<?php
// Genera la URL firmada del Checkout de Wompi a partir del carrito y los datos
// del comprador enviados por el frontend. Guarda el pedido en la base de datos.

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

// Validar el email del comprador: rechaza valores mal formados o con saltos de
// línea (que podrían usarse para inyectar cabeceras en los correos de alerta).
$emailComprador = trim((string) ($comprador['email'] ?? ''));
if (!filter_var($emailComprador, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'El correo electrónico no es válido.']);
    exit;
}

// Sumar cantidades solicitadas por nombre de producto (el navegador puede mandar
// duplicados); el precio NUNCA se toma de aquí — solo se usa para saber qué y
// cuánto pidieron. El precio real se busca en la base de datos más abajo.
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

// Validar cada producto contra la base de datos: precio real y stock disponible.
// Así evitamos que alguien manipule el precio o la cantidad desde el navegador.
try {
    $db = conectarDb();
} catch (Throwable $e) {
    error_log('Error conectando a la base de datos en crear-transaccion.php: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['error' => 'No se pudo validar el pedido. Intenta de nuevo en un momento.']);
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

$items         = [];
$totalCentavos = 0;
foreach ($cantidadesPedidas as $nombre => $cantidad) {
    if (!isset($productosDb[$nombre])) {
        http_response_code(400);
        echo json_encode(['error' => "El producto \"{$nombre}\" ya no está disponible."]);
        exit;
    }

    $precio = (float) $productosDb[$nombre]['precio'];
    $stock  = (int) $productosDb[$nombre]['stock'];

    if ($precio <= 0) {
        http_response_code(400);
        echo json_encode(['error' => "El producto \"{$nombre}\" no está disponible para compra en línea."]);
        exit;
    }

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
    $totalCentavos += (int) round($precio * $cantidad * 100);
}

// Referencia única por pedido — se guarda en la DB y Wompi la devuelve en el webhook
$referencia = 'YCAPS-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));

// Firma de integridad: SHA256(referencia + monto_en_centavos + moneda + secreto_integridad)
// Wompi la verifica para garantizar que la URL no fue modificada en tránsito.
$firma = hash('sha256', $referencia . $totalCentavos . 'COP' . WOMPI_INTEGRITY_SECRET);

// Guardar el pedido ANTES de redirigir (estado inicial: "pendiente")
$total = $totalCentavos / 100;
try {
    guardarPedido($db, $comprador, $items, $total, $referencia);
} catch (Throwable $e) {
    error_log('Error guardando pedido en la base de datos: ' . $e->getMessage());
    // No bloquear el pago si falla el guardado; el webhook puede actualizar después
}

// Enviar email de confirmación de pedido recibido
try {
    require_once __DIR__ . '/mailer.php';
    enviarEmailNuevoPedido($comprador, $items, $total, $referencia);
} catch (Throwable $e) {
    error_log('Error enviando email de pedido: ' . $e->getMessage());
}

// Solo los parámetros requeridos por Wompi — los opcionales (customer-data,
// shipping-address) causan conflictos de parseo en el JS de Wompi cuando
// el navegador re-codifica los dos puntos del nombre de parámetro.
$checkoutUrl = 'https://checkout.wompi.co/p/'
    . '?public-key='          . urlencode(WOMPI_PUBLIC_KEY)
    . '&currency=COP'
    . '&amount-in-cents='     . $totalCentavos
    . '&reference='           . urlencode($referencia)
    . '&redirect-url='        . urlencode(WOMPI_REDIRECT_URL)
    . '&signature:integrity=' . $firma;

echo json_encode(['checkout_url' => $checkoutUrl]);
