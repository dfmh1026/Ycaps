<?php
// Muestra el recibo PDF que se guardó en el pedido al confirmarse el pago
// (columna "recibo" de la tabla pedidos) — para trazabilidad de lo enviado.
require __DIR__ . '/auth.php';
require __DIR__ . '/load_config.php';

$pedidoId = (int) ($_GET['id'] ?? 0);

if ($pedidoId <= 0) {
    http_response_code(400);
    echo 'Falta el id del pedido.';
    exit;
}

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$stmt = $pdo->prepare('SELECT recibo, wompi_referencia FROM pedidos WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $pedidoId]);
$fila = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$fila) {
    http_response_code(404);
    echo 'Pedido no encontrado.';
    exit;
}

if ($fila['recibo'] === null) {
    http_response_code(404);
    echo 'Este pedido todavía no tiene un recibo PDF guardado (se genera automáticamente cuando se confirma el pago).';
    exit;
}

$nombreArchivo = 'recibo-' . preg_replace('/[^A-Za-z0-9\-]/', '', (string) $fila['wompi_referencia']) . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $nombreArchivo . '"');
header('Content-Length: ' . strlen($fila['recibo']));
echo $fila['recibo'];
