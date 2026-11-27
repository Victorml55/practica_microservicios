<?php
// Cliente HTTP mínimo para llamar a otros microservicios dentro de red-tienda.
// Todas las llamadas llevan X-Internal-Key para que los endpoints internos las acepten.
function llamar(string $metodo, string $url, ?array $data = null): array {
    $headers = "Content-Type: application/json\r\nAccept: application/json\r\n";
    $clave = getenv('INTERNAL_KEY') ?: '';
    if ($clave !== '') $headers .= "X-Internal-Key: $clave\r\n";

    $opts = ['http' => [
        'method'        => $metodo,
        'header'        => $headers,
        'timeout'       => 5,
        'ignore_errors' => true,   // para leer el body aunque responda 4xx/5xx
    ]];
    if ($data !== null) $opts['http']['content'] = json_encode($data);

    $respuesta = @file_get_contents($url, false, stream_context_create($opts));
    if ($respuesta === false) {
        return ['ok' => false, 'status' => 0, 'data' => ['error' => "Sin conexión con $url"]];
    }
    $status = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $status = (int)$m[1];
    }
    return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'data' => json_decode($respuesta, true)];
}

function url_servicio(string $var, string $defecto): string {
    return rtrim(getenv($var) ?: $defecto, '/');
}

function detalle_error(array $r): string {
    return $r['data']['error'] ?? $r['data']['detail'] ?? "HTTP {$r['status']}";
}

/**
 * Valida los items contra los servicios reales (el precio y el stock NO se confían al navegador):
 *   - precio_unitario sale de api-productos
 *   - se comprueba que api-inventario tenga unidades suficientes
 * Devuelve los items normalizados o lanza error 400 / 500.
 */
function preparar_items(array $items): array {
    $productos  = url_servicio('PRODUCTOS_URL', 'http://api-productos:8002');
    $inventario = url_servicio('INVENTARIO_URL', 'http://api-inventario:8004');

    // Agrupa por producto (si el mismo producto viene dos veces se suman las cantidades)
    $cantidades = [];
    foreach ($items as $i => $it) {
        $pid  = (int)($it['producto_id'] ?? 0);
        $cant = (int)($it['cantidad'] ?? 0);
        if ($pid <= 0 || $cant <= 0) error("Item $i inválido: requiere producto_id y cantidad > 0", 400);
        $cantidades[$pid] = ($cantidades[$pid] ?? 0) + $cant;
    }

    $resultado = [];
    foreach ($cantidades as $pid => $cant) {
        $p = llamar('GET', "$productos/api/productos/$pid");
        if ($p['status'] === 404) error("El producto $pid no existe", 400);
        if (!$p['ok']) error('No se pudo consultar api-productos: ' . detalle_error($p), 500);

        $inv = llamar('GET', "$inventario/api/inventario/$pid");
        if (!$inv['ok']) error("No hay inventario registrado para el producto $pid", 400);
        $disp = (int)($inv['data']['disponibles'] ?? 0);
        if ($disp < $cant)
            error("Stock insuficiente para \"{$p['data']['nombre']}\": disponibles $disp, solicitados $cant", 400);

        $resultado[] = ['producto_id' => $pid, 'cantidad' => $cant, 'precio_unitario' => (float)$p['data']['precio']];
    }
    return $resultado;
}

/** El costo de envío lo calcula api-envios con el CP y el total de piezas. */
function cotizar_envio(string $codigo_postal, int $piezas): float {
    $envios = url_servicio('ENVIOS_URL', 'http://api-envios:8003');
    $r = llamar('POST', "$envios/api/envios/cotizar", ['codigo_postal' => $codigo_postal, 'cantidad' => $piezas]);
    if ($r['status'] === 400) error('Código postal inválido: ' . detalle_error($r), 400);
    if (!$r['ok']) error('No se pudo cotizar el envío: ' . detalle_error($r), 500);
    return (float)$r['data']['costo_envio'];
}

/**
 * Al pasar un pedido a "pagado":
 *   1) reserva inventario por cada item; si alguno falla se devuelven las unidades
 *      ya reservadas y se aborta (el pedido NO pasa a pagado)
 *   2) vacía el carrito del usuario
 *   3) genera la guía de envío
 * Devuelve ['ok' => bool, 'error' => ?string, 'guia' => ?string, 'pasos' => [...]].
 */
function orquestar_pago(array $pedido): array {
    $inv     = url_servicio('INVENTARIO_URL', 'http://api-inventario:8004');
    $carrito = url_servicio('CARRITO_URL', 'http://api-carrito:8005');
    $envios  = url_servicio('ENVIOS_URL', 'http://api-envios:8003');

    $pasos = ['inventario' => [], 'carrito' => null, 'envios' => null];

    $reservados = [];
    foreach ($pedido['items'] as $item) {
        $r = llamar('POST', "$inv/api/inventario/reservar", [
            'producto_id' => $item['producto_id'],
            'cantidad'    => $item['cantidad'],
        ]);
        $pasos['inventario'][] = ['producto_id' => $item['producto_id'], 'ok' => $r['ok'],
                                  'detalle' => $r['ok'] ? null : detalle_error($r)];
        if (!$r['ok']) {
            // Compensación: regresar lo que ya se había reservado
            foreach ($reservados as $res) llamar('POST', "$inv/api/inventario/reabastecer", $res);
            return ['ok' => false, 'error' => "No se pudo reservar el producto {$item['producto_id']}: " . detalle_error($r),
                    'guia' => null, 'pasos' => $pasos];
        }
        $reservados[] = ['producto_id' => $item['producto_id'], 'cantidad' => $item['cantidad']];
    }

    $r = llamar('DELETE', "$carrito/api/carrito/{$pedido['usuario_id']}/vaciar");
    $pasos['carrito'] = ['ok' => $r['ok'], 'detalle' => $r['ok'] ? null : detalle_error($r)];

    $r = llamar('POST', "$envios/api/envios/generar", ['pedido_id' => $pedido['id']]);
    $guia = $r['ok'] ? ($r['data']['guia'] ?? null) : null;
    $pasos['envios'] = ['ok' => $guia !== null, 'guia' => $guia, 'detalle' => $r['ok'] ? null : detalle_error($r)];

    return ['ok' => true, 'error' => null, 'guia' => $guia, 'pasos' => $pasos];
}
