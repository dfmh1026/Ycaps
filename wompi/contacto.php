<?php
// Recibe el formulario de "Contacto" del sitio y envía el mensaje por correo a la tienda.

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/load_config.php';
require __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

$entrada = json_decode(file_get_contents('php://input'), true);
$entrada = is_array($entrada) ? $entrada : [];

$nombre   = trim((string) ($entrada['nombre']   ?? ''));
$email    = trim((string) ($entrada['email']    ?? ''));
$telefono = trim((string) ($entrada['telefono'] ?? ''));
$mensaje  = trim((string) ($entrada['mensaje']  ?? ''));

if ($nombre === '' || $mensaje === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Por favor completa tu nombre y el mensaje.']);
    exit;
}

// Igual que en el checkout: rechaza correos mal formados o con saltos de línea
// (que podrían usarse para inyectar cabeceras adicionales en el correo).
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'El correo electrónico no es válido.']);
    exit;
}

// Se guarda en la base de datos y se envía el correo de forma independiente:
// si uno de los dos falla, el otro sirve de respaldo y el mensaje no se pierde.
$guardadoEnDb  = false;
$correoEnviado = false;

try {
    $db = conectarDb();
    guardarMensajeContacto($db, $nombre, $email, $telefono, $mensaje);
    $guardadoEnDb = true;
} catch (Throwable $e) {
    error_log('Error guardando mensaje de contacto en la base de datos: ' . $e->getMessage());
}

try {
    require_once __DIR__ . '/mailer.php';
    enviarEmailContacto($nombre, $email, $telefono, $mensaje);
    $correoEnviado = true;
} catch (Throwable $e) {
    error_log('Error enviando email de contacto: ' . $e->getMessage());
}

if (!$guardadoEnDb && !$correoEnviado) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo enviar el mensaje. Intenta de nuevo en un momento.']);
    exit;
}

echo json_encode(['ok' => true]);
