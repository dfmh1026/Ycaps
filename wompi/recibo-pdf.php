<?php
// Genera y descarga el recibo en PDF de un pedido con pago confirmado.
// Se accede por la misma referencia que usa el cliente para rastrear su pedido.

require __DIR__ . '/load_config.php';
require __DIR__ . '/db.php';
require __DIR__ . '/pdf.php';

$referencia = isset($_GET['ref']) ? trim((string) $_GET['ref']) : '';

if ($referencia === '') {
    http_response_code(400);
    echo 'Falta la referencia del pedido.';
    exit;
}

try {
    $db = conectarDb();

    $stmt = $db->prepare('SELECT * FROM pedidos WHERE wompi_referencia = :ref LIMIT 1');
    $stmt->execute([':ref' => $referencia]);
    $pedido = $stmt->fetch();

    if (!$pedido) {
        http_response_code(404);
        echo 'Pedido no encontrado.';
        exit;
    }

    if ($pedido['estado'] !== 'aprobado') {
        http_response_code(403);
        echo 'El recibo solo está disponible para pedidos con pago confirmado.';
        exit;
    }

    $stmtItems = $db->prepare('SELECT nombre_producto, precio, cantidad FROM pedido_items WHERE pedido_id = :id');
    $stmtItems->execute([':id' => $pedido['id']]);
    $items = $stmtItems->fetchAll();

    $numeroRecibo  = obtenerOCrearNumeroRecibo($db, (int) $pedido['id']);
    $pdfContenido  = generarReciboPdf($pedido, $items, $numeroRecibo);
    $nombreArchivo = 'recibo-' . preg_replace('/[^A-Za-z0-9\-]/', '', $referencia) . '.pdf';

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    header('Content-Length: ' . strlen($pdfContenido));
    echo $pdfContenido;
} catch (Throwable $e) {
    error_log('Error generando recibo PDF: ' . $e->getMessage());
    http_response_code(500);
    echo 'Error al generar el recibo.';
}
