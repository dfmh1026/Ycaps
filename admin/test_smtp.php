<?php
// SCRIPT DE PRUEBA — borra después de confirmar que el correo llega.
// https://www.ycapsgorras.com/admin/test_smtp.php

$rutaHostinger = dirname(__DIR__, 2) . '/ycaps_config.php';
$rutaLocal     = dirname(__DIR__)    . '/wompi/config.php';
if (file_exists($rutaHostinger))  require_once $rutaHostinger;
elseif (file_exists($rutaLocal))  require_once $rutaLocal;
else die('<pre style="color:red">Config no encontrado</pre>');

echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Test SMTP</title>';
echo '<style>body{font-family:monospace;max-width:700px;margin:30px auto;padding:0 20px}
.ok{color:#166534} .err{color:#991b1b} .cmd{color:#1d4ed8} .resp{color:#374151}
pre{background:#f1f5f9;padding:1rem;border-radius:8px;white-space:pre-wrap;font-size:.85rem}
h2{margin-top:1.5rem}</style></head><body>';
echo '<h1>Ycaps — Diagnóstico SMTP</h1>';

// ── Verificar constantes ──────────────────────────────────────────────────────
$ok = true;
foreach (['SMTP_HOST','SMTP_PORT','SMTP_USER','SMTP_PASS','TIENDA_EMAIL','TIENDA_NOMBRE'] as $c) {
    if (!defined($c)) {
        echo "<p class='err'>❌ Falta constante: <b>{$c}</b> — agrégala a ycaps_config.php</p>";
        $ok = false;
    }
}
if (!$ok) { echo '</body></html>'; exit; }

$host   = SMTP_HOST;
$port   = (int) SMTP_PORT;
$user   = SMTP_USER;
$pass   = SMTP_PASS;
$from   = TIENDA_EMAIL;
$nombre = TIENDA_NOMBRE;
$para   = TIENDA_EMAIL; // enviar a ti mismo

echo "<p class='ok'><b>Constantes OK</b></p>";
echo "<pre>";
echo "SMTP_HOST : {$host}\n";
echo "SMTP_PORT : {$port}\n";
echo "SMTP_USER : {$user}\n";
echo "SMTP_PASS : " . (strlen($pass) > 0 ? str_repeat('*', strlen($pass)) : '(vacía — ¡FALTA!)') . "\n";
echo "TIENDA_EMAIL : {$from}\n";
echo "</pre>";

if (strlen($pass) === 0) {
    echo "<p class='err'>❌ SMTP_PASS está vacía. Pon la contraseña del correo en ycaps_config.php</p></body></html>";
    exit;
}

// ── Conexión SSL ──────────────────────────────────────────────────────────────
echo '<h2>Conexión SSL</h2><pre>';
$ctx  = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
$sock = @stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, 12, STREAM_CLIENT_CONNECT, $ctx);

if (!$sock) {
    echo "<span class='err'>❌ No se pudo conectar a {$host}:{$port}\nError {$errno}: {$errstr}</span>";
    echo "</pre></body></html>"; exit;
}

stream_set_timeout($sock, 10);

$log  = [];
$leer = function() use ($sock, &$log) {
    $buf = '';
    while (true) {
        $line = fgets($sock, 512);
        if ($line === false) break;
        $buf .= $line;
        $log[] = ['S', rtrim($line)];
        if (strlen($line) >= 4 && $line[3] === ' ') break; // fin de respuesta multi-línea
    }
    return $buf;
};
$cmd = function(string $c) use ($sock, &$log) {
    $log[] = ['C', $c];
    fputs($sock, $c . "\r\n");
};
$codigo = function(string $resp): int {
    return (int) substr(trim($resp), 0, 3);
};

$fallo = false;
$pasos = [];

$greeting = $leer();
$pasos[] = ['greeting', $greeting, 220];

$cmd("EHLO {$host}");
$ehlo = $leer();
$pasos[] = ['EHLO', $ehlo, 250];

$cmd("AUTH LOGIN");
$r1 = $leer();
$pasos[] = ['AUTH LOGIN', $r1, 334];

$cmd(base64_encode($user));
$r2 = $leer();
$pasos[] = ['user b64', $r2, 334];

$cmd(base64_encode($pass));
$r3 = $leer();
$pasos[] = ['pass b64', $r3, 235]; // 235 = autenticado

$cmd("MAIL FROM:<{$from}>");
$r4 = $leer();
$pasos[] = ['MAIL FROM', $r4, 250];

$cmd("RCPT TO:<{$para}>");
$r5 = $leer();
$pasos[] = ['RCPT TO', $r5, 250];

$cmd("DATA");
$r6 = $leer();
$pasos[] = ['DATA', $r6, 354];

$asunto  = 'Prueba SMTP Ycaps — ' . date('H:i:s');
$cuerpo  = "Correo de prueba enviado vía SMTP autenticado.\nFecha: " . date('Y-m-d H:i:s');
$mensaje = "From: {$nombre} <{$from}>\r\n"
         . "To: {$para}\r\n"
         . "Subject: =?UTF-8?B?" . base64_encode($asunto) . "?=\r\n"
         . "MIME-Version: 1.0\r\n"
         . "Content-Type: text/plain; charset=UTF-8\r\n"
         . "Content-Transfer-Encoding: base64\r\n"
         . "\r\n"
         . chunk_split(base64_encode($cuerpo));

fputs($sock, $mensaje . "\r\n.\r\n");
$r7 = $leer();
$pasos[] = ['mensaje .', $r7, 250];

$cmd("QUIT");
fclose($sock);

// ── Mostrar log ───────────────────────────────────────────────────────────────
foreach ($log as [$dir, $linea]) {
    $cls = $dir === 'C' ? 'cmd' : 'resp';
    $pre = $dir === 'C' ? '→ SEND: ' : '← RECV: ';
    echo "<span class='{$cls}'>{$pre}" . htmlspecialchars($linea) . "</span>\n";
}
echo '</pre>';

// ── Resultado por paso ────────────────────────────────────────────────────────
echo '<h2>Resultado por paso</h2><ul>';
foreach ($pasos as [$nombre_p, $resp, $esperado]) {
    $c = $codigo($resp);
    if ($c === $esperado || ($nombre_p === 'greeting' && $c === 220)) {
        echo "<li class='ok'>✅ {$nombre_p} → {$c}</li>";
    } else {
        echo "<li class='err'>❌ {$nombre_p} → esperado {$esperado}, recibido {$c}: <b>" . htmlspecialchars(trim($resp)) . "</b></li>";
        $fallo = true;
    }
}
echo '</ul>';

if ($fallo) {
    echo "<p class='err'><b>El correo NO se envió.</b> Revisa el error marcado con ❌ arriba.</p>";
    if (strpos($r3 ?? '', '535') !== false || strpos($r3 ?? '', '334') !== false) {
        echo "<p class='err'>→ El error en el paso <b>pass b64</b> generalmente significa contraseña incorrecta.</p>";
    }
} else {
    echo "<p class='ok' style='font-size:1.1rem'><b>✅ Correo enviado correctamente.</b> Revisa la bandeja de entrada de <b>" . htmlspecialchars($para) . "</b></p>";
    echo "<p style='color:#64748b;font-size:.85rem'>Si llegó a la bandeja principal (no spam), la configuración SMTP es correcta. Borra este archivo.</p>";
}

echo '</body></html>';
