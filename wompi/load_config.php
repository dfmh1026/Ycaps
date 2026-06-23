<?php
// Busca el archivo de configuración en el directorio padre de public_html
// (fuera del alcance del deploy de git en Hostinger).
// En desarrollo local usa wompi/config.php como fallback.

date_default_timezone_set('America/Bogota');

$rutaHostinger = dirname(__DIR__, 2) . '/ycaps_config.php';
$rutaLocal     = __DIR__ . '/config.php';

if (file_exists($rutaHostinger)) {
    require $rutaHostinger;
} elseif (file_exists($rutaLocal)) {
    require $rutaLocal;
} else {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Archivo de configuración no encontrado.']);
    exit;
}
