<?php
// JWT HS256 sin dependencias externas
function b64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function b64url_decode(string $data): string|false {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}

function jwt_secret(): string {
    $s = getenv('JWT_SECRET');
    if (!$s) error('JWT_SECRET no configurado', 500);
    return $s;
}

function jwt_crear(array $payload, int $duracion = 7200): string {
    $payload['iat'] = time();
    $payload['exp'] = time() + $duracion;
    $h = b64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $p = b64url_encode(json_encode($payload));
    $f = b64url_encode(hash_hmac('sha256', "$h.$p", jwt_secret(), true));
    return "$h.$p.$f";
}

// Devuelve el payload o null si el token es inválido / expiró
function jwt_verificar(?string $token): ?array {
    if (!$token || substr_count($token, '.') !== 2) return null;
    [$h, $p, $f] = explode('.', $token);
    $esperada = b64url_encode(hash_hmac('sha256', "$h.$p", jwt_secret(), true));
    if (!hash_equals($esperada, $f)) return null;
    $header = json_decode(b64url_decode($h), true);
    if (($header['alg'] ?? '') !== 'HS256') return null;
    $payload = json_decode(b64url_decode($p), true);
    if (!is_array($payload) || ($payload['exp'] ?? 0) < time()) return null;
    return $payload;
}

// Exige token válido; responde 401 si no lo hay
function requerir_auth(): array {
    $payload = jwt_verificar(bearer());
    if (!$payload) error('Token inválido o ausente', 401);
    return $payload;
}
