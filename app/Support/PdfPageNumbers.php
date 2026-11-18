<?php

namespace App\Support;

use Barryvdh\DomPDF\PDF;
use Dompdf\Dompdf;

/**
 * Draws "Page X of Y" on PDFs without enabling dompdf's inline PHP
 * (<script type="text/php">), which would execute any PHP that user-supplied
 * HTML smuggles into a template.
 *
 * A template opts in with a meta tag in its <head>:
 *
 *   <meta name="pdf-page-numbers" content="right=95;bottom=24;size=8.5;font=Helvetica">
 *
 * right/bottom are offsets in points from the page's right and bottom edges.
 */
class PdfPageNumbers
{
    public const META_NAME = 'pdf-page-numbers';

    private const DEFAULTS = [
        'right' => 95.0,
        'bottom' => 24.0,
        'size' => 8.5,
        'font' => 'Helvetica',
        'text' => 'Page {PAGE_NUM} of {PAGE_COUNT}',
    ];

    private const COLOR = [100 / 255, 116 / 255, 139 / 255];

    public static function register(PDF $pdf): PDF
    {
        $dompdf = $pdf->getDomPDF();

        $dompdf->setCallbacks([[
            'event' => 'end_document',
            'f' => static function (int $pageNumber, int $pageCount, $canvas, $fontMetrics) use ($dompdf): void {
                $config = self::configFor($dompdf);
                if ($config === null) {
                    return;
                }

                $text = str_replace(['{PAGE_NUM}', '{PAGE_COUNT}'], [$pageNumber, $pageCount], $config['text']);
                $font = $fontMetrics->getFont($config['font'], 'normal');

                $canvas->text(
                    $canvas->get_width() - $config['right'],
                    $canvas->get_height() - $config['bottom'],
                    $text,
                    $font,
                    $config['size'],
                    self::COLOR
                );
            },
        ]]);

        return $pdf;
    }

    /**
     * @return array{right: float, bottom: float, size: float, font: string, text: string}|null
     */
    private static function configFor(Dompdf $dompdf): ?array
    {
        $dom = $dompdf->getDom();
        if (! $dom) {
            return null;
        }

        foreach ($dom->getElementsByTagName('meta') as $meta) {
            if (strtolower($meta->getAttribute('name')) !== self::META_NAME) {
                continue;
            }

            $config = self::DEFAULTS;
            foreach (explode(';', $meta->getAttribute('content')) as $pair) {
                [$key, $value] = array_pad(array_map('trim', explode('=', $pair, 2)), 2, '');
                if ($key === '' || ! array_key_exists($key, $config) || $value === '') {
                    continue;
                }
                $config[$key] = in_array($key, ['font', 'text'], true) ? $value : (float) $value;
            }

            return $config;
        }

        return null;
    }
}
