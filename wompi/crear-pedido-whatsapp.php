<?php
// Guarda en la base de datos el pedido que el cliente va a enviar por WhatsApp
// (pago por transferencia/depósito, verificado manualmente por la tienda).
// No cobra nada ni decuenta stock todavía — eso ocurre cuando el admin marca
// el pedido como pagado desde el panel (ver admin/pedidos.php).

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/load_config.php';
require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

$entrada = json_decode(file_get_contents('php://input'), true);

if (!is_array($entrada) || empty($entrada['items']) || !is_array($entrada['items'])) {
    http_response_code(400);
    echo json_encode(['error' => 'El carrito está vacío o es inválido.']);
    exit;
}

$comprador = is_array($entrada['comprador'] ?? null) ? $entrada['comprador'] : [];

$emailComprador = trim((string) ($comprador['email'] ?? ''));
if (!filter_var($emailComprador, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'El correo electrónico no es válido.']);
    exit;
}

// Sumar cantidades solicitadas por nombre de producto; el precio se busca
// siempre en la base de datos, nunca se toma de lo que mande el navegador.
$cantidadesPedidas = [];
foreach ($entrada['items'] as $item) {
    $nombre   = isset($item['nombre'])   ? trim((string) $item['nombre']) : '';
    $cantidad = isset($item['cantidad']) ? (int) $item['cantidad']        : 0;

    if ($nombre === '' || $cantidad <= 0) {
        continue;
    }

    $cantidadesPedidas[$nombre] = ($cantidadesPedidas[$nombre] ?? 0) + $cantidad;
}

if (empty($cantidadesPedidas)) {
    http_response_code(400);
    echo json_encode(['error' => 'El carrito no tiene productos válidos.']);
    exit;
}

try {
    $db = conectarDb();
} catch (Throwable $e) {
    error_log('Error conectando a la base de datos en crear-pedido-whatsapp.php: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['error' => 'No se pudo registrar el pedido. Intenta de nuevo en un momento.']);
    exit;
}

$placeholders = implode(',', array_fill(0, count($cantidadesPedidas), '?'));
$stmtProductos = $db->prepare(
    "SELECT nombre, precio, stock FROM productos WHERE activo = 1 AND nombre IN ({$placeholders})"
);
$stmtProductos->execute(array_keys($cantidadesPedidas));
$productosDb = [];
foreach ($stmtProductos->fetchAll() as $p) {
    $productosDb[$p['nombre']] = $p;
}

$items = [];
$total = 0;
foreach ($cantidadesPedidas as $nombre => $cantidad) {
    if (!isset($productosDb[$nombre])) {
        http_response_code(400);
        echo json_encode(['error' => "El producto \"{$nombre}\" ya no está disponible."]);
        exit;
    }

    $precio = (float) $productosDb[$nombre]['precio'];
    $stock  = (int) $productosDb[$nombre]['stock'];

    if ($cantidad > $stock) {
        http_response_code(409);
        echo json_encode(['error' => "Stock insuficiente para \"{$nombre}\" (disponible: {$stock})."]);
        exit;
    }

    $items[] = [
        'title'      => $nombre,
        'unit_price' => $precio,
        'quantity'   => $cantidad,
    ];
    $total += $precio * $cantidad;
}

// Referencia con prefijo "WSP" para distinguir a simple vista los pedidos que
// llegaron por WhatsApp de los pagados en línea con Wompi.
$referencia = 'YCAPS-WSP-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));

try {
    guardarPedido($db, $comprador, $items, $total, $referencia, 'whatsapp');
} catch (Throwable $e) {
    error_log('Error guardando pedido de WhatsApp en la base de datos: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['error' => 'No se pudo registrar el pedido. Intenta de nuevo en un momento.']);
    exit;
}

try {
    require_once __DIR__ . '/mailer.php';
    enviarEmailNuevoPedido($comprador, $items, $total, $referencia, 'whatsapp');
} catch (Throwable $e) {
    error_log('Error enviando email de pedido WhatsApp: ' . $e->getMessage());
}

echo json_encode(['ok' => true, 'referencia' => $referencia]);
