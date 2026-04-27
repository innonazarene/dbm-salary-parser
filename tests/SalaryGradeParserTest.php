<?php

namespace PhSalaryGrade\Tests;

use PhSalaryGrade\SalaryGradeParser;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class SalaryGradeParserTest extends TestCase
{
    private string $sampleText = <<<TEXT
        SALARY SCHEDULE
        National Budget Circular No. 601

        Salary Grade  Step 1   Step 2   Step 3   Step 4   Step 5   Step 6   Step 7   Step 8
        1             14061    14164    14278    14393    14509    14626    14743    14862
        2             14925    15035    15146    15258    15371    15484    15599    15714
        3             15852    15971    16088    16208    16329    16448    16571    16693
        10            25586    25790    25996    26203    26412    26623    26835    27050
        15            40208    40604    41006    41413    41824    42241    42662    43090
        30            203200   206401   209558   212766   216022   219434   222797   226319
        TEXT;

    public function test_parse_returns_array_of_objects(): void
    {
        $result = SalaryGradeParser::parse($this->sampleText);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('salary_grade', $result[0]);
        $this->assertArrayHasKey('step_1', $result[0]);
        $this->assertArrayHasKey('step_8', $result[0]);
    }

    public function test_parse_extracts_correct_values(): void
    {
        $result = SalaryGradeParser::parse($this->sampleText);

        $grade1 = $result[0];
        $this->assertSame(1,     $grade1['salary_grade']);
        $this->assertSame(14061, $grade1['step_1']);
        $this->assertSame(14862, $grade1['step_8']);

        $grade30 = $result[count($result) - 1];
        $this->assertSame(30,     $grade30['salary_grade']);
        $this->assertSame(203200, $grade30['step_1']);
        $this->assertSame(226319, $grade30['step_8']);
    }

    public function test_parse_sorts_by_salary_grade_ascending(): void
    {
        $result = SalaryGradeParser::parse($this->sampleText);
        $grades = array_column($result, 'salary_grade');

        for ($i = 1; $i < count($grades); $i++) {
            $this->assertGreaterThan($grades[$i - 1], $grades[$i]);
        }
    }

    public function test_parse_pads_missing_steps_with_null(): void
    {
        $text   = "5  17866  18000  18133  18267";
        $result = SalaryGradeParser::parse($text);

        $this->assertSame(17866, $result[0]['step_1']);
        $this->assertSame(18000, $result[0]['step_2']);
        $this->assertSame(18133, $result[0]['step_3']);
        $this->assertSame(18267, $result[0]['step_4']);
        $this->assertNull($result[0]['step_5']);
        $this->assertNull($result[0]['step_8']);
    }

    public function test_parse_handles_comma_formatted_numbers(): void
    {
        $text   = "1  14,061  14,164  14,278  14,393  14,509  14,626  14,743  14,862";
        $result = SalaryGradeParser::parse($text);

        $this->assertSame(14061, $result[0]['step_1']);
        $this->assertSame(14862, $result[0]['step_8']);
    }

    public function test_parse_deduplicates_repeated_grades(): void
    {
        $text = <<<TEXT
            1  14061  14164  14278  14393  14509  14626  14743  14862
            1  99999  99999  99999  99999  99999  99999  99999  99999
            TEXT;

        $result = SalaryGradeParser::parse($text);

        $this->assertCount(1, $result);
        $this->assertSame(14061, $result[0]['step_1']);
    }

    public function test_parse_ignores_non_salary_lines(): void
    {
        $text = <<<TEXT
            Republic of the Philippines
            Department of Budget and Management
            Effective January 1, 2026
            1  14061  14164  14278  14393  14509  14626  14743  14862
            Page 1 of 5
            TEXT;

        $result = SalaryGradeParser::parse($text);

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['salary_grade']);
    }

    public function test_parse_throws_when_no_data_found(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SalaryGradeParser::parse("This document has no salary table.");
    }

    public function test_to_json_returns_valid_json(): void
    {
        $json   = SalaryGradeParser::toJson($this->sampleText);
        $decoded = json_decode($json, true);

        $this->assertNotNull($decoded);
        $this->assertIsArray($decoded);
        $this->assertSame(1, $decoded[0]['salary_grade']);
        $this->assertSame(14061, $decoded[0]['step_1']);
    }
}
