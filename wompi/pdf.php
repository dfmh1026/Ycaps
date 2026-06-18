<?php
// Generador minimalista de PDF sin dependencias externas — para recibos de compra.
// Construye el documento PDF directamente (estructura de objetos + xref), con
// fuentes estándar Helvetica/Helvetica-Bold, color y rectángulos rellenos para
// un diseño con identidad de marca, y el logo embebido como JPEG si GD existe.

function _pdfEscapeTexto(string $texto): string
{
    $convertido = @iconv('UTF-8', 'Windows-1252//IGNORE', $texto);
    if ($convertido === false) {
        $convertido = $texto;
    }
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $convertido);
}

function _logoComoJpeg(string $rutaPng, int $anchoMax = 240): ?array
{
    if (!is_file($rutaPng) || !function_exists('imagecreatefrompng')) {
        return null;
    }

    $origen = @imagecreatefrompng($rutaPng);
    if (!$origen) {
        return null;
    }

    $anchoOrig = imagesx($origen);
    $altoOrig  = imagesy($origen);
    $factor    = $anchoOrig > $anchoMax ? $anchoMax / $anchoOrig : 1;
    $ancho     = max(1, (int) round($anchoOrig * $factor));
    $alto      = max(1, (int) round($altoOrig * $factor));

    // Componer sobre fondo blanco (el logo puede tener transparencia PNG)
    $lienzo = imagecreatetruecolor($ancho, $alto);
    $blanco = imagecolorallocate($lienzo, 255, 255, 255);
    imagefill($lienzo, 0, 0, $blanco);
    imagecopyresampled($lienzo, $origen, 0, 0, 0, 0, $ancho, $alto, $anchoOrig, $altoOrig);

    ob_start();
    imagejpeg($lienzo, null, 88);
    $datos = ob_get_clean();

    imagedestroy($origen);
    imagedestroy($lienzo);

    return ['datos' => $datos, 'ancho' => $ancho, 'alto' => $alto];
}

function _pdfTexto(float $x, float $y, string $texto, string $fuente, float $tamano, array $colorRGB): string
{
    [$r, $g, $b] = $colorRGB;
    $esc = _pdfEscapeTexto($texto);
    return sprintf("%.3f %.3f %.3f rg\nBT /%s %.1f Tf %.2f %.2f Td (%s) Tj ET\n", $r, $g, $b, $fuente, $tamano, $x, $y, $esc);
}

function _pdfRect(float $x, float $y, float $w, float $h, array $colorRGB): string
{
    [$r, $g, $b] = $colorRGB;
    return sprintf("%.3f %.3f %.3f rg\n%.2f %.2f %.2f %.2f re f\n", $r, $g, $b, $x, $y, $w, $h);
}

function _pdfDocumento(string $stream, ?array $logo = null): string
{
    $recursos = '/Font << /F1 5 0 R /F2 7 0 R >>';

    $objetos = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        4 => "<< /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream",
        5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        7 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
    ];

    if ($logo) {
        $recursos .= ' /XObject << /Im1 6 0 R >>';
        $objetos[6] = "<< /Type /XObject /Subtype /Image /Width {$logo['ancho']} /Height {$logo['alto']} "
            . '/ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '
            . strlen($logo['datos']) . " >>\nstream\n" . $logo['datos'] . "\nendstream";
    }

    $objetos[3] = "<< /Type /Page /Parent 2 0 R /Resources << {$recursos} >> /MediaBox [0 0 612 792] /Contents 4 0 R >>";
    ksort($objetos);

    $pdf     = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objetos as $num => $cuerpo) {
        $offsets[$num] = strlen($pdf);
        $pdf .= "{$num} 0 obj\n{$cuerpo}\nendobj\n";
    }

    $maxObj     = max(array_keys($objetos));
    $totalObjs  = $maxObj + 1;
    $xrefOffset = strlen($pdf);

    $pdf .= "xref\n0 {$totalObjs}\n0000000000 65535 f \n";
    for ($i = 1; $i <= $maxObj; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
    }
    $pdf .= "trailer\n<< /Size {$totalObjs} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
}

