<?php
// Generador minimalista de PDF sin dependencias externas — para recibos de compra.
// Construye el documento PDF directamente (estructura de objetos + xref), con
// fuente estándar Helvetica y, si GD está disponible, el logo embebido como JPEG
// (PDF soporta JPEG de forma nativa, sin necesidad de decodificar PNG/alpha).

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

function _pdfDesdeLineas(array $lineas, ?array $logo = null): string
{
    $textoInicioY = 760;
    $stream       = '';

    if ($logo) {
        $logoAnchoPt = 90;
        $logoAltoPt  = $logo['alto'] * ($logoAnchoPt / $logo['ancho']);
        $logoX       = (612 - $logoAnchoPt) / 2;
        $logoY       = 792 - 40 - $logoAltoPt;
        $textoInicioY = $logoY - 25;

        $stream .= "q\n{$logoAnchoPt} 0 0 {$logoAltoPt} {$logoX} {$logoY} cm\n/Im1 Do\nQ\n";
    }

    $stream .= "BT /F1 10 Tf\n50 {$textoInicioY} Td\n";
    foreach ($lineas as $i => $linea) {
        if ($i > 0) {
            $stream .= "0 -16 Td\n";
        }
        $stream .= '(' . _pdfEscapeTexto($linea) . ") Tj\n";
    }
    $stream .= 'ET';

    $recursos = '/Font << /F1 5 0 R >>';

    $objetos = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        4 => "<< /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream",
        5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
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

    $lineas = [];
    $lineas[] = 'YCAPS - RECIBO DE COMPRA';
    $lineas[] = 'Recibo No: ' . $numeroRecibo;
    $lineas[] = '';
    $lineas[] = 'Referencia: ' . $pedido['wompi_referencia'];
    $lineas[] = 'Fecha: ' . date('d/m/Y H:i', strtotime($pedido['creado_en']));
    $lineas[] = 'Estado: ' . ucfirst($pedido['estado']);
    $lineas[] = '';
    $lineas[] = 'Cliente: ' . $pedido['nombre'];
    $lineas[] = 'Email: ' . $pedido['email'];
    $lineas[] = 'Telefono: ' . $pedido['telefono'];
    $lineas[] = 'Direccion: ' . $pedido['direccion'] . ', ' . $pedido['ciudad']
        . (!empty($pedido['departamento']) ? ', ' . $pedido['departamento'] : '');
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

    return _pdfDesdeLineas($lineas, $logo);
}
