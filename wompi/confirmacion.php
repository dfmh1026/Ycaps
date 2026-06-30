<?php
// Página de destino a la que Wompi redirige al comprador después del pago.
// Verifica el estado real de la transacción llamando a la API de Wompi,
// actualiza el pedido en la DB (si aún no lo hizo el webhook) y envía
// el correo de confirmación si el pedido acaba de aprobarse aquí.

require __DIR__ . '/load_config.php';
require __DIR__ . '/db.php';

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

$estado     = '';
$referencia = '';
if ($respuesta !== false && $codigoHttp >= 200 && $codigoHttp < 300) {
    $data       = json_decode($respuesta, true);
    $estado     = $data['data']['status']    ?? '';
    $referencia = $data['data']['reference'] ?? '';
}

// Si el pago fue aprobado, intentar actualizar el pedido en la DB.
// Solo actualizamos si el estado actual es 'pendiente' — así no pisamos
// un estado que el webhook ya haya establecido, y evitamos enviar el
// correo de confirmación dos veces.
if ($estado === 'APPROVED' && $referencia !== '') {
    try {
        $db = conectarDb();

        $upd = $db->prepare(
            "UPDATE pedidos
             SET estado = 'aprobado', wompi_transaction_id = :tid
             WHERE wompi_referencia = :ref AND estado = 'pendiente'"
        );
        $upd->execute([':tid' => $transaccionId, ':ref' => $referencia]);

        // rowCount() > 0 significa que fuimos los primeros en marcar el pedido
        // como aprobado (el webhook aún no había llegado) → enviamos el correo.
        if ($upd->rowCount() > 0) {
            $stmtPedido = $db->prepare(
                'SELECT * FROM pedidos WHERE wompi_referencia = :ref LIMIT 1'
            );
            $stmtPedido->execute([':ref' => $referencia]);
            $pedido = $stmtPedido->fetch();

            if ($pedido) {
                registrarCambioEstado(
                    $db,
                    (int) $pedido['id'],
                    'pendiente',
                    'aprobado',
                    'confirmacion_fallback',
                    $transaccionId
                );
                // Decrementar stock (también lo hace el webhook si llega después,
                // pero GREATEST(0, stock - n) lo hace idempotente).
                $stmtItems = $db->prepare(
                    'SELECT nombre_producto, precio, cantidad FROM pedido_items WHERE pedido_id = :id'
                );
                $stmtItems->execute([':id' => $pedido['id']]);
                $items = $stmtItems->fetchAll();

                $stmtStock = $db->prepare(
                    'UPDATE productos SET stock = GREATEST(0, stock - :cantidad)
                     WHERE nombre = :nombre AND activo = 1'
                );
                foreach ($items as $item) {
                    $stmtStock->execute([
                        ':cantidad' => (int) $item['cantidad'],
                        ':nombre'   => $item['nombre_producto'],
                    ]);
                }

                require_once __DIR__ . '/mailer.php';
                require_once __DIR__ . '/pdf.php';

                $numeroRecibo = obtenerOCrearNumeroRecibo($db, (int) $pedido['id']);
                $pdfDatos     = generarReciboPdf($pedido, $items, $numeroRecibo);
                $pdfNombre    = 'recibo-' . preg_replace('/[^A-Za-z0-9\-]/', '', $referencia) . '.pdf';

                guardarReciboPdf($db, (int) $pedido['id'], $pdfDatos);
                enviarEmailPagoConfirmado($pedido, $referencia, $pdfDatos, $pdfNombre);
            }
        }
    } catch (Throwable $e) {
        error_log('Error en confirmacion.php al aprobar pedido: ' . $e->getMessage());
    }
}

// Pago rechazado/anulado/con error: actualizamos y avisamos solo si el webhook
// todavía no lo había hecho (mismo patrón de idempotencia que el caso APPROVED).
$estadoMapaRechazo = ['DECLINED' => 'rechazado', 'VOIDED' => 'anulado', 'ERROR' => 'error'];
if (isset($estadoMapaRechazo[$estado]) && $referencia !== '') {
    try {
        $db = conectarDb();
        $estadoInterno = $estadoMapaRechazo[$estado];

        $upd = $db->prepare(
            "UPDATE pedidos
             SET estado = :estado, wompi_transaction_id = :tid
             WHERE wompi_referencia = :ref AND estado = 'pendiente'"
        );
        $upd->execute([':estado' => $estadoInterno, ':tid' => $transaccionId, ':ref' => $referencia]);

        if ($upd->rowCount() > 0) {
            $stmtPedido = $db->prepare(
                'SELECT * FROM pedidos WHERE wompi_referencia = :ref LIMIT 1'
            );
            $stmtPedido->execute([':ref' => $referencia]);
            $pedido = $stmtPedido->fetch();

            if ($pedido) {
                registrarCambioEstado(
                    $db,
                    (int) $pedido['id'],
                    'pendiente',
                    $estadoInterno,
                    'confirmacion_fallback',
                    $transaccionId
                );

                require_once __DIR__ . '/mailer.php';
                enviarEmailPagoRechazado($pedido, $referencia, $estadoInterno);
            }
        }
    } catch (Throwable $e) {
        error_log('Error en confirmacion.php al rechazar pedido: ' . $e->getMessage());
    }
}

$refParam = $referencia !== '' ? '&ref=' . urlencode($referencia) : '';

switch ($estado) {
    case 'APPROVED':
        header('Location: ../gracias.html' . ($referencia !== '' ? '?ref=' . urlencode($referencia) : ''));
        break;
    case 'PENDING':
        header('Location: ../index.html?pago=pendiente' . $refParam);
        break;
    default:
        header('Location: ../index.html?pago=fallo');
}
exit;