function generarReciboPdf(array $pedido, array $items, string $numeroRecibo): string
{
    $logo = _logoComoJpeg(__DIR__ . '/../media/logoycaps.png');

    $dorado    = [0.831, 0.686, 0.216];
    $negro     = [0.07, 0.07, 0.07];
    $gris      = [0.45, 0.45, 0.45];
    $grisClaro = [0.85, 0.85, 0.85];
    $margenIzq = 50;
    $anchoUtil = 512;

    $stream = '';
    $y = 792 - 40;

    if ($logo) {
        $logoAnchoPt = 90;
        $logoAltoPt  = $logo['alto'] * ($logoAnchoPt / $logo['ancho']);
        $logoX       = (612 - $logoAnchoPt) / 2;
        $logoY       = $y - $logoAltoPt;
        $stream .= sprintf("q\n%.2f 0 0 %.2f %.2f %.2f cm\n/Im1 Do\nQ\n", $logoAnchoPt, $logoAltoPt, $logoX, $logoY);
        $y = $logoY - 16;
    }

    // Barra dorada decorativa
    $stream .= _pdfRect($margenIzq, $y, $anchoUtil, 2.5, $dorado);
    $y -= 26;

    // Título y número de recibo
    $stream .= _pdfTexto($margenIzq, $y, 'YCAPS - RECIBO DE COMPRA', 'F2', 16, $dorado);
    $stream .= _pdfTexto($margenIzq + 320, $y, 'Recibo No: ' . $numeroRecibo, 'F1', 9, $gris);
    $y -= 30;

    // --- Sección: datos del pedido ---
    $stream .= _pdfTexto($margenIzq, $y, 'DATOS DEL PEDIDO', 'F2', 10, $negro);
    $y -= 6;
    $stream .= _pdfRect($margenIzq, $y, $anchoUtil, 0.75, $grisClaro);
    $y -= 18;

    $direccionCompleta = $pedido['direccion'] . ', ' . $pedido['ciudad']
        . (!empty($pedido['departamento']) ? ', ' . $pedido['departamento'] : '');

    $campos = [
        'Referencia' => $pedido['wompi_referencia'],
        'Fecha'      => date('d/m/Y H:i', strtotime($pedido['creado_en'])),
        'Estado'     => ucfirst($pedido['estado']),
        'Cliente'    => $pedido['nombre'],
        'Cedula'     => $pedido['cedula'] ?? '',
        'Email'      => $pedido['email'],
        'Telefono'   => $pedido['telefono'],
        'Direccion'  => $direccionCompleta,
    ];

    foreach ($campos as $etiqueta => $valor) {
        $stream .= _pdfTexto($margenIzq, $y, $etiqueta . ':', 'F2', 9, $gris);
        $stream .= _pdfTexto($margenIzq + 95, $y, (string) $valor, 'F1', 9, $negro);
        $y -= 16;
    }

    $y -= 8;

    // --- Sección: productos ---
    $stream .= _pdfTexto($margenIzq, $y, 'PRODUCTOS', 'F2', 10, $negro);
    $y -= 6;
    $stream .= _pdfRect($margenIzq, $y, $anchoUtil, 0.75, $grisClaro);
    $y -= 18;

    $stream .= _pdfTexto($margenIzq, $y, 'Producto', 'F2', 8.5, $gris);
    $stream .= _pdfTexto($margenIzq + 330, $y, 'Cant.', 'F2', 8.5, $gris);
    $stream .= _pdfTexto($margenIzq + 400, $y, 'Subtotal', 'F2', 8.5, $gris);
    $y -= 8;
    $stream .= _pdfRect($margenIzq, $y, $anchoUtil, 0.5, $grisClaro);
    $y -= 16;

    foreach ($items as $it) {
        $subtotal = (float) $it['precio'] * (int) $it['cantidad'];
        $stream .= _pdfTexto($margenIzq, $y, (string) $it['nombre_producto'], 'F1', 9, $negro);
        $stream .= _pdfTexto($margenIzq + 330, $y, 'x' . $it['cantidad'], 'F1', 9, $negro);
        $stream .= _pdfTexto($margenIzq + 400, $y, '$' . number_format($subtotal, 0, ',', '.'), 'F1', 9, $negro);
        $y -= 18;
    }

    $y -= 4;
    $stream .= _pdfRect($margenIzq, $y, $anchoUtil, 0.75, $grisClaro);
    $y -= 28;

    // --- Total destacado ---
    $stream .= _pdfRect($margenIzq, $y - 8, $anchoUtil, 30, [0.97, 0.94, 0.85]);
    $stream .= _pdfTexto($margenIzq + 14, $y, 'TOTAL', 'F2', 13, $negro);
    $stream .= _pdfTexto(
        $margenIzq + 380,
        $y,
        '$' . number_format((float) $pedido['total'], 0, ',', '.') . ' COP',
        'F2',
        13,
        $dorado
    );
    $y -= 42;

    $stream .= _pdfTexto(
        $margenIzq,
        $y,
        'Transaccion Wompi: ' . ($pedido['wompi_transaction_id'] ?: '-'),
        'F1',
        8,
        $gris
    );
    $y -= 26;

    $stream .= _pdfRect($margenIzq, $y + 16, $anchoUtil, 0.75, $grisClaro);
    $stream .= _pdfTexto($margenIzq, $y, 'Gracias por tu compra en Ycaps', 'F2', 9.5, $dorado);
    $y -= 14;
    $stream .= _pdfTexto($margenIzq, $y, 'www.ycapsgorras.com', 'F1', 8, $gris);

    return _pdfDocumento($stream, $logo);
}
