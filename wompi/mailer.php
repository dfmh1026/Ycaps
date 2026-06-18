<?php
// Envío de correos via SMTP autenticado — evita spam vs mail() sin autenticación.
// Requiere SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, TIENDA_EMAIL, TIENDA_NOMBRE.
// WhatsApp via CallMeBot: requiere WHATSAPP_APIKEY en ycaps_config.php.

function _telegramNotificar(string $mensaje): void
{
    if (!defined('TELEGRAM_BOT_TOKEN') || !defined('TELEGRAM_CHAT_ID')) return;
    if (TELEGRAM_BOT_TOKEN === '' || TELEGRAM_CHAT_ID === '') return;
    if (!function_exists('curl_init')) return;

    $ch = curl_init('https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['chat_id' => TELEGRAM_CHAT_ID, 'text' => $mensaje]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function _smtpEnviar(string $para, string $asunto, string $cuerpo): void
{
    $host = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.hostinger.com';
    $port = defined('SMTP_PORT') ? (int) SMTP_PORT : 465;
    $user = defined('SMTP_USER') ? SMTP_USER : TIENDA_EMAIL;
    $pass = defined('SMTP_PASS') ? SMTP_PASS : '';
    $from = TIENDA_EMAIL;
    $nombre = TIENDA_NOMBRE;

    // Conexión SSL directa (puerto 465)
    $ctx    = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $socket = @stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);

    if (!$socket) {
        // Fallback a mail() si SMTP no conecta
        $headers = "From: {$nombre} <{$from}>\r\nReply-To: {$from}\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";
        @mail($para, '=?UTF-8?B?' . base64_encode($asunto) . '?=', $cuerpo, $headers, '-f' . $from);
        return;
    }

    $leer = function() use ($socket) { return fgets($socket, 512); };
    $cmd  = function(string $c) use ($socket) { fputs($socket, $c . "\r\n"); };

    $leer(); // greeting

    $cmd("EHLO {$host}");
    while ($l = $leer()) { if (substr($l, 3, 1) === ' ') break; }

    $cmd("AUTH LOGIN");
    $leer();
    $cmd(base64_encode($user));
    $leer();
    $cmd(base64_encode($pass));
    $leer();

    $cmd("MAIL FROM:<{$from}>");
    $leer();

    $cmd("RCPT TO:<{$para}>");
    $leer();

    $cmd("DATA");
    $leer();

    $subjectB64 = '=?UTF-8?B?' . base64_encode($asunto) . '?=';
    $cuerpoB64  = chunk_split(base64_encode($cuerpo));

    $mensaje = "From: {$nombre} <{$from}>\r\n"
             . "To: {$para}\r\n"
             . "Subject: {$subjectB64}\r\n"
             . "MIME-Version: 1.0\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n"
             . "Content-Transfer-Encoding: base64\r\n"
             . "\r\n"
             . $cuerpoB64;

    $cmd($mensaje . "\r\n.");
    $leer();

    $cmd("QUIT");
    fclose($socket);
}

function _formatearPrecio(float $precio): string
{
    return '$' . number_format($precio, 0, ',', '.') . ' COP';
}

function _listadoItems(array $items): string
{
    $lineas = [];
    foreach ($items as $item) {
        $nombre   = $item['title']          ?? $item['nombre_producto'] ?? 'Producto';
        $precio   = (float) ($item['unit_price'] ?? $item['precio']    ?? 0);
        $cantidad = (int)   ($item['quantity']   ?? $item['cantidad']   ?? 1);
        $subtotal = $precio * $cantidad;
        $lineas[] = "  - {$nombre} x{$cantidad} = " . _formatearPrecio($subtotal);
    }
    return implode("\n", $lineas);
}

function enviarEmailNuevoPedido(array $comprador, array $items, float $total, string $referencia): void
{
    $asunto    = "Pedido recibido — Ycaps #{$referencia}";
    $itemsTxt  = _listadoItems($items);
    $totalFmt  = _formatearPrecio($total);
    $nombre    = $comprador['nombre']    ?? '';
    $email     = $comprador['email']     ?? '';
    $telefono  = $comprador['telefono']  ?? '';
    $direccion = $comprador['direccion'] ?? '';
    $ciudad    = $comprador['ciudad']    ?? '';

    $cuerpoCliente =
        "Hola {$nombre},\n\n"
        . "Hemos recibido tu pedido en Ycaps. Aquí están los detalles:\n\n"
        . "Referencia: {$referencia}\n"
        . "Productos:\n{$itemsTxt}\n"
        . "Total: {$totalFmt}\n"
        . "Estado: Pendiente de pago\n\n"
        . "Guarda tu número de referencia para rastrear tu pedido en www.ycapsgorras.com\n\n"
        . "Gracias por confiar en Ycaps.\n"
        . "— El equipo de Ycaps | www.ycapsgorras.com";

    $cuerpoTienda =
        "¡Nuevo pedido recibido!\n\n"
        . "Referencia:  {$referencia}\n"
        . "Cliente:     {$nombre}\n"
        . "Email:       {$email}\n"
        . "Teléfono:    {$telefono}\n"
        . "Dirección:   {$direccion}, {$ciudad}\n\n"
        . "Productos:\n{$itemsTxt}\n"
        . "Total: {$totalFmt}\n"
        . "Estado: Pendiente de pago\n\n"
        . "Ingresa al panel admin para ver el detalle: https://www.ycapsgorras.com/admin/";

    if ($email !== '') {
        _smtpEnviar($email, $asunto, $cuerpoCliente);
    }
    _smtpEnviar(TIENDA_EMAIL, 'Nuevo pedido — ' . $asunto, $cuerpoTienda);
}

function enviarEmailGuiaEnvio(string $emailCliente, string $nombreCliente, string $referencia, string $guia): void
{
    $asunto = "Tu pedido está en camino — Ycaps #{$referencia}";

    $cuerpo =
        "Hola {$nombreCliente},\n\n"
        . "¡Tu pedido ya fue despachado! Aquí tienes el número de guía para rastrearlo:\n\n"
        . "  Número de guía: {$guia}\n\n"
        . "Puedes rastrear tu paquete en el sitio web de la transportadora con ese número.\n\n"
        . "Si tienes alguna duda, escríbenos por WhatsApp:\n"
        . "  https://wa.me/573004710483\n\n"
        . "Gracias por tu compra.\n"
        . "— El equipo de Ycaps | www.ycapsgorras.com";

    _smtpEnviar($emailCliente, $asunto, $cuerpo);

    _smtpEnviar(
        TIENDA_EMAIL,
        "Guía enviada al cliente — Pedido #{$referencia}",
        "Guía {$guia} notificada a {$nombreCliente} ({$emailCliente})."
    );
}

function enviarEmailPagoConfirmado(string $emailCliente, string $nombreCliente, string $referencia, float $total): void
{
    $asunto   = "¡Pago confirmado! — Ycaps #{$referencia}";
    $totalFmt = _formatearPrecio($total);

    $cuerpoCliente =
        "Hola {$nombreCliente},\n\n"
        . "¡Tu pago ha sido confirmado! Pronto te contactaremos por WhatsApp para coordinar el envío.\n\n"
        . "Referencia: {$referencia}\n"
        . "Total pagado: {$totalFmt}\n\n"
        . "Puedes rastrear tu pedido en:\n"
        . "www.ycapsgorras.com — sección \"Rastrear pedido\"\n\n"
        . "Gracias por tu compra.\n"
        . "— El equipo de Ycaps | www.ycapsgorras.com";

    $cuerpoTienda =
        "¡Pago confirmado por Wompi!\n\n"
        . "Referencia: {$referencia}\n"
        . "Cliente:    {$nombreCliente}\n"
        . "Email:      {$emailCliente}\n"
        . "Total:      {$totalFmt}\n\n"
        . "Alista el pedido para envío.\n"
        . "Panel admin: https://www.ycapsgorras.com/admin/";

    if ($emailCliente !== '') {
        _smtpEnviar($emailCliente, $asunto, $cuerpoCliente);
    }
    _smtpEnviar(TIENDA_EMAIL, 'Pago confirmado — ' . $asunto, $cuerpoTienda);

    _telegramNotificar(
        "✅ NUEVO PEDIDO PAGADO\n"
        . "Cliente: {$nombreCliente}\n"
        . "Ref: {$referencia}\n"
        . "Total: {$totalFmt}\n"
        . "Panel: https://www.ycapsgorras.com/admin/"
    );
}
