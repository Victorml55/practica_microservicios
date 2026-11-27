<?php
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    $ruta = getenv('DB_PATH') ?: __DIR__ . '/../data/usuarios.sqlite';
    if (!is_dir(dirname($ruta))) mkdir(dirname($ruta), 0777, true);

    $pdo = new PDO('sqlite:' . $ruta, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL,
        correo TEXT NOT NULL UNIQUE,
        contrasena TEXT NOT NULL,
        rol TEXT NOT NULL DEFAULT 'cliente',
        creado_en TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");

    // Datos semilla (acuerdo del equipo): demo@tienda.com / demo123 con id 1
    if ((int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() === 0) {
        $st = $pdo->prepare('INSERT INTO usuarios (id, nombre, correo, contrasena, rol) VALUES (?, ?, ?, ?, ?)');
        $st->execute([1, 'Usuario Demo', 'demo@tienda.com', password_hash('demo123', PASSWORD_DEFAULT), 'cliente']);
        $st->execute([2, 'Administrador', 'admin@tienda.com', password_hash('admin123', PASSWORD_DEFAULT), 'admin']);
    }
    return $pdo;
}

function usuario_publico(array $u): array {
    return [
        'id'        => (int)$u['id'],
        'nombre'    => $u['nombre'],
        'correo'    => $u['correo'],
        'rol'       => $u['rol'],
        'creado_en' => $u['creado_en'],
    ];
}
