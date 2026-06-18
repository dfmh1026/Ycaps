<?php
// Envío de correos via SMTP autenticado — evita spam vs mail() sin autenticación.
// Requiere SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, TIENDA_EMAIL, TIENDA_NOMBRE.
// Telegram: requiere TELEGRAM_BOT_TOKEN y TELEGRAM_CHAT_ID en ycaps_config.php.

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

// $adjunto opcional: ['nombre' => 'recibo.pdf', 'datos' => $bytesBinarios, 'tipo' => 'application/pdf']
// $cuerpoHtml debe venir ya envuelto con _plantillaEmail().
function _smtpEnviar(string $para, string $asunto, string $cuerpoHtml, ?array $adjunto = null): void
{
    // El destinatario puede venir de datos enviados por el cliente (formulario de
    // compra). Sin esta validación, un correo malicioso con salto de línea podría
    // inyectar cabeceras SMTP adicionales (ej. Bcc a terceros) o comandos al
    // servidor de correo. filter_var con FILTER_VALIDATE_EMAIL rechaza cualquier
    // valor que no sea un correo válido, incluyendo los que llevan \r o \n.
    if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
        error_log('_smtpEnviar: destinatario inválido, envío bloqueado.');
        return;
    }

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
        // Fallback a mail() si SMTP no conecta (sin adjunto, mail() multipart es más limitado)
        $headers = "From: {$nombre} <{$from}>\r\nReply-To: {$from}\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
        @mail($para, '=?UTF-8?B?' . base64_encode($asunto) . '?=', $cuerpoHtml, $headers, '-f' . $from);
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
    $cuerpoB64  = chunk_split(base64_encode($cuerpoHtml));

    if ($adjunto) {
        $boundary   = 'YCAPS-' . bin2hex(random_bytes(12));
        $adjuntoB64 = chunk_split(base64_encode($adjunto['datos']));

        $mensaje = "From: {$nombre} <{$from}>\r\n"
                 . "To: {$para}\r\n"
                 . "Subject: {$subjectB64}\r\n"
                 . "MIME-Version: 1.0\r\n"
                 . "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n"
                 . "\r\n"
                 . "--{$boundary}\r\n"
                 . "Content-Type: text/html; charset=UTF-8\r\n"
                 . "Content-Transfer-Encoding: base64\r\n"
                 . "\r\n"
                 . $cuerpoB64
                 . "\r\n--{$boundary}\r\n"
                 . "Content-Type: {$adjunto['tipo']}; name=\"{$adjunto['nombre']}\"\r\n"
                 . "Content-Transfer-Encoding: base64\r\n"
                 . "Content-Disposition: attachment; filename=\"{$adjunto['nombre']}\"\r\n"
                 . "\r\n"
                 . $adjuntoB64
                 . "\r\n--{$boundary}--";
    } else {
        $mensaje = "From: {$nombre} <{$from}>\r\n"
                 . "To: {$para}\r\n"
                 . "Subject: {$subjectB64}\r\n"
                 . "MIME-Version: 1.0\r\n"
                 . "Content-Type: text/html; charset=UTF-8\r\n"
                 . "Content-Transfer-Encoding: base64\r\n"
                 . "\r\n"
                 . $cuerpoB64;
    }

    $cmd($mensaje . "\r\n.");
    $leer();

    $cmd("QUIT");
    fclose($socket);
}

function _formatearPrecio(float $precio): string
{
    return '$' . number_format($precio, 0, ',', '.') . ' COP';
}

// --- Plantilla HTML con la identidad visual de Ycaps (dorado/negro) ---
function _plantillaEmail(string $contenidoHtml): string
{
    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0"></head>'
        . '<body style="margin:0;padding:0;background:#0d0d0d;font-family:Arial,Helvetica,sans-serif;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0d0d0d;padding:28px 12px;">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="100%" style="max-width:560px;background:#181818;border-radius:14px;overflow:hidden;border:1px solid #2a2a2a;">'
        . '<tr><td style="background:#d4af37;padding:22px 28px;text-align:center;">'
        . '<span style="font-family:Arial,sans-serif;font-size:26px;font-weight:900;letter-spacing:2px;color:#111;">YCAPS</span>'
        . '</td></tr>'
        . '<tr><td style="padding:28px 28px;color:#eaeaea;font-size:14px;line-height:1.6;">'
        . $contenidoHtml
        . '</td></tr>'
        . '<tr><td style="padding:16px 28px;background:#111;text-align:center;border-top:1px solid #2a2a2a;">'
        . '<span style="font-size:12px;color:#888;">Ycaps Gorras &middot; www.ycapsgorras.com</span>'
        . '</td></tr>'
        . '</table>'
        . '</td></tr>'
        . '</table>'
        . '</body></html>';
}

