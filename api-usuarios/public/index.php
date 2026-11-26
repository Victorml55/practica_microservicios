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

// ---------- Rutas api-usuarios (responsable: Victor) ----------
if ($method === 'GET' && $path === '/health') json(['servicio' => 'api-usuarios', 'ok' => true]);

if ($method === 'POST' && $path === '/api/usuarios/registro') {
    // TODO: validar nombre, correo, contrasena; guardar con password_hash(); 201
    json(['error' => 'No implementado'], 501);
}
if ($method === 'POST' && $path === '/api/usuarios/login') {
    // TODO: validar credenciales y devolver { token, usuario: {id, nombre, correo, rol} }
    // Token JWT HS256 firmado con getenv('JWT_SECRET'), payload: { sub: id, rol, exp }
    json(['error' => 'No implementado'], 501);
}
if ($method === 'GET' && preg_match('#^/api/usuarios/(\d+)$#', $path, $m)) {
    // TODO: devolver perfil (sin contrasena) o 404
    json(['error' => 'No implementado', 'id' => (int)$m[1]], 501);
}

json(['error' => 'Ruta no encontrada'], 404);
