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

        // Try pdftotext (poppler-utils) — best layout preservation
        if (self::hasPdfToText()) {
            return self::extractWithPdfToText($path);
        }

        // Fall back to smalot/pdfparser
        if (class_exists(\Smalot\PdfParser\Parser::class)) {
            return self::extractWithSmalot($path);
        }

        throw new RuntimeException(
            "No PDF extraction backend available. " .
            "Install poppler-utils (pdftotext) or require smalot/pdfparser via Composer."
        );
    }

    // -------------------------------------------------------------------------

    private static function hasPdfToText(): bool
    {
        $command = (DIRECTORY_SEPARATOR === '\\') ? 'where pdftotext 2>nul' : 'which pdftotext 2>/dev/null';
        $out = shell_exec($command);
        return ! empty(trim((string) $out));
    }

    private static function extractWithPdfToText(string $path): string
    {
        // -layout preserves column/table spacing, critical for salary tables
        $escaped = escapeshellarg($path);
        $text    = shell_exec("pdftotext -layout {$escaped} -");

        if ($text === null || trim($text) === '') {
            throw new RuntimeException("pdftotext returned empty output for: {$path}");
        }

        return $text;
    }

    private static function extractWithSmalot(string $path): string
    {
        $parser  = new \Smalot\PdfParser\Parser();
        $pdf     = $parser->parseFile($path);
        $text    = $pdf->getText();

        if (trim($text) === '') {
            throw new RuntimeException("smalot/pdfparser returned empty text for: {$path}");
        }

        return $text;
    }
}
