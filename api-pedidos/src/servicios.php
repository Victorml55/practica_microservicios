<?php
// Cliente HTTP mínimo para llamar a otros microservicios dentro de red-tienda
function llamar(string $metodo, string $url, ?array $data = null): array {
    $opts = ['http' => [
        'method'        => $metodo,
        'header'        => "Content-Type: application/json\r\nAccept: application/json\r\n",
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

/**
 * Al pasar un pedido a "pagado":
 *   1) reserva inventario por cada item
 *   2) vacía el carrito del usuario
 *   3) genera la guía de envío
 * Devuelve el resultado de cada paso y la guía (si se generó).
 */
function orquestar_pago(array $pedido): array {
    $inv     = url_servicio('INVENTARIO_URL', 'http://api-inventario:8004');
    $carrito = url_servicio('CARRITO_URL', 'http://api-carrito:8005');
    $envios  = url_servicio('ENVIOS_URL', 'http://api-envios:8003');

    $resultado = ['inventario' => [], 'carrito' => null, 'envios' => null];

    foreach ($pedido['items'] as $item) {
        $r = llamar('POST', "$inv/api/inventario/reservar", [
            'producto_id' => $item['producto_id'],
            'cantidad'    => $item['cantidad'],
        ]);
        $resultado['inventario'][] = [
            'producto_id' => $item['producto_id'],
            'ok'          => $r['ok'],
            'detalle'     => $r['ok'] ? null : ($r['data']['error'] ?? "HTTP {$r['status']}"),
        ];
    }

    $r = llamar('DELETE', "$carrito/api/carrito/{$pedido['usuario_id']}/vaciar");
    $resultado['carrito'] = ['ok' => $r['ok'], 'detalle' => $r['ok'] ? null : ($r['data']['error'] ?? "HTTP {$r['status']}")];

    $r = llamar('POST', "$envios/api/envios/generar", ['pedido_id' => $pedido['id']]);
    $guia = $r['ok'] ? ($r['data']['guia'] ?? null) : null;
    $resultado['envios'] = ['ok' => $r['ok'] && $guia !== null, 'guia' => $guia,
                            'detalle' => $r['ok'] ? null : ($r['data']['error'] ?? $r['data']['detail'] ?? "HTTP {$r['status']}")];

    return ['guia' => $guia, 'pasos' => $resultado];
}
