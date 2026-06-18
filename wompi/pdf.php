<?php
// Generador minimalista de PDF sin dependencias externas — para recibos de compra.
// Construye el documento PDF directamente (estructura de objetos + xref) usando
// solo la fuente estándar Helvetica, suficiente para un recibo de texto simple.

function _pdfEscapeTexto(string $texto): string
{
    $convertido = @iconv('UTF-8', 'Windows-1252//IGNORE', $texto);
    if ($convertido === false) {
        $convertido = $texto;
    }
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $convertido);
}

function _pdfDesdeLineas(array $lineas): string
{
    $stream = "BT /F1 10 Tf\n50 760 Td\n";
    foreach ($lineas as $i => $linea) {
        if ($i > 0) {
            $stream .= "0 -16 Td\n";
        }
        $stream .= '(' . _pdfEscapeTexto($linea) . ") Tj\n";
    }
    $stream .= 'ET';

    $objetos = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /MediaBox [0 0 612 792] /Contents 4 0 R >>',
        4 => "<< /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream",
        5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
    ];

    $pdf     = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objetos as $num => $cuerpo) {
        $offsets[$num] = strlen($pdf);
        $pdf .= "{$num} 0 obj\n{$cuerpo}\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $totalObjs  = count($objetos) + 1;

    $pdf .= "xref\n0 {$totalObjs}\n0000000000 65535 f \n";
    for ($i = 1; $i <= count($objetos); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size {$totalObjs} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
}

function generarReciboPdf(array $pedido, array $items): string
{
    $lineas = [];
    $lineas[] = 'YCAPS - RECIBO DE COMPRA';
    $lineas[] = '';
    $lineas[] = 'Referencia: ' . $pedido['wompi_referencia'];
    $lineas[] = 'Fecha: ' . date('d/m/Y H:i', strtotime($pedido['creado_en']));
    $lineas[] = 'Estado: ' . ucfirst($pedido['estado']);
    $lineas[] = '';
    $lineas[] = 'Cliente: ' . $pedido['nombre'];
    $lineas[] = 'Email: ' . $pedido['email'];
    $lineas[] = 'Telefono: ' . $pedido['telefono'];
    $lineas[] = 'Direccion: ' . $pedido['direccion'] . ', ' . $pedido['ciudad'];
    $lineas[] = '';
    $lineas[] = 'PRODUCTOS';
    $lineas[] = '------------------------------------------------';
    foreach ($items as $it) {
        $subtotal = (float) $it['precio'] * (int) $it['cantidad'];
        $lineas[] = sprintf(
            '%-28s x%-3d $%s',
            $it['nombre_producto'],
            $it['cantidad'],
            number_format($subtotal, 0, ',', '.')
        );
    }
    $lineas[] = '------------------------------------------------';
    $lineas[] = '';
    $lineas[] = 'TOTAL: $' . number_format((float) $pedido['total'], 0, ',', '.') . ' COP';
    $lineas[] = '';
    $lineas[] = 'Transaccion Wompi: ' . ($pedido['wompi_transaction_id'] ?: '-');
    $lineas[] = '';
    $lineas[] = 'Gracias por tu compra en Ycaps';
    $lineas[] = 'www.ycapsgorras.com';

    return _pdfDesdeLineas($lineas);
}
