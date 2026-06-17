<?php
// Prueba de notificación Telegram — ELIMINA este archivo después de probar.
require __DIR__ . '/load_config.php';

if (!defined('TELEGRAM_BOT_TOKEN')) {
    die('❌ Falta TELEGRAM_BOT_TOKEN en ycaps_config.php');
}

// --- Paso 1: obtener updates para encontrar el chat_id correcto ---
$ch = curl_init('https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/getUpdates');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$raw  = curl_exec($ch);
$err  = curl_error($ch);
curl_close($ch);

if ($err) { die('❌ Error cURL: ' . htmlspecialchars($err)); }

$data = json_decode($raw, true);

if (empty($data['result'])) {
    echo '⚠️ El bot no tiene mensajes aún.<br>';
    echo 'Ve a Telegram, abre tu bot y escribe cualquier mensaje (ej: <b>hola</b>), luego recarga esta página.';
    exit;
}

// Tomar el chat_id del último mensaje recibido
$ultimo  = end($data['result']);
$chatId  = $ultimo['message']['chat']['id'] ?? null;
$nombre  = $ultimo['message']['chat']['first_name'] ?? '';

if (!$chatId) {
    echo '❌ No se pudo leer el chat_id. Respuesta raw:<br><pre>' . htmlspecialchars($raw) . '</pre>';
    exit;
}

echo "✅ Chat ID detectado: <strong>{$chatId}</strong> ({$nombre})<br><br>";
echo "Agrega esto a tu <code>ycaps_config.php</code>:<br>";
echo "<pre>define('TELEGRAM_CHAT_ID', '{$chatId}');</pre><br>";

// --- Paso 2: enviar mensaje de prueba al chat_id detectado ---
$ch2 = curl_init('https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage');
curl_setopt_array($ch2, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode([
        'chat_id' => $chatId,
        'text'    => "✅ Prueba exitosa — Ycaps\nLas alertas de pedidos están funcionando correctamente.",
    ]),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$resp2 = curl_exec($ch2);
curl_close($ch2);

$json2 = json_decode($resp2, true);
if ($json2 && $json2['ok']) {
    echo '📲 Mensaje de prueba enviado — revisa tu Telegram ahora.';
} else {
    echo '❌ No se pudo enviar: ' . htmlspecialchars($resp2);
}
