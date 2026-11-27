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

// ---------- Base de datos SQLite y 10 productos semilla ----------
$pdo = new PDO('sqlite:' . sys_get_temp_dir() . '/productos.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$pdo->exec("
    CREATE TABLE IF NOT EXISTS productos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL,
        descripcion TEXT NOT NULL,
        precio REAL NOT NULL,
        categoria TEXT NOT NULL,
        imagen_url TEXT NOT NULL
    )
");

if ((int)$pdo->query("SELECT COUNT(*) FROM productos")->fetchColumn() === 0) {
    $semillas = [
        ['Laptop Pro 14"', 'Ultrabook 16GB RAM y 512GB SSD NVMe', 18999.90, 'Electronica', 'https://picsum.photos/seed/prod1/400/300'],
        ['Mouse Inalambrico Ergonomico', 'Sensor optico 4000 DPI recargable USB-C', 549.00, 'Accesorios', 'https://picsum.photos/seed/prod2/400/300'],
        ['Teclado Mecanico RGB', 'Switches lineales silenciosos en espanol', 1299.50, 'Accesorios', 'https://picsum.photos/seed/prod3/400/300'],
        ['Monitor 27" 144Hz', 'Panel IPS Full HD con tiempo de respuesta 1ms', 4599.00, 'Electronica', 'https://picsum.photos/seed/prod4/400/300'],
        ['Audifonos Bluetooth ANC', 'Cancelacion activa de ruido y 30h de bateria', 2199.00, 'Audio', 'https://picsum.photos/seed/prod5/400/300'],
        ['Silla Ergonomica Oficina', 'Soporte lumbar ajustable y respaldo de malla', 3490.00, 'Muebles', 'https://picsum.photos/seed/prod6/400/300'],
        ['Mochila Antirrobo Laptop', 'Impermeable con puerto USB para equipos de 15.6"', 799.00, 'Accesorios', 'https://picsum.photos/seed/prod7/400/300'],
        ['Hub USB-C 7 en 1', 'Salida HDMI 4K, Ethernet RJ45 y carga rapida PD', 689.90, 'Accesorios', 'https://picsum.photos/seed/prod8/400/300'],
        ['Microfono Condensador USB', 'Patron cardioide ideal para streaming y reuniones', 1450.00, 'Audio', 'https://picsum.photos/seed/prod9/400/300'],
        ['Lampara Escritorio LED', 'Control tactil con 3 temperaturas de color', 420.00, 'Hogar', 'https://picsum.photos/seed/prod10/400/300'],
    ];

    $stmt = $pdo->prepare("INSERT INTO productos (nombre, descripcion, precio, categoria, imagen_url) VALUES (?, ?, ?, ?, ?)");
    foreach ($semillas as $s) {
        $stmt->execute($s);
    }
}

function formatearProducto(array $row): array {
    return [
        'id' => (int)$row['id'],
        'nombre' => $row['nombre'],
        'descripcion' => $row['descripcion'],
        'precio' => (float)$row['precio'],
        'categoria' => $row['categoria'],
        'imagen_url' => $row['imagen_url'],
    ];
}

$method = $_SERVER['REQUEST_METHOD'];
$path   = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

// ---------- Rutas api-productos (responsable: Dennis) ----------
if ($method === 'GET' && $path === '/health') json(['servicio' => 'api-productos', 'ok' => true]);

if ($method === 'GET' && $path === '/api/productos') {
    $q = trim($_GET['q'] ?? '');
    $categoria = trim($_GET['categoria'] ?? '');

    $sql = "SELECT * FROM productos WHERE 1=1";
    $params = [];

    if ($q !== '') {
        $sql .= " AND (LOWER(nombre) LIKE :q OR LOWER(descripcion) LIKE :q)";
        $params[':q'] = '%' . mb_strtolower($q) . '%';
    }
    if ($categoria !== '') {
        $sql .= " AND LOWER(categoria) = :categoria";
        $params[':categoria'] = mb_strtolower($categoria);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    json(array_map('formatearProducto', $stmt->fetchAll()));
}

if ($method === 'GET' && preg_match('#^/api/productos/(\d+)$#', $path, $m)) {
    $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
    $stmt->execute([(int)$m[1]]);
    $producto = $stmt->fetch();

    if (!$producto) {
        json(['error' => 'Producto no encontrado'], 404);
    }
    json(formatearProducto($producto));
}

if ($method === 'POST' && $path === '/api/productos') {
    $data = body();
    $requeridos = ['nombre', 'descripcion', 'precio', 'categoria', 'imagen_url'];

    foreach ($requeridos as $campo) {
        if (!isset($data[$campo]) || trim((string)$data[$campo]) === '') {
            json(['error' => "El campo '$campo' es obligatorio"], 400);
        }
    }

    if (!is_numeric($data['precio']) || (float)$data['precio'] <= 0) {
        json(['error' => 'El precio debe ser un numero decimal mayor a 0'], 400);
    }

    $stmt = $pdo->prepare("
        INSERT INTO productos (nombre, descripcion, precio, categoria, imagen_url)
        VALUES (:nombre, :descripcion, :precio, :categoria, :imagen_url)
    ");
    $stmt->execute([
        ':nombre' => trim((string)$data['nombre']),
        ':descripcion' => trim((string)$data['descripcion']),
        ':precio' => (float)$data['precio'],
        ':categoria' => trim((string)$data['categoria']),
        ':imagen_url' => trim((string)$data['imagen_url']),
    ]);

    $id = (int)$pdo->lastInsertId();
    $stmtGet = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
    $stmtGet->execute([$id]);
    json(formatearProducto($stmtGet->fetch()), 201);
}

json(['error' => 'Ruta no encontrada'], 404);
