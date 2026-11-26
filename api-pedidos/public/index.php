<?php
// ---------- Utilidades comunes (CORS + JSON) ----------
header('Access-Control-Allow-Origin: ' . (getenv('CORS_ORIGIN') ?: '*'));
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

function json($data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function body(): array {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

$method = $_SERVER['REQUEST_METHOD'];
$path   = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

// ---------- Conexión PostgreSQL ----------
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', getenv('DB_HOST'), getenv('DB_PORT'), getenv('DB_NAME'));
    $pdo = new PDO($dsn, getenv('DB_USER'), getenv('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    return $pdo;
}

// ---------- Rutas api-pedidos (responsable: Victor) ----------
if ($method === 'GET' && $path === '/health') {
    try { db()->query('SELECT 1'); json(['servicio' => 'api-pedidos', 'ok' => true, 'db' => 'ok']); }
    catch (Throwable $e) { json(['servicio' => 'api-pedidos', 'ok' => false, 'db' => $e->getMessage()], 500); }
}

if ($method === 'POST' && $path === '/api/pedidos') {
    // TODO: crear pedido { usuario_id, items:[{producto_id,cantidad,precio_unitario}], direccion, codigo_postal, costo_envio }
    // estado inicial: 'pendiente'; 201 con el pedido creado
    json(['error' => 'No implementado'], 501);
}
if ($method === 'GET' && preg_match('#^/api/pedidos/usuario/(\d+)$#', $path, $m)) {
    // TODO: historial del usuario (incluir estado y guia si ya existe)
    json(['error' => 'No implementado', 'usuario_id' => (int)$m[1]], 501);
}
if ($method === 'PATCH' && preg_match('#^/api/pedidos/(\d+)/estado$#', $path, $m)) {
    // TODO: { estado: pendiente|pagado|enviado|cancelado }
    // Si estado = 'pagado' -> ORQUESTAR:
    //   1) POST INVENTARIO_URL/api/inventario/reservar  (por cada item)
    //   2) DELETE CARRITO_URL/api/carrito/{usuario_id}/vaciar
    //   3) POST ENVIOS_URL/api/envios/generar { pedido_id } -> guardar guia
    json(['error' => 'No implementado', 'id' => (int)$m[1]], 501);
}

json(['error' => 'Ruta no encontrada'], 404);
