<?php
// Recibe las notificaciones de Mercado Pago y actualiza el estado del pedido
// en la base de datos. Configura esta URL como "notification_url" (ya se
// envía automáticamente desde crear-preferencia.php).

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/config.php';
require __DIR__ . '/db.php';

$entrada = json_decode(file_get_contents('php://input'), true) ?? [];

// Mercado Pago también puede enviar el id del pago como query string.
$tipo = $entrada['type'] ?? $_GET['type'] ?? '';
$paymentId = $entrada['data']['id'] ?? $_GET['id'] ?? null;

if ($tipo !== 'payment' || !$paymentId) {
    http_response_code(200);
    echo json_encode(['ok' => true, 'mensaje' => 'Notificación ignorada.']);
    exit;
}

// Consultar el detalle del pago en Mercado Pago.
$ch = curl_init('https://api.mercadopago.com/v1/payments/' . urlencode((string) $paymentId));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . MP_ACCESS_TOKEN],
    CURLOPT_TIMEOUT => 15,
]);
$respuesta = curl_exec($ch);
$codigoHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($respuesta === false || $codigoHttp < 200 || $codigoHttp >= 300) {
    http_response_code(502);
    echo json_encode(['error' => 'No se pudo consultar el pago en Mercado Pago.']);
    exit;
}

$pago = json_decode($respuesta, true);
$preferenceId = $pago['order']['id'] ?? ($pago['preference_id'] ?? null);
$estado = $pago['status'] ?? 'desconocido';

if (!$preferenceId) {
    http_response_code(200);
    echo json_encode(['ok' => true, 'mensaje' => 'Pago sin preferencia asociada.']);
    exit;
}

try {
    $db = conectarDb();
    $stmt = $db->prepare(
        'UPDATE pedidos SET estado = :estado, mp_payment_id = :payment_id
         WHERE mp_preference_id = :preference_id'
    );
    $stmt->execute([
        ':estado' => $estado,
        ':payment_id' => $paymentId,
        ':preference_id' => $preferenceId,
    ]);

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    error_log('Error actualizando pedido desde webhook: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno al actualizar el pedido.']);
}
