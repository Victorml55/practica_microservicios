<?php
// api-pedidos (responsable: Victor)
require __DIR__ . '/../src/http.php';
require __DIR__ . '/../src/jwt.php';
require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/servicios.php';

const ESTADOS = ['pendiente', 'pagado', 'enviado', 'cancelado'];
// Transiciones permitidas
const TRANSICIONES = [
    'pendiente' => ['pagado', 'cancelado'],
    'pagado'    => ['enviado', 'cancelado'],
    'enviado'   => [],
    'cancelado' => [],
];

$method = $_SERVER['REQUEST_METHOD'];
$path   = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

// El usuario del token solo puede tocar sus propios pedidos (salvo admin)
function validar_dueno(array $auth, int $usuario_id): void {
    if ((int)$auth['sub'] !== $usuario_id && ($auth['rol'] ?? '') !== 'admin')
        error('No puedes acceder a pedidos de otro usuario', 403);
}

try {
    if ($method === 'GET' && $path === '/health') {
        db()->query('SELECT 1');
        json(['servicio' => 'api-pedidos', 'ok' => true, 'db' => 'ok']);
    }

    // POST /api/pedidos   (requiere token)
    // { usuario_id, items:[{producto_id, cantidad, precio_unitario}], direccion, codigo_postal, costo_envio }
    if ($method === 'POST' && $path === '/api/pedidos') {
        $auth = requerir_auth();
        $b = body();

        $usuario_id    = (int)($b['usuario_id'] ?? 0);
        $items         = $b['items'] ?? [];
        $direccion     = trim($b['direccion'] ?? '');
        $codigo_postal = trim((string)($b['codigo_postal'] ?? ''));
        $costo_envio   = (float)($b['costo_envio'] ?? 0);

        if ($usuario_id <= 0 || $direccion === '' || $codigo_postal === '')
            error('usuario_id, direccion y codigo_postal son obligatorios', 400);
        if (!is_array($items) || count($items) === 0)
            error('El pedido debe tener al menos un item', 400);
        if ($costo_envio < 0) error('costo_envio no puede ser negativo', 400);
        validar_dueno($auth, $usuario_id);

        $subtotal = 0;
        foreach ($items as $i => $it) {
            $pid = (int)($it['producto_id'] ?? 0);
            $cant = (int)($it['cantidad'] ?? 0);
            $precio = (float)($it['precio_unitario'] ?? -1);
            if ($pid <= 0 || $cant <= 0 || $precio < 0)
                error("Item $i inválido: requiere producto_id, cantidad > 0 y precio_unitario", 400);
            $subtotal += $cant * $precio;
        }
        $subtotal = round($subtotal, 2);
        $total = round($subtotal + $costo_envio, 2);

        $pdo = db();
        $pdo->beginTransaction();
        $st = $pdo->prepare('INSERT INTO pedidos (usuario_id, direccion, codigo_postal, subtotal, costo_envio, total)
                             VALUES (?, ?, ?, ?, ?, ?) RETURNING id');
        $st->execute([$usuario_id, $direccion, $codigo_postal, $subtotal, $costo_envio, $total]);
        $id = (int)$st->fetchColumn();

        $st = $pdo->prepare('INSERT INTO pedido_items (pedido_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)');
        foreach ($items as $it) {
            $st->execute([$id, (int)$it['producto_id'], (int)$it['cantidad'], (float)$it['precio_unitario']]);
        }
        $pdo->commit();

        json(pedido_por_id($id), 201);
    }

    // GET /api/pedidos/usuario/{usuario_id}   (requiere token)
    if ($method === 'GET' && preg_match('#^/api/pedidos/usuario/(\d+)$#', $path, $m)) {
        $auth = requerir_auth();
        $usuario_id = (int)$m[1];
        validar_dueno($auth, $usuario_id);

        $st = db()->prepare('SELECT id FROM pedidos WHERE usuario_id = ? ORDER BY creado_en DESC, id DESC');
        $st->execute([$usuario_id]);
        json(array_map(fn($id) => pedido_por_id((int)$id), $st->fetchAll(PDO::FETCH_COLUMN)));
    }

    // GET /api/pedidos/{id}   (extra: lo usa la confirmación de orden y api-pagos)
    if ($method === 'GET' && preg_match('#^/api/pedidos/(\d+)$#', $path, $m)) {
        $p = pedido_por_id((int)$m[1]);
        if (!$p) error('Pedido no encontrado', 404);
        json($p);
    }

    // PATCH /api/pedidos/{id}/estado   { estado }
    // Sin token: lo llama api-pagos dentro de la red Docker.
    if ($method === 'PATCH' && preg_match('#^/api/pedidos/(\d+)/estado$#', $path, $m)) {
        $id = (int)$m[1];
        $nuevo = strtolower(trim(body()['estado'] ?? ''));
        if (!in_array($nuevo, ESTADOS, true))
            error('estado debe ser: ' . implode(', ', ESTADOS), 400);

        $pedido = pedido_por_id($id);
        if (!$pedido) error('Pedido no encontrado', 404);

        $actual = $pedido['estado'];
        if ($actual === $nuevo) json($pedido + ['mensaje' => "El pedido ya estaba en $nuevo"]);
        if (!in_array($nuevo, TRANSICIONES[$actual], true))
            error("No se puede pasar de $actual a $nuevo", 400);

        $integracion = null;
        $guia = $pedido['guia'];
        if ($nuevo === 'pagado') {
            $integracion = orquestar_pago($pedido);
            $guia = $integracion['guia'] ?? $guia;
        }

        $st = db()->prepare('UPDATE pedidos SET estado = ?, guia = ?, actualizado_en = NOW() WHERE id = ?');
        $st->execute([$nuevo, $guia, $id]);

        $respuesta = pedido_por_id($id);
        if ($integracion) $respuesta['integracion'] = $integracion['pasos'];
        json($respuesta);
    }

    error('Ruta no encontrada', 404);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error('Error interno: ' . $e->getMessage(), 500);
}
