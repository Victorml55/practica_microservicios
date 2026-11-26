<?php
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s',
        getenv('DB_HOST') ?: 'localhost', getenv('DB_PORT') ?: '5432', getenv('DB_NAME') ?: 'pedidos_db');
    $pdo = new PDO($dsn, getenv('DB_USER') ?: 'tienda', getenv('DB_PASSWORD') ?: 'tienda123', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pedidos (
            id              SERIAL PRIMARY KEY,
            usuario_id      INTEGER NOT NULL,
            direccion       TEXT NOT NULL,
            codigo_postal   VARCHAR(10) NOT NULL,
            subtotal        NUMERIC(10,2) NOT NULL,
            costo_envio     NUMERIC(10,2) NOT NULL DEFAULT 0,
            total           NUMERIC(10,2) NOT NULL,
            estado          VARCHAR(20) NOT NULL DEFAULT 'pendiente'
                            CHECK (estado IN ('pendiente','pagado','enviado','cancelado')),
            guia            VARCHAR(50),
            creado_en       TIMESTAMP NOT NULL DEFAULT NOW(),
            actualizado_en  TIMESTAMP NOT NULL DEFAULT NOW()
        );
        CREATE TABLE IF NOT EXISTS pedido_items (
            id              SERIAL PRIMARY KEY,
            pedido_id       INTEGER NOT NULL REFERENCES pedidos(id) ON DELETE CASCADE,
            producto_id     INTEGER NOT NULL,
            cantidad        INTEGER NOT NULL CHECK (cantidad > 0),
            precio_unitario NUMERIC(10,2) NOT NULL
        );
        CREATE INDEX IF NOT EXISTS idx_pedidos_usuario ON pedidos(usuario_id);
    ");
    return $pdo;
}

// Carga un pedido con sus items, con tipos numéricos correctos
function pedido_por_id(int $id): ?array {
    $st = db()->prepare('SELECT * FROM pedidos WHERE id = ?');
    $st->execute([$id]);
    $p = $st->fetch();
    if (!$p) return null;

    $st = db()->prepare('SELECT producto_id, cantidad, precio_unitario FROM pedido_items WHERE pedido_id = ? ORDER BY id');
    $st->execute([$id]);
    $items = array_map(fn($i) => [
        'producto_id'     => (int)$i['producto_id'],
        'cantidad'        => (int)$i['cantidad'],
        'precio_unitario' => (float)$i['precio_unitario'],
    ], $st->fetchAll());

    return [
        'id'             => (int)$p['id'],
        'usuario_id'     => (int)$p['usuario_id'],
        'direccion'      => $p['direccion'],
        'codigo_postal'  => $p['codigo_postal'],
        'items'          => $items,
        'subtotal'       => (float)$p['subtotal'],
        'costo_envio'    => (float)$p['costo_envio'],
        'total'          => (float)$p['total'],
        'estado'         => $p['estado'],
        'guia'           => $p['guia'],
        'creado_en'      => $p['creado_en'],
        'actualizado_en' => $p['actualizado_en'],
    ];
}
