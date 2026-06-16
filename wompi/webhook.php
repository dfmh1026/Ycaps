<?php
// Recibe las notificaciones de Wompi y actualiza el estado del pedido en la DB.
// Configura esta URL en el dashboard de Wompi: Desarrolladores > Webhooks.

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/config.php';
require __DIR__ . '/db.php';

$cuerpo = file_get_contents('php://input');
$datos  = json_decode($cuerpo, true);

// Solo procesamos el evento "transaction.updated"
if (!is_array($datos) || ($datos['event'] ?? '') !== 'transaction.updated') {
    http_response_code(200);
    echo json_encode(['ok' => true, 'mensaje' => 'Evento ignorado.']);
    exit;
}

$firma       = $datos['signature']              ?? [];
$propiedades = $firma['properties']             ?? [];
$checksumRec = $firma['checksum']               ?? '';
$transaccion = $datos['data']['transaction']    ?? [];

if (empty($propiedades) || $checksumRec === '' || empty($transaccion)) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos de firma incompletos.']);
    exit;
}

// Verificar la firma del webhook:
// SHA256(valor_propiedad1 + valor_propiedad2 + ... + secreto_eventos)
// Las propiedades son rutas tipo "transaction.id", "transaction.status", etc.
$cadena = '';
foreach ($propiedades as $propiedad) {
    $partes  = explode('.', $propiedad, 2);
    $cadena .= (string) ($transaccion[$partes[1]] ?? '');
}
$checksumEsp = hash('sha256', $cadena . WOMPI_EVENTS_SECRET);

if (!hash_equals($checksumEsp, $checksumRec)) {
    http_response_code(401);
    echo json_encode(['error' => 'Firma de webhook inválida.']);
    exit;
}

$referencia    = $transaccion['reference'] ?? '';
$estado        = $transaccion['status']    ?? '';
$transaccionId = $transaccion['id']        ?? '';

if ($referencia === '' || $estado === '') {
    http_response_code(200);
    echo json_encode(['ok' => true]);
    exit;
}

// Mapear estado de Wompi al estado interno de la base de datos
$estadoMapa = [
    'APPROVED' => 'aprobado',
    'DECLINED' => 'rechazado',
    'VOIDED'   => 'anulado',
    'ERROR'    => 'error',
    'PENDING'  => 'pendiente',
];
$estadoInterno = $estadoMapa[$estado] ?? strtolower($estado);

try {
    $db   = conectarDb();
    $stmt = $db->prepare(
        'UPDATE pedidos
         SET estado = :estado, wompi_transaction_id = :transaction_id
         WHERE wompi_referencia = :referencia'
    );
    $stmt->execute([
        ':estado'         => $estadoInterno,
        ':transaction_id' => $transaccionId,
        ':referencia'     => $referencia,
    ]);

    // Enviar email de pago confirmado solo cuando el pago fue aprobado
    if ($estado === 'APPROVED') {
        $stmtPedido = $db->prepare(
            'SELECT nombre, email, total FROM pedidos WHERE wompi_referencia = :ref LIMIT 1'
        );
        $stmtPedido->execute([':ref' => $referencia]);
        $pedido = $stmtPedido->fetch();

        if ($pedido) {
            try {
                require_once __DIR__ . '/mailer.php';
                enviarEmailPagoConfirmado(
                    $pedido['email'],
                    $pedido['nombre'],
                    $referencia,
                    (float) $pedido['total']
                );
            } catch (Throwable $e) {
                error_log('Error enviando email de pago confirmado: ' . $e->getMessage());
            }
        }
    }

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    error_log('Error actualizando pedido desde webhook Wompi: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno al actualizar el pedido.']);
}
