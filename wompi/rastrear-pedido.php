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

$entrada  = json_decode(file_get_contents('php://input'), true);
$termino  = isset($entrada['referencia']) ? trim((string) $entrada['referencia']) : '';

if ($termino === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Ingresa tu número de guía o referencia.']);
    exit;
}

try {
    $db = conectarDb();

    // Buscar primero por número de guía, luego por referencia Wompi.
    // La referencia Wompi se normaliza aceptando con o sin prefijo "YCAPS-".
    $referenciaNorm = preg_match('/^YCAPS-/i', $termino)
        ? strtoupper($termino)
        : 'YCAPS-' . strtoupper($termino);

    $stmt = $db->prepare(
        'SELECT p.nombre, p.ciudad, p.total, p.estado, p.guia_envio,
                p.wompi_referencia, p.creado_en
         FROM pedidos p
         WHERE p.guia_envio = :guia
            OR p.wompi_referencia = :ref
         LIMIT 1'
    );
    $stmt->execute([':guia' => $termino, ':ref' => $referenciaNorm]);
    $pedido = $stmt->fetch();

    // Si no encontró por referencia normalizada, intenta con el término exacto
    if (!$pedido) {
        $stmt2 = $db->prepare(
            'SELECT p.nombre, p.ciudad, p.total, p.estado, p.guia_envio,
                    p.wompi_referencia, p.creado_en
             FROM pedidos p
             WHERE p.wompi_referencia = :ref
             LIMIT 1'
        );
        $stmt2->execute([':ref' => $termino]);
        $pedido = $stmt2->fetch();
    }

    if (!$pedido) {
        http_response_code(404);
        echo json_encode(['error' => 'No encontramos ningún pedido con ese número. Verifica que sea correcto.']);
        exit;
    }

    $referencia = $pedido['wompi_referencia'];

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
