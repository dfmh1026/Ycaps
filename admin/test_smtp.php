<?php
// SCRIPT DE PRUEBA — borra este archivo después de confirmar que el correo llega.
// Accede a: https://www.ycapsgorras.com/admin/test_smtp.php
// (no requiere login porque es una prueba directa del SMTP)

$rutaHostinger = dirname(__DIR__, 2) . '/ycaps_config.php';
$rutaLocal     = dirname(__DIR__)    . '/wompi/config.php';
if (file_exists($rutaHostinger))     require_once $rutaHostinger;
elseif (file_exists($rutaLocal))     require_once $rutaLocal;
else die('<p style="color:red">Config no encontrado</p>');

require_once __DIR__ . '/../wompi/mailer.php';

$errores = [];

// Verificar constantes
foreach (['SMTP_HOST', 'SMTP_PORT', 'SMTP_USER', 'SMTP_PASS', 'TIENDA_EMAIL', 'TIENDA_NOMBRE'] as $c) {
    if (!defined($c)) $errores[] = "Falta constante: {$c}";
}

if ($errores) {
    echo '<h2 style="color:red">Faltan constantes en ycaps_config.php:</h2><ul>';
    foreach ($errores as $e) echo "<li>{$e}</li>";
    echo '</ul>';
    echo '<p>Agrega las líneas SMTP_* al archivo <code>ycaps_config.php</code> (ver config.example.php).</p>';
    exit;
}

// Intentar conexión SMTP
$host = SMTP_HOST;
$port = (int) SMTP_PORT;
$ctx  = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
$sock = @stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);

if (!$sock) {
    echo "<h2 style='color:red'>❌ No se pudo conectar al SMTP</h2>";
    echo "<p>Host: <b>{$host}:{$port}</b></p>";
    echo "<p>Error: {$errstr} (código {$errno})</p>";
    echo "<p>Verifica que las constantes SMTP_HOST y SMTP_PORT sean correctas.</p>";
    exit;
}
fclose($sock);

// Enviar correo de prueba
$destino = TIENDA_EMAIL;
_smtpEnviar($destino, 'Prueba SMTP — Ycaps', "Este es un correo de prueba enviado via SMTP autenticado.\n\nSi recibes esto en tu bandeja de entrada (no spam), el SMTP está configurado correctamente.\n\nFecha: " . date('Y-m-d H:i:s'));

echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Test SMTP</title>';
echo '<style>body{font-family:sans-serif;max-width:600px;margin:40px auto;padding:0 20px} .ok{color:#166534;background:#dcfce7;padding:1rem;border-radius:8px} .info{background:#f1f5f9;padding:1rem;border-radius:8px;margin-top:1rem;font-size:.9rem}</style></head><body>';
echo '<h1>Ycaps — Test SMTP</h1>';
echo '<div class="ok"><strong>✅ Correo enviado via SMTP.</strong><br>Revisa la bandeja de entrada de <b>' . htmlspecialchars($destino) . '</b>.<br>Si llegó a la bandeja principal (no spam), todo está correcto.</div>';
echo '<div class="info">';
echo '<b>Configuración usada:</b><br>';
echo 'Host: ' . htmlspecialchars(SMTP_HOST) . ':' . SMTP_PORT . '<br>';
echo 'Usuario: ' . htmlspecialchars(SMTP_USER) . '<br>';
echo 'Desde: ' . htmlspecialchars(TIENDA_EMAIL) . '<br>';
echo '</div>';
echo '<p style="margin-top:1.5rem;color:#64748b;font-size:.85rem">⚠️ Borra este archivo después de confirmar que el correo llegó: <code>admin/test_smtp.php</code></p>';
echo '</body></html>';