function _filaDato(string $etiqueta, string $valor): string
{
    return '<tr>'
        . '<td style="padding:5px 0;color:#9a9a9a;font-size:13px;width:110px;vertical-align:top;">' . htmlspecialchars($etiqueta) . '</td>'
        . '<td style="padding:5px 0;color:#fff;font-size:13px;vertical-align:top;">' . htmlspecialchars($valor) . '</td>'
        . '</tr>';
}

function _tablaItems(array $items): string
{
    $filas = '';
    foreach ($items as $item) {
        $nombre   = $item['title']          ?? $item['nombre_producto'] ?? 'Producto';
        $precio   = (float) ($item['unit_price'] ?? $item['precio']    ?? 0);
        $cantidad = (int)   ($item['quantity']   ?? $item['cantidad']   ?? 1);
        $subtotal = $precio * $cantidad;
        $filas .= '<tr>'
            . '<td style="padding:7px 0;color:#eaeaea;font-size:13px;border-bottom:1px solid #2a2a2a;">' . htmlspecialchars($nombre) . ' &times;' . $cantidad . '</td>'
            . '<td style="padding:7px 0;color:#eaeaea;font-size:13px;text-align:right;border-bottom:1px solid #2a2a2a;white-space:nowrap;">' . _formatearPrecio($subtotal) . '</td>'
            . '</tr>';
    }
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:6px 0 18px;">' . $filas . '</table>';
}

function _cajaTotal(string $totalFmt): string
{
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;background:#d4af37;border-radius:8px;">'
        . '<tr>'
        . '<td style="padding:12px 16px;color:#111;font-weight:bold;font-size:14px;">TOTAL</td>'
        . '<td style="padding:12px 16px;color:#111;font-weight:bold;font-size:14px;text-align:right;">' . htmlspecialchars($totalFmt) . '</td>'
        . '</tr></table>';
}

function _tituloSeccion(string $texto): string
{
    return '<p style="margin:18px 0 6px;color:#d4af37;font-weight:bold;font-size:12px;letter-spacing:1px;text-transform:uppercase;">'
        . htmlspecialchars($texto) . '</p>';
}

function enviarEmailNuevoPedido(array $comprador, array $items, float $total, string $referencia): void
{
    $asunto       = "Pedido recibido — Ycaps #{$referencia}";
    $totalFmt     = _formatearPrecio($total);
    $nombre       = $comprador['nombre']       ?? '';
    $cedula       = $comprador['cedula']       ?? '';
    $email        = $comprador['email']        ?? '';
    $telefono     = $comprador['telefono']     ?? '';
    $direccion    = $comprador['direccion']    ?? '';
    $ciudad       = $comprador['ciudad']       ?? '';
    $departamento = $comprador['departamento'] ?? '';
    $direccionCompleta = trim($direccion . ', ' . $ciudad . ($departamento !== '' ? ', ' . $departamento : ''), ', ');

    $contenidoCliente =
        '<p style="margin:0 0 14px;font-size:15px;">Hola <strong>' . htmlspecialchars($nombre) . '</strong>,</p>'
        . '<p style="margin:0 0 4px;">Hemos recibido tu pedido en Ycaps. Aquí están los detalles:</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:10px;">'
        . _filaDato('Referencia', $referencia)
        . _filaDato('Cédula', $cedula)
        . _filaDato('Dirección', $direccionCompleta)
        . _filaDato('Método de pago', 'Wompi (pago en línea)')
        . _filaDato('Estado', 'Pendiente de pago')
        . '</table>'
        . _tituloSeccion('Productos')
        . _tablaItems($items)
        . _cajaTotal($totalFmt)
        . '<p style="margin:0 0 14px;">Guarda tu número de referencia para rastrear tu pedido en <a href="https://www.ycapsgorras.com" style="color:#d4af37;text-decoration:none;">www.ycapsgorras.com</a></p>'
        . '<p style="margin:0;">Gracias por confiar en Ycaps.</p>';

    $contenidoTienda =
        '<p style="margin:0 0 16px;font-size:16px;color:#d4af37;font-weight:bold;">¡Nuevo pedido recibido!</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">'
        . _filaDato('Referencia', $referencia)
        . _filaDato('Cliente', $nombre)
        . _filaDato('Cédula', $cedula)
        . _filaDato('Email', $email)
        . _filaDato('Teléfono', $telefono)
        . _filaDato('Dirección', $direccionCompleta)
        . _filaDato('Método de pago', 'Wompi (pago en línea)')
        . _filaDato('Estado', 'Pendiente de pago')
        . '</table>'
        . _tituloSeccion('Productos')
        . _tablaItems($items)
        . _cajaTotal($totalFmt)
        . '<p style="margin:0;"><a href="https://www.ycapsgorras.com/admin/" style="color:#d4af37;text-decoration:none;">Ver en el panel admin →</a></p>';

    if ($email !== '') {
        _smtpEnviar($email, $asunto, _plantillaEmail($contenidoCliente));
    }
    _smtpEnviar(TIENDA_EMAIL, 'Nuevo pedido — ' . $asunto, _plantillaEmail($contenidoTienda));
}

