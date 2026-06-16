<?php
// Genera la URL firmada del Checkout de Wompi a partir del carrito y los datos
// del comprador enviados por el frontend. Guarda el pedido en la base de datos.

header('Content-Type: application/json; charset=utf-8');

$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'Falta configurar wompi/config.php con las credenciales de Wompi.']);
    exit;
}
require $configFile;
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

// Procesar ítems y calcular total en centavos (Wompi usa centavos de COP)
$items         = [];
$totalCentavos = 0;
foreach ($entrada['items'] as $item) {
    $nombre   = isset($item['nombre'])   ? trim((string) $item['nombre'])   : '';
    $precio   = isset($item['precio'])   ? (float) $item['precio']          : 0;
    $cantidad = isset($item['cantidad']) ? (int)   $item['cantidad']        : 0;

    if ($nombre === '' || $precio <= 0 || $cantidad <= 0) {
        continue;
    }

    $items[] = [
        'title'      => $nombre,
        'unit_price' => $precio,
        'quantity'   => $cantidad,
    ];
    $totalCentavos += (int) round($precio * $cantidad * 100);
}

if (empty($items)) {
    http_response_code(400);
    echo json_encode(['error' => 'El carrito no tiene productos válidos.']);
    exit;
}

// Referencia única por pedido — se guarda en la DB y Wompi la devuelve en el webhook
$referencia = 'YCAPS-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));

// Firma de integridad: SHA256(referencia + monto_en_centavos + moneda + secreto_integridad)
// Wompi la verifica para garantizar que la URL no fue modificada en tránsito.
$firma = hash('sha256', $referencia . $totalCentavos . 'COP' . WOMPI_INTEGRITY_SECRET);

// Guardar el pedido ANTES de redirigir (estado inicial: "pendiente")
$total = $totalCentavos / 100;
try {
    $db = conectarDb();
    guardarPedido($db, $comprador, $items, $total, $referencia);
} catch (Throwable $e) {
    error_log('Error guardando pedido en la base de datos: ' . $e->getMessage());
    // No bloquear el pago si falla el guardado; el webhook puede actualizar después
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
