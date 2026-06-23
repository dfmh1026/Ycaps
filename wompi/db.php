<?php
// Conexión PDO a la base de datos MySQL.
// Requiere las constantes DB_HOST, DB_NAME, DB_USER, DB_PASS definidas en config.php.

function conectarDb(): PDO
{
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '-05:00'",
    ]);
}

// Guarda un pedido con sus ítems en una transacción atómica.
// Devuelve el id del pedido creado.
function guardarPedido(PDO $db, array $comprador, array $items, float $total, string $wompiReferencia, string $metodoPago = 'wompi'): int
{
    $db->beginTransaction();

    try {
        $stmt = $db->prepare(
            'INSERT INTO pedidos
                (nombre, cedula, email, telefono, direccion, ciudad, departamento, total, metodo_pago, estado, wompi_referencia)
             VALUES
                (:nombre, :cedula, :email, :telefono, :direccion, :ciudad, :departamento, :total, :metodo_pago, :estado, :wompi_referencia)'
        );
        $stmt->execute([
            ':nombre'           => $comprador['nombre']       ?? '',
            ':cedula'           => $comprador['cedula']       ?? '',
            ':email'            => $comprador['email']        ?? '',
            ':telefono'         => $comprador['telefono']     ?? '',
            ':direccion'        => $comprador['direccion']    ?? '',
            ':ciudad'           => $comprador['ciudad']       ?? '',
            ':departamento'     => $comprador['departamento'] ?? '',
            ':total'            => $total,
            ':metodo_pago'      => $metodoPago,
            ':estado'           => 'pendiente',
            ':wompi_referencia' => $wompiReferencia,
        ]);

        $pedidoId = (int) $db->lastInsertId();

        $stmtItem = $db->prepare(
            'INSERT INTO pedido_items (pedido_id, nombre_producto, precio, cantidad)
             VALUES (:pedido_id, :nombre_producto, :precio, :cantidad)'
        );

        foreach ($items as $item) {
            $stmtItem->execute([
                ':pedido_id'       => $pedidoId,
                ':nombre_producto' => $item['title'],
                ':precio'          => $item['unit_price'],
                ':cantidad'        => $item['quantity'],
            ]);
        }

        registrarCambioEstado($db, $pedidoId, null, 'pendiente', 'creacion', $wompiReferencia);

        $db->commit();
        return $pedidoId;
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

// Registra una transición de estado en el historial del pedido (trazabilidad).
function registrarCambioEstado(
    PDO $db,
    int $pedidoId,
    ?string $estadoAnterior,
    string $estadoNuevo,
    string $origen,
    ?string $detalle = null
): void {
    $stmt = $db->prepare(
        'INSERT INTO pedido_estado_historial
            (pedido_id, estado_anterior, estado_nuevo, origen, detalle)
         VALUES
            (:pedido_id, :estado_anterior, :estado_nuevo, :origen, :detalle)'
    );
    $stmt->execute([
        ':pedido_id'       => $pedidoId,
        ':estado_anterior' => $estadoAnterior,
        ':estado_nuevo'    => $estadoNuevo,
        ':origen'          => $origen,
        ':detalle'         => $detalle,
    ]);
}

// Devuelve el número consecutivo de recibo de un pedido, creándolo si no existe.
function obtenerOCrearNumeroRecibo(PDO $db, int $pedidoId): string
{
    $stmt = $db->prepare('SELECT id FROM recibos WHERE pedido_id = :pid LIMIT 1');
    $stmt->execute([':pid' => $pedidoId]);
    $reciboId = $stmt->fetchColumn();

    if (!$reciboId) {
        $db->prepare('INSERT INTO recibos (pedido_id) VALUES (:pid)')->execute([':pid' => $pedidoId]);
        $reciboId = (int) $db->lastInsertId();
    }

    return 'YCAPS-REC-' . str_pad((string) $reciboId, 6, '0', STR_PAD_LEFT);
}

// Guarda en el pedido el mismo PDF que se envió por correo al confirmarse el
// pago. Así queda trazabilidad de exactamente qué recibo se mandó, y se puede
// consultar después desde el panel admin sin tener que regenerarlo.
function guardarReciboPdf(PDO $db, int $pedidoId, string $pdfDatos): void
{
    $stmt = $db->prepare('UPDATE pedidos SET recibo = :recibo WHERE id = :id');
    $stmt->bindParam(':recibo', $pdfDatos, PDO::PARAM_LOB);
    $stmt->bindParam(':id', $pedidoId, PDO::PARAM_INT);
    $stmt->execute();
}

// Guarda un mensaje del formulario de "Contacto" del sitio — respaldo en base
// de datos por si el envío del correo llegara a fallar.
function guardarMensajeContacto(PDO $db, string $nombre, string $email, string $telefono, string $mensaje): int
{
    $stmt = $db->prepare(
        'INSERT INTO mensajes_contacto (nombre, email, telefono, mensaje)
         VALUES (:nombre, :email, :telefono, :mensaje)'
    );
    $stmt->execute([
        ':nombre'   => $nombre,
        ':email'    => $email,
        ':telefono' => $telefono,
        ':mensaje'  => $mensaje,
    ]);

    return (int) $db->lastInsertId();
}
