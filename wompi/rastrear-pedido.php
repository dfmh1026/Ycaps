<?php
// Devuelve el estado de un pedido por número de referencia.
// Llamado por el modal de rastreo del frontend.

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/load_config.php';
require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

$entrada    = json_decode(file_get_contents('php://input'), true);
$referencia = isset($entrada['referencia']) ? trim((string) $entrada['referencia']) : '';

if ($referencia === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Ingresa tu número de referencia.']);
    exit;
}

// Normalizar: aceptar con o sin prefijo "YCAPS-"
if (!preg_match('/^YCAPS-/i', $referencia)) {
    $referencia = 'YCAPS-' . strtoupper($referencia);
} else {
    $referencia = strtoupper($referencia);
}

try {
    $db   = conectarDb();
    $stmt = $db->prepare(
        'SELECT p.nombre, p.ciudad, p.total, p.estado, p.guia_envio, p.creado_en
         FROM pedidos p
         WHERE p.wompi_referencia = :ref
         LIMIT 1'
    );
    $stmt->execute([':ref' => $referencia]);
    $pedido = $stmt->fetch();

    if (!$pedido) {
        http_response_code(404);
        echo json_encode(['error' => 'No encontramos ningún pedido con esa referencia. Verifica que sea correcta.']);
        exit;
    }

    // Obtener los ítems del pedido
    $stmtItems = $db->prepare(
        'SELECT nombre_producto, precio, cantidad
         FROM pedido_items
         WHERE pedido_id = (SELECT id FROM pedidos WHERE wompi_referencia = :ref LIMIT 1)'
    );
    $stmtItems->execute([':ref' => $referencia]);
    $itemsRaw = $stmtItems->fetchAll();

    $items = array_map(function ($i) {
        return $i['nombre_producto'] . ' x' . $i['cantidad'];
    }, $itemsRaw);

    $guia        = $pedido['guia_envio'] ?? '';
    $estadoTexto = [
        'pendiente'  => 'Pendiente de pago',
        'aprobado'   => $guia !== '' ? 'En camino — paquete despachado' : 'Pago confirmado — en preparación',
        'rechazado'  => 'Pago rechazado',
        'anulado'    => 'Anulado',
        'error'      => 'Error en el pago',
    ];

    echo json_encode([
        'referencia'  => $referencia,
        'nombre'      => $pedido['nombre'],
        'ciudad'      => $pedido['ciudad'],
        'total'       => (float) $pedido['total'],
        'estado'      => $pedido['estado'],
        'estadoTexto' => $estadoTexto[$pedido['estado']] ?? ucfirst($pedido['estado']),
        'fecha'       => $pedido['creado_en'],
        'items'       => $items,
        'guia'        => $guia,
    ]);

} catch (Throwable $e) {
    error_log('Error rastreando pedido: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error al consultar el pedido. Intenta de nuevo.']);
}
