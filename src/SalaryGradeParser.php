<?php

namespace PhSalaryGrade;

use InvalidArgumentException;

class SalaryGradeParser
{
    /**
     * Parse salary grade data from raw PDF text.
     *
     * Returns an array of objects ready for JSON encoding:
     *
     *   [
     *     ["salary_grade" => 1,  "step_1" => 14061, "step_2" => 14164, ..., "step_8" => 14862],
     *     ["salary_grade" => 2,  "step_1" => 14925, "step_2" => 15035, ..., "step_8" => 15714],
     *     ...
     *   ]
     *
     * JSON output (via toJson / fileToJson):
     *
     *   [
     *     { "salary_grade": 1, "step_1": 14061, "step_2": 14164, ..., "step_8": 14862 },
     *     { "salary_grade": 2, "step_1": 14925, ... },
     *     ...
     *   ]
     *
     * @param  string  $text
     * @return array<int, array<string, int|null>>
     * @throws InvalidArgumentException
     */
    public static function parse(string $text): array
    {
        $lines  = self::normalizeLines($text);
        $seen   = [];
        $result = [];

        foreach ($lines as $line) {
            $row = self::parseLine($line);
            if ($row === null) {
                continue;
            }

            [$grade, $steps] = $row;

            if (isset($seen[$grade])) {
                continue;
            }

            $seen[$grade] = true;
            $result[]     = self::toObject($grade, $steps);
        }

        if (empty($result)) {
            throw new InvalidArgumentException(
                "Could not parse any salary grade data from the provided text. " .
                "Ensure the PDF contains a DBM salary schedule table."
            );
        }

        usort($result, fn ($a, $b) => $a['salary_grade'] <=> $b['salary_grade']);

        return $result;
    }

    /**
     * Parse from a PDF file (PdfExtractor + parse).
     *
     * @param  string  $path
     * @return array<int, array<string, int|null>>
     */
    public static function parseFile(string $path): array
    {
        $text = PdfExtractor::extract($path);
        return self::parse($text);
    }

    /**
     * Parse raw text and return a JSON string.
     */
    public static function toJson(string $text): string
    {
        return json_encode(self::parse($text), JSON_PRETTY_PRINT);
    }

    /**
     * Parse a PDF file and return a JSON string.
     */
    public static function fileToJson(string $path): string
    {
        return json_encode(self::parseFile($path), JSON_PRETTY_PRINT);
    }

    // -------------------------------------------------------------------------

    /**
     * Build the result object for one salary grade row.
     *
     * @param  int        $grade
     * @param  array<int> $steps
     * @return array<string, int|null>
     */
    private static function toObject(int $grade, array $steps): array
    {
        $obj = ['salary_grade' => $grade];

        foreach ($steps as $i => $amount) {
            $obj['step_' . ($i + 1)] = $amount;
        }

        // Pad missing steps with null so every row always has step_1 … step_8
        for ($i = count($steps) + 1; $i <= 8; $i++) {
            $obj['step_' . $i] = null;
        }

        return $obj;
    }

    /**
     * Normalize raw PDF text into clean lines.
     */
    private static function normalizeLines(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        // Strip thousand separators: 14,061 -> 14061
        $text = preg_replace('/\b(\d{1,3}),(\d{3})\b/', '$1$2', $text);
        return explode("\n", $text);
    }

    /**
     * Try to parse a single line as a salary grade row.
     *
     * Valid row: starts with grade (1–30), followed by 1–8 ascending
     * salary amounts in the range PHP 10,000–999,999.
     *
     * @return array{0: int, 1: array<int>}|null
     */
    private static function parseLine(string $line): ?array
    {
        $line = trim($line);
        if ($line === '') {
            return null;
        }

        if (! preg_match_all('/\b(\d+)\b/', $line, $matches)) {
            return null;
        }

        $numbers = array_map('intval', $matches[1]);

        $grade = $numbers[0];
        if ($grade < 1 || $grade > 30) {
            return null;
        }

        $steps = [];
        foreach (array_slice($numbers, 1) as $n) {
            if ($n >= 10000 && $n <= 999999) {
                $steps[] = $n;
            }
        }

        if (count($steps) < 1 || count($steps) > 8) {
            return null;
        }

        // Steps must be ascending
        for ($i = 1; $i < count($steps); $i++) {
            if ($steps[$i] < $steps[$i - 1]) {
                return null;
            }
        }

        return [$grade, $steps];
    }
}
