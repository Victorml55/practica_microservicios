<?php
// api-usuarios (responsable: Victor)
require __DIR__ . '/../src/http.php';
require __DIR__ . '/../src/jwt.php';
require __DIR__ . '/../src/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$path   = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

try {
    if ($method === 'GET' && $path === '/health') {
        db()->query('SELECT 1');
        json(['servicio' => 'api-usuarios', 'ok' => true]);
    }

    // POST /api/usuarios/registro  { nombre, correo, contrasena }
    if ($method === 'POST' && $path === '/api/usuarios/registro') {
        $b = body();
        $nombre     = trim($b['nombre'] ?? '');
        $correo     = strtolower(trim($b['correo'] ?? ''));
        $contrasena = (string)($b['contrasena'] ?? '');

        if ($nombre === '' || $correo === '' || $contrasena === '')
            error('nombre, correo y contrasena son obligatorios', 400);
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL))
            error('Correo inválido', 400);
        if (strlen($contrasena) < 6)
            error('La contrasena debe tener al menos 6 caracteres', 400);

        $st = db()->prepare('SELECT 1 FROM usuarios WHERE correo = ?');
        $st->execute([$correo]);
        if ($st->fetchColumn()) error('El correo ya está registrado', 400);

        $st = db()->prepare('INSERT INTO usuarios (nombre, correo, contrasena) VALUES (?, ?, ?)');
        $st->execute([$nombre, $correo, password_hash($contrasena, PASSWORD_DEFAULT)]);

        $st = db()->prepare('SELECT * FROM usuarios WHERE id = ?');
        $st->execute([db()->lastInsertId()]);
        json(usuario_publico($st->fetch()), 201);
    }

    // POST /api/usuarios/login  { correo, contrasena }
    if ($method === 'POST' && $path === '/api/usuarios/login') {
        $b = body();
        $correo     = strtolower(trim($b['correo'] ?? ''));
        $contrasena = (string)($b['contrasena'] ?? '');
        if ($correo === '' || $contrasena === '')
            error('correo y contrasena son obligatorios', 400);

        $st = db()->prepare('SELECT * FROM usuarios WHERE correo = ?');
        $st->execute([$correo]);
        $u = $st->fetch();
        if (!$u || !password_verify($contrasena, $u['contrasena']))
            error('Credenciales incorrectas', 401);

        $token = jwt_crear(['sub' => (int)$u['id'], 'rol' => $u['rol'], 'nombre' => $u['nombre']]);
        json(['token' => $token, 'usuario' => usuario_publico($u)]);
    }

    // GET /api/usuarios/{id}   (requiere token; solo el propio usuario o un admin)
    if ($method === 'GET' && preg_match('#^/api/usuarios/(\d+)$#', $path, $m)) {
        $auth = requerir_auth();
        $id = (int)$m[1];
        if ($auth['sub'] !== $id && $auth['rol'] !== 'admin')
            error('No puedes consultar el perfil de otro usuario', 403);

        $st = db()->prepare('SELECT * FROM usuarios WHERE id = ?');
        $st->execute([$id]);
        $u = $st->fetch();
        if (!$u) error('Usuario no encontrado', 404);
        json(usuario_publico($u));
    }

    error('Ruta no encontrada', 404);
} catch (Throwable $e) {
    error('Error interno: ' . $e->getMessage(), 500);
}
