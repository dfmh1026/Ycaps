<?php
// Funciones de correo para Ycaps.
// Requiere TIENDA_EMAIL y TIENDA_NOMBRE definidas en config.php.

function _formatearPrecio(float $precio): string
{
    return '$' . number_format($precio, 0, ',', '.') . ' COP';
}

function _listadoItems(array $items): string
{
    $lineas = [];
    foreach ($items as $item) {
        // Acepta tanto el formato de crear-transaccion.php como el de la BD
        $nombre   = $item['title']          ?? $item['nombre_producto'] ?? 'Producto';
        $precio   = (float) ($item['unit_price'] ?? $item['precio']    ?? 0);
        $cantidad = (int)   ($item['quantity']   ?? $item['cantidad']   ?? 1);
        $subtotal = $precio * $cantidad;
        $lineas[] = "  - {$nombre} x{$cantidad} = " . _formatearPrecio($subtotal);
    }
    return implode("\n", $lineas);
}

function _headers(): string
{
    return "From: " . TIENDA_NOMBRE . " <" . TIENDA_EMAIL . ">\r\n"
         . "Reply-To: " . TIENDA_EMAIL . "\r\n"
         . "MIME-Version: 1.0\r\n"
         . "Content-Type: text/plain; charset=UTF-8\r\n";
}

// Envía emails cuando se crea un pedido (antes de confirmación de pago).
function enviarEmailNuevoPedido(array $comprador, array $items, float $total, string $referencia): void
{
    $asunto      = "Pedido recibido — Ycaps #{$referencia}";
    $itemsTxt    = _listadoItems($items);
    $totalFmt    = _formatearPrecio($total);
    $nombre      = $comprador['nombre']    ?? '';
    $email       = $comprador['email']     ?? '';
    $telefono    = $comprador['telefono']  ?? '';
    $direccion   = $comprador['direccion'] ?? '';
    $ciudad      = $comprador['ciudad']    ?? '';

    // Email para el cliente
    $cuerpoCliente =
        "Hola {$nombre},\n\n"
        . "Hemos recibido tu pedido en Ycaps. Aquí están los detalles:\n\n"
        . "Referencia: {$referencia}\n"
        . "Productos:\n{$itemsTxt}\n"
        . "Total: {$totalFmt}\n"
        . "Estado: Pendiente de pago\n\n"
        . "Guarda tu número de referencia — lo necesitarás para rastrear tu pedido en www.ycapsgorras.com\n\n"
        . "Gracias por confiar en Ycaps.\n"
        . "— El equipo de Ycaps | www.ycapsgorras.com";

    // Email para la tienda
    $cuerpoTienda =
        "Nuevo pedido recibido en Ycaps:\n\n"
        . "Referencia:  {$referencia}\n"
        . "Cliente:     {$nombre}\n"
        . "Email:       {$email}\n"
        . "Teléfono:    {$telefono}\n"
        . "Dirección:   {$direccion}, {$ciudad}\n\n"
        . "Productos:\n{$itemsTxt}\n"
        . "Total: {$totalFmt}\n"
        . "Estado: Pendiente de pago\n\n"
        . "Ingresa al panel de base de datos para ver el detalle completo.";

    $headers = _headers();
    if ($email !== '') {
        @mail($email, $asunto, $cuerpoCliente, $headers);
    }
    @mail(TIENDA_EMAIL, 'Nuevo pedido — ' . $asunto, $cuerpoTienda, $headers);
}

// Envía emails cuando Wompi confirma el pago (desde webhook).
function enviarEmailPagoConfirmado(string $emailCliente, string $nombreCliente, string $referencia, float $total): void
{
    $asunto   = "¡Pago confirmado! — Ycaps #{$referencia}";
    $totalFmt = _formatearPrecio($total);

    $cuerpoCliente =
        "Hola {$nombreCliente},\n\n"
        . "¡Tu pago ha sido confirmado! Pronto te contactaremos por WhatsApp para coordinar el envío.\n\n"
        . "Referencia: {$referencia}\n"
        . "Total pagado: {$totalFmt}\n\n"
        . "Puedes rastrear tu pedido en cualquier momento en:\n"
        . "www.ycapsgorras.com — sección \"Rastrear pedido\"\n\n"
        . "Gracias por tu compra.\n"
        . "— El equipo de Ycaps | www.ycapsgorras.com";

    $cuerpoTienda =
        "Pago confirmado por Wompi:\n\n"
        . "Referencia: {$referencia}\n"
        . "Cliente:    {$nombreCliente}\n"
        . "Email:      {$emailCliente}\n"
        . "Total:      {$totalFmt}";

    $headers = _headers();
    if ($emailCliente !== '') {
        @mail($emailCliente, $asunto, $cuerpoCliente, $headers);
    }
    @mail(TIENDA_EMAIL, 'Pago confirmado — ' . $asunto, $cuerpoTienda, $headers);
}
