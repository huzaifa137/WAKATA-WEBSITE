<?php

namespace App\Support;

/**
 * A tiny one-page PDF writer (no library needed): used for the sample "licence sheet" that stands in for a paper
 * until the real PDF is uploaded (see Library). Plain Helvetica text, A4.
 */
class SimplePdf
{
    /** @param  array<int, string>  $lines  body lines under the heading */
    public static function sheet(string $brand, string $tagline, string $heading, array $lines): string
    {
        $stream  = self::text('F2', 22, 60, 780, $brand);
        $stream .= self::text('F1', 11, 60, 756, $tagline);
        $stream .= self::text('F2', 18, 60, 686, $heading);

        $y = 662;
        foreach ($lines as $line) {
            $stream .= self::text('F1', 12, 60, $y, $line);
            $y -= 20;
        }

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> >>',
            '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . 'endstream',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $pdf .= str_pad((string) $o, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }

        return $pdf . "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
    }

    private static function text(string $font, int $size, int $x, int $y, string $text): string
    {
        // Keep to printable ASCII and escape what PDF strings treat specially
        $text = preg_replace('/[^\x20-\x7E]/', '', $text) ?? '';
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);

        return "BT /$font $size Tf $x $y Td ($text) Tj ET\n";
    }
}