function enviarEmailGuiaEnvio(string $emailCliente, string $nombreCliente, string $referencia, string $guia): void
{
    $asunto = "Tu pedido está en camino — Ycaps #{$referencia}";

    $contenido =
        '<p style="margin:0 0 14px;font-size:15px;">Hola <strong>' . htmlspecialchars($nombreCliente) . '</strong>,</p>'
        . '<p style="margin:0 0 14px;">¡Tu pedido ya fue despachado! Aquí tienes el número de guía para rastrearlo:</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;background:#d4af37;border-radius:8px;">'
        . '<tr><td style="padding:14px 16px;color:#111;font-weight:bold;font-size:15px;text-align:center;">' . htmlspecialchars($guia) . '</td></tr>'
        . '</table>'
        . '<p style="margin:0 0 14px;">Puedes rastrear tu paquete en el sitio web de la transportadora con ese número.</p>'
        . '<p style="margin:0 0 14px;">Si tienes alguna duda, escríbenos por <a href="https://wa.me/573004710483" style="color:#d4af37;text-decoration:none;">WhatsApp</a>.</p>'
        . '<p style="margin:0;">Gracias por tu compra.</p>';

    _smtpEnviar($emailCliente, $asunto, _plantillaEmail($contenido));

    $contenidoTienda =
        '<p style="margin:0;">Guía <strong style="color:#d4af37;">' . htmlspecialchars($guia) . '</strong> notificada a '
        . htmlspecialchars($nombreCliente) . ' (' . htmlspecialchars($emailCliente) . ') — Pedido #' . htmlspecialchars($referencia) . '.</p>';

    _smtpEnviar(TIENDA_EMAIL, "Guía enviada al cliente — Pedido #{$referencia}", _plantillaEmail($contenidoTienda));
}

// $pedido debe incluir: nombre, cedula, email, telefono, direccion, ciudad, departamento, total
function enviarEmailPagoConfirmado(
    array $pedido,
    string $referencia,
    ?string $pdfDatos = null,
    ?string $pdfNombre = null
): void {
    $emailCliente  = $pedido['email']        ?? '';
    $nombreCliente = $pedido['nombre']        ?? '';
    $cedula        = $pedido['cedula']        ?? '';
    $telefono      = $pedido['telefono']      ?? '';
    $direccion     = $pedido['direccion']     ?? '';
    $ciudad        = $pedido['ciudad']        ?? '';
    $departamento  = $pedido['departamento']  ?? '';
    $metodoPago    = ucfirst($pedido['metodo_pago'] ?? 'wompi');
    $total         = (float) ($pedido['total'] ?? 0);
    $direccionCompleta = trim($direccion . ', ' . $ciudad . ($departamento !== '' ? ', ' . $departamento : ''), ', ');

    $asunto   = "¡Pago confirmado! — Ycaps #{$referencia}";
    $totalFmt = _formatearPrecio($total);

    $contenidoCliente =
        '<p style="margin:0 0 14px;font-size:15px;">Hola <strong>' . htmlspecialchars($nombreCliente) . '</strong>,</p>'
        . '<p style="margin:0 0 14px;">¡Tu pago ha sido confirmado! Pronto te contactaremos por WhatsApp para coordinar el envío.</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:6px;">'
        . _filaDato('Referencia', $referencia)
        . _filaDato('Cédula', $cedula)
        . _filaDato('Dirección', $direccionCompleta)
        . _filaDato('Método de pago', $metodoPago)
        . '</table>'
        . _cajaTotal($totalFmt)
        . '<p style="margin:0 0 14px;">Puedes rastrear tu pedido en <a href="https://www.ycapsgorras.com" style="color:#d4af37;text-decoration:none;">www.ycapsgorras.com</a> — sección "Estado pedido".</p>'
        . '<p style="margin:0;">Gracias por tu compra.</p>';

    $contenidoTienda =
        '<p style="margin:0 0 16px;font-size:16px;color:#d4af37;font-weight:bold;">¡Pago confirmado por Wompi!</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">'
        . _filaDato('Referencia', $referencia)
        . _filaDato('Cliente', $nombreCliente)
        . _filaDato('Cédula', $cedula)
        . _filaDato('Email', $emailCliente)
        . _filaDato('Teléfono', $telefono)
        . _filaDato('Dirección', $direccionCompleta)
        . _filaDato('Método de pago', $metodoPago)
        . '</table>'
        . _cajaTotal($totalFmt)
        . '<p style="margin:0 0 6px;">Alista el pedido para envío.</p>'
        . '<p style="margin:0;"><a href="https://www.ycapsgorras.com/admin/" style="color:#d4af37;text-decoration:none;">Ver en el panel admin →</a></p>'
        . ($pdfDatos !== null ? '<p style="margin:14px 0 0;color:#9a9a9a;font-size:12px;">📎 Se adjunta el recibo en PDF de esta compra.</p>' : '');

    if ($emailCliente !== '') {
        _smtpEnviar($emailCliente, $asunto, _plantillaEmail($contenidoCliente));
    }

    $adjunto = ($pdfDatos !== null)
        ? ['nombre' => $pdfNombre ?: "recibo-{$referencia}.pdf", 'datos' => $pdfDatos, 'tipo' => 'application/pdf']
        : null;

    _smtpEnviar(TIENDA_EMAIL, 'Pago confirmado — ' . $asunto, _plantillaEmail($contenidoTienda), $adjunto);

    _telegramNotificar(
        "✅ NUEVO PEDIDO PAGADO\n"
        . "Cliente: {$nombreCliente}\n"
        . "Dirección: {$direccionCompleta}\n"
        . "Ref: {$referencia}\n"
        . "Total: {$totalFmt}\n"
        . "Panel: https://www.ycapsgorras.com/admin/"
    );
}

