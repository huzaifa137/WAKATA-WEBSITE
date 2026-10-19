<?php

namespace App\Support;

/**
 * A small multi-page PDF table writer (no library needed), used for the report downloads (module 10).
 * A4 landscape, Courier so columns line up with plain padding, the title and page number on every page.
 */
class ReportPdf
{
    private const W = 842;
    private const H = 595;
    private const MARGIN = 40;
    private const SIZE = 8;
    private const LEAD = 11;
    private const CHARS = 158;          // 762pt of usable width / (0.6 * 8pt per Courier character)

    /**
     * @param  array<int, string>  $head
     * @param  array<int, array<int, string|int|float>>  $rows
     * @param  array<int, bool>  $numeric  column index => right-aligned
     */
    public static function table(string $brand, string $title, string $subtitle, array $head, array $rows, array $numeric = []): string
    {
        $rows  = array_map(fn ($r) => array_map(fn ($c) => self::clean((string) $c), array_values($r)), $rows);
        $head  = array_map(fn ($c) => self::clean((string) $c), array_values($head));
        $width = self::widths($head, $rows);

        $line = function (array $cells) use ($width, $numeric): string {
            $out = [];
            foreach ($width as $i => $w) {
                $text  = mb_substr($cells[$i] ?? '', 0, $w);
                $out[] = ! empty($numeric[$i]) ? str_pad($text, $w, ' ', STR_PAD_LEFT) : str_pad($text, $w);
            }

            return rtrim(implode('  ', $out));
        };

        $perPage = (int) floor((self::H - 2 * self::MARGIN - 70) / self::LEAD);
        $chunks  = array_chunk($rows ?: [[]], max(1, $perPage));
        $pages   = [];

        foreach ($chunks as $n => $chunk) {
            $s  = self::text('F2', 14, self::MARGIN, self::H - self::MARGIN, $brand . '  |  ' . $title);
            $s .= self::text('F1', 9, self::MARGIN, self::H - self::MARGIN - 16, $subtitle . '   Page ' . ($n + 1) . ' of ' . count($chunks));
            $y  = self::H - self::MARGIN - 40;
            $s .= self::text('F2', self::SIZE, self::MARGIN, $y, $line($head));
            $y -= self::LEAD;

            foreach ($chunk as $row) {
                if ($row === []) {
                    $s .= self::text('F1', self::SIZE, self::MARGIN, $y, 'Nothing to show for this period.');
                    break;
                }
                $s .= self::text('F1', self::SIZE, self::MARGIN, $y, $line($row));
                $y -= self::LEAD;
            }
            $pages[] = $s;
        }

        // objects: 1 catalog, 2 pages, 3 and 4 fonts, then a page and a content stream for every page
        $count   = count($pages);
        $kids    = implode(' ', array_map(fn ($i) => (5 + $i * 2) . ' 0 R', array_keys($pages)));
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            "<< /Type /Pages /Kids [$kids] /Count $count >>",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold >>',
        ];
        foreach ($pages as $i => $stream) {
            $content   = 6 + $i * 2;
            $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::W . ' ' . self::H . "] /Contents $content 0 R /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> >>";
            $objects[] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . 'endstream';
        }

        $pdf     = "%PDF-1.4\n";
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

    /** Column widths: as wide as the longest cell, shrunk together when the row would not fit the page. */
    private static function widths(array $head, array $rows): array
    {
        $w = array_map(fn ($h) => mb_strlen($h), $head);
        foreach ($rows as $r) {
            foreach ($w as $i => $cur) {
                $w[$i] = max($cur, mb_strlen($r[$i] ?? ''));
            }
        }

        $gap   = 2 * (count($w) - 1);
        $total = array_sum($w) + $gap;
        if ($total > self::CHARS) {
            $room = self::CHARS - $gap;
            $keep = array_map(fn ($x) => min($x, 12), $w);                  // short columns stay whole
            $left = $room - array_sum($keep);
            $long = array_sum(array_map(fn ($x, $k) => max(0, $x - $k), $w, $keep)) ?: 1;
            foreach ($w as $i => $x) {
                $w[$i] = max(4, (int) floor($keep[$i] + max(0, $x - $keep[$i]) / $long * max(0, $left)));
            }
        }

        return $w;
    }

    private static function clean(string $text): string
    {
        $text = strtr($text, ["\u{2019}" => "'", "\u{2018}" => "'", "\u{201C}" => '"', "\u{201D}" => '"', "\u{2013}" => '-', "\u{2014}" => '-', "\u{00A0}" => ' ']);

        return preg_replace('/[^\x20-\x7E]/', '', $text) ?? '';
    }

    private static function text(string $font, int $size, int $x, int $y, string $text): string
    {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], self::clean($text));

        return "BT /$font $size Tf $x $y Td ($text) Tj ET\n";
    }
}
