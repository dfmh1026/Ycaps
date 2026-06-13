<?php
// Crea una preferencia de pago de Mercado Pago (Checkout Pro) a partir del
// carrito y los datos de envío enviados desde script.js.

header('Content-Type: application/json; charset=utf-8');

$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'Falta configurar config.php con las credenciales de Mercado Pago.']);
    exit;
}
require $configFile;

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

$itemsPreferencia = [];
foreach ($entrada['items'] as $item) {
    $nombre = isset($item['nombre']) ? trim((string) $item['nombre']) : '';
    $precio = isset($item['precio']) ? (float) $item['precio'] : 0;
    $cantidad = isset($item['cantidad']) ? (int) $item['cantidad'] : 0;

    if ($nombre === '' || $precio <= 0 || $cantidad <= 0) {
        continue;
    }

    $itemsPreferencia[] = [
        'title' => $nombre,
        'quantity' => $cantidad,
        'unit_price' => $precio,
        'currency_id' => 'COP',
    ];
}

if (empty($itemsPreferencia)) {
    http_response_code(400);
    echo json_encode(['error' => 'El carrito no tiene productos válidos.']);
    exit;
}

$preferencia = [
    'items' => $itemsPreferencia,
    'payer' => [
        'name' => trim((string) ($comprador['nombre'] ?? '')),
        'email' => trim((string) ($comprador['email'] ?? '')),
        'phone' => [
            'number' => trim((string) ($comprador['telefono'] ?? '')),
        ],
        'address' => [
            'street_name' => trim((string) ($comprador['direccion'] ?? '')),
        ],
    ],
    'back_urls' => [
        'success' => MP_BACK_URL_SUCCESS,
        'failure' => MP_BACK_URL_FAILURE,
        'pending' => MP_BACK_URL_PENDING,
    ],
    'auto_return' => 'approved',
];

$ch = curl_init('https://api.mercadopago.com/checkout/preferences');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . MP_ACCESS_TOKEN,
    ],
    CURLOPT_POSTFIELDS => json_encode($preferencia),
    CURLOPT_TIMEOUT => 15,
]);

$respuesta = curl_exec($ch);
$codigoHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$errorCurl = curl_error($ch);
curl_close($ch);

if ($respuesta === false) {
    http_response_code(502);
    echo json_encode(['error' => 'No se pudo conectar con Mercado Pago: ' . $errorCurl]);
    exit;
}

$resultado = json_decode($respuesta, true);

if ($codigoHttp >= 200 && $codigoHttp < 300 && !empty($resultado['init_point'])) {
    echo json_encode(['init_point' => $resultado['init_point']]);
    exit;
}

http_response_code(502);
echo json_encode([
    'error' => 'Mercado Pago rechazó la solicitud.',
    'detalle' => $resultado['message'] ?? $respuesta,
]);