// $pedido debe incluir: nombre, email, telefono, direccion, ciudad, departamento, total
// $estadoInterno: 'rechazado' | 'anulado' | 'error'
function enviarEmailPagoRechazado(array $pedido, string $referencia, string $estadoInterno): void
{
    $emailCliente  = $pedido['email']        ?? '';
    $nombreCliente = $pedido['nombre']        ?? '';
    $telefono      = $pedido['telefono']      ?? '';
    $direccion     = $pedido['direccion']     ?? '';
    $ciudad        = $pedido['ciudad']        ?? '';
    $departamento  = $pedido['departamento']  ?? '';
    $total         = (float) ($pedido['total'] ?? 0);
    $direccionCompleta = trim($direccion . ', ' . $ciudad . ($departamento !== '' ? ', ' . $departamento : ''), ', ');
    $totalFmt = _formatearPrecio($total);

    $etiquetas = [
        'rechazado' => 'Pago rechazado',
        'anulado'   => 'Pago anulado',
        'error'     => 'Error al procesar el pago',
    ];
    $etiquetaEstado = $etiquetas[$estadoInterno] ?? 'Pago no completado';

    $asunto = "{$etiquetaEstado} — Ycaps #{$referencia}";

    $contenidoCliente =
        '<p style="margin:0 0 14px;font-size:15px;">Hola <strong>' . htmlspecialchars($nombreCliente) . '</strong>,</p>'
        . '<p style="margin:0 0 14px;">Tu pago no pudo completarse (' . htmlspecialchars(mb_strtolower($etiquetaEstado)) . '). No te preocupes, no se realizó ningún cobro.</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:6px;">'
        . _filaDato('Referencia', $referencia)
        . _filaDato('Estado', $etiquetaEstado)
        . '</table>'
        . _cajaTotal($totalFmt)
        . '<p style="margin:14px 0 14px;">Puedes intentar nuevamente desde <a href="https://www.ycapsgorras.com" style="color:#d4af37;text-decoration:none;">www.ycapsgorras.com</a> o hacer tu pedido directamente por <a href="https://wa.me/573004710483" style="color:#d4af37;text-decoration:none;">WhatsApp</a>.</p>'
        . '<p style="margin:0;">Estamos atentos si necesitas ayuda.</p>';

    $contenidoTienda =
        '<p style="margin:0 0 16px;font-size:16px;color:#d4af37;font-weight:bold;">⚠️ ' . htmlspecialchars($etiquetaEstado) . '</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">'
        . _filaDato('Referencia', $referencia)
        . _filaDato('Cliente', $nombreCliente)
        . _filaDato('Email', $emailCliente)
        . _filaDato('Teléfono', $telefono)
        . _filaDato('Dirección', $direccionCompleta)
        . _filaDato('Estado', $etiquetaEstado)
        . '</table>'
        . _cajaTotal($totalFmt)
        . '<p style="margin:0;"><a href="https://www.ycapsgorras.com/admin/" style="color:#d4af37;text-decoration:none;">Ver en el panel admin →</a></p>';

    if ($emailCliente !== '') {
        _smtpEnviar($emailCliente, $asunto, _plantillaEmail($contenidoCliente));
    }
    _smtpEnviar(TIENDA_EMAIL, $etiquetaEstado . ' — ' . $asunto, _plantillaEmail($contenidoTienda));

    _telegramNotificar(
        "⚠️ " . mb_strtoupper($etiquetaEstado) . "\n"
        . "Cliente: {$nombreCliente}\n"
        . "Ref: {$referencia}\n"
        . "Total: {$totalFmt}"
    );
}
