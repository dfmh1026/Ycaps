<?php
// Página de destino a la que Wompi redirige al comprador después del pago.
// Verifica el estado real de la transacción llamando a la API de Wompi y
// redirige al inicio con el parámetro ?pago=exito|pendiente|fallo.

require __DIR__ . '/config.php';

// Wompi envía el id de la transacción como query param "id"
$transaccionId = isset($_GET['id']) ? preg_replace('/[^a-zA-Z0-9\-_]/', '', $_GET['id']) : '';

if ($transaccionId === '') {
    header('Location: ../index.html?pago=fallo');
    exit;
}

$apiBase = (WOMPI_ENV === 'production')
    ? 'https://production.wompi.co/v1'
    : 'https://sandbox.wompi.co/v1';

$ch = curl_init($apiBase . '/transactions/' . urlencode($transaccionId));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . WOMPI_PRIVATE_KEY],
    CURLOPT_TIMEOUT        => 15,
]);
$respuesta  = curl_exec($ch);
$codigoHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$estado = '';
if ($respuesta !== false && $codigoHttp >= 200 && $codigoHttp < 300) {
    $data   = json_decode($respuesta, true);
    $estado = $data['data']['status'] ?? '';
}

switch ($estado) {
    case 'APPROVED':
        header('Location: ../index.html?pago=exito');
        break;
    case 'PENDING':
        header('Location: ../index.html?pago=pendiente');
        break;
    default:
        header('Location: ../index.html?pago=fallo');
}
exit;
