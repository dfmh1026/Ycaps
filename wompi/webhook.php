<?php
// Recibe las notificaciones de Wompi y actualiza el estado del pedido en la DB.
// Configura esta URL en el dashboard de Wompi: Desarrolladores > Webhooks.

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/load_config.php';
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
    $db = conectarDb();

    // Capturar el estado actual antes de sobrescribirlo (para el historial)
    $stmtActual = $db->prepare(
        'SELECT id, estado FROM pedidos WHERE wompi_referencia = :ref LIMIT 1'
    );
    $stmtActual->execute([':ref' => $referencia]);
    $pedidoActual = $stmtActual->fetch();

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

    if ($pedidoActual && $pedidoActual['estado'] !== $estadoInterno) {
        registrarCambioEstado(
            $db,
            (int) $pedidoActual['id'],
            $pedidoActual['estado'],
            $estadoInterno,
            'webhook',
            $transaccionId
        );
    }

    // Acciones al aprobar el pago
    if ($estado === 'APPROVED') {
        // Obtener datos completos del pedido (necesarios también para el recibo PDF)
        $stmtPedido = $db->prepare(
            'SELECT * FROM pedidos WHERE wompi_referencia = :ref LIMIT 1'
        );
        $stmtPedido->execute([':ref' => $referencia]);
        $pedido = $stmtPedido->fetch();

        if ($pedido) {
            // Decrementar stock de cada producto comprado
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

            // Email de confirmación al cliente, con recibo PDF adjunto a la alerta de la tienda
            try {
                require_once __DIR__ . '/mailer.php';
                require_once __DIR__ . '/pdf.php';

                $numeroRecibo = obtenerOCrearNumeroRecibo($db, (int) $pedido['id']);
                $pdfDatos     = generarReciboPdf($pedido, $items, $numeroRecibo);
                $pdfNombre    = 'recibo-' . preg_replace('/[^A-Za-z0-9\-]/', '', $referencia) . '.pdf';

                enviarEmailPagoConfirmado($pedido, $referencia, $pdfDatos, $pdfNombre);
            } catch (Throwable $e) {
                error_log('Error enviando email de pago confirmado: ' . $e->getMessage());
            }
        }
    } elseif (in_array($estado, ['DECLINED', 'VOIDED', 'ERROR'], true)
        && $pedidoActual && $pedidoActual['estado'] !== $estadoInterno) {
        // Avisar de pago rechazado/anulado/con error solo cuando el estado realmente cambió
        try {
            $stmtPedido = $db->prepare(
                'SELECT * FROM pedidos WHERE wompi_referencia = :ref LIMIT 1'
            );
            $stmtPedido->execute([':ref' => $referencia]);
            $pedido = $stmtPedido->fetch();

            if ($pedido) {
                require_once __DIR__ . '/mailer.php';
                enviarEmailPagoRechazado($pedido, $referencia, $estadoInterno);
            }
        } catch (Throwable $e) {
            error_log('Error enviando email de pago rechazado: ' . $e->getMessage());
        }
    }

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    error_log('Error actualizando pedido desde webhook Wompi: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno al actualizar el pedido.']);
}
