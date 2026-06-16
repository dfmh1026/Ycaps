<?php
// Carga el archivo de configuración desde fuera de public_html (inmune al deploy de git).
// Fallback a wompi/config.php para desarrollo local.

$rutaHostinger = dirname(__DIR__, 2) . '/ycaps_config.php';
$rutaLocal     = dirname(__DIR__)    . '/wompi/config.php';

if (file_exists($rutaHostinger)) {
    require_once $rutaHostinger;
} elseif (file_exists($rutaLocal)) {
    require_once $rutaLocal;
} else {
    die('<p style="font-family:sans-serif;padding:2rem">⚠️ Archivo de configuración no encontrado.<br>Crea <code>ycaps_config.php</code> fuera de <code>public_html</code>.</p>');
}
