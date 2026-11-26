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

// ---------- Rutas api-productos (responsable: Dennis) ----------
if ($method === 'GET' && $path === '/health') json(['servicio' => 'api-productos', 'ok' => true]);

if ($method === 'GET' && $path === '/api/productos') {
    // TODO: listar productos. Opcional: ?q=texto&categoria=xxx
    json(['error' => 'No implementado'], 501);
}
if ($method === 'GET' && preg_match('#^/api/productos/(\d+)$#', $path, $m)) {
    // TODO: detalle del producto o 404
    json(['error' => 'No implementado', 'id' => (int)$m[1]], 501);
}
if ($method === 'POST' && $path === '/api/productos') {
    // TODO: crear producto (nombre, descripcion, precio, categoria, imagen_url); 201
    json(['error' => 'No implementado'], 501);
}

json(['error' => 'Ruta no encontrada'], 404);
