<?php

namespace PhSalaryGrade;

use RuntimeException;

class PdfExtractor
{
    /**
     * Extract raw text from a PDF file.
     * Tries pdftotext (poppler) first, falls back to smalot/pdfparser.
     *
     * @param  string  $path  Absolute path to the PDF file
     * @return string
     *
     * @throws RuntimeException
     */
    public static function extract(string $path): string
    {
        if (! file_exists($path)) {
            throw new RuntimeException("File not found: {$path}");
        }

        if (! str_ends_with(strtolower($path), '.pdf')) {
            throw new RuntimeException("File does not appear to be a PDF: {$path}");
        }

        if (! class_exists(\Smalot\PdfParser\Parser::class)) {
            throw new RuntimeException(
                "No PDF extraction backend available. " .
                "Require smalot/pdfparser via Composer."
            );
        }

        return self::extractWithSmalot($path);
    }

    private static function extractWithSmalot(string $path): string
    {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf    = $parser->parseFile($path);
        $fullText = '';

        foreach ($pdf->getPages() as $page) {
            $data = $page->getDataTm();
            $items = [];

            // Extract text with coordinates
            foreach ($data as $item) {
                // Some versions return text directly, others in an array. Usually $item[1] is text.
                $text = trim($item[1] ?? '');
                if ($text === '') {
                    continue;
                }
                
                $x = $item[0][4];
                $y = $item[0][5];
                $items[] = ['text' => $text, 'x' => $x, 'y' => $y];
            }

            // Sort by Y descending (top to bottom)
            usort($items, function($a, $b) {
                return $b['y'] <=> $a['y'];
            });

            $rows = [];
            $currentRow = [];
            $currentY = null;

            // Group into rows if Y coordinate difference is small
            foreach ($items as $item) {
                if ($currentY === null) {
                    $currentY = $item['y'];
                }

                if (abs($currentY - $item['y']) > 12) {
                    $rows[] = $currentRow;
                    $currentRow = [];
                    $currentY = $item['y'];
                }

                $currentRow[] = $item;
            }
            if (!empty($currentRow)) {
                $rows[] = $currentRow;
            }

            // Sort each row by X ascending (left to right) and build string
            foreach ($rows as $row) {
                usort($row, function($a, $b) {
                    return $a['x'] <=> $b['x'];
                });
                
                $lineTexts = array_column($row, 'text');
                $fullText .= implode(" ", $lineTexts) . "\n";
            }
            
            $fullText .= "\n\n";
        }

        if (trim($fullText) === '') {
            throw new RuntimeException("smalot/pdfparser returned empty text for: {$path}");
        }

        return $fullText;
    }
}
