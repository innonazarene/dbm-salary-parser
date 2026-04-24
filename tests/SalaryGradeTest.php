<?php

namespace PhSalaryGrade\Tests;

use PhSalaryGrade\SalaryGrade;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class SalaryGradeTest extends TestCase
{
    public function test_get_returns_correct_salary(): void
    {
        $this->assertSame(14061, SalaryGrade::get(1, 1));
        $this->assertSame(14862, SalaryGrade::get(1, 8));
        $this->assertSame(203200, SalaryGrade::get(30, 1));
        $this->assertSame(226319, SalaryGrade::get(30, 8));
    }

    public function test_get_defaults_to_step_one(): void
    {
        $this->assertSame(SalaryGrade::get(5, 1), SalaryGrade::get(5));
    }

    public function test_get_throws_on_invalid_grade(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SalaryGrade::get(0);
    }

    public function test_get_throws_on_grade_above_max(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SalaryGrade::get(31);
    }

    public function test_get_throws_on_invalid_step(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SalaryGrade::get(1, 9);
    }

    public function test_get_steps_returns_all_eight_steps(): void
    {
        $steps = SalaryGrade::getSteps(10);

        $this->assertCount(8, $steps);
        $this->assertArrayHasKey(1, $steps);
        $this->assertArrayHasKey(8, $steps);
        $this->assertSame(25586, $steps[1]);
        $this->assertSame(27050, $steps[8]);
    }

    public function test_all_returns_30_grades(): void
    {
        $all = SalaryGrade::all();

        $this->assertCount(30, $all);
        foreach ($all as $grade => $steps) {
            $this->assertCount(8, $steps);
        }
    }

    public function test_find_returns_correct_grade_and_step(): void
    {
        $result = SalaryGrade::find(14061);

        $this->assertNotNull($result);
        $this->assertSame(1, $result['grade']);
        $this->assertSame(1, $result['step']);
    }

    public function test_find_returns_null_for_unknown_amount(): void
    {
        $this->assertNull(SalaryGrade::find(99999));
    }

    public function test_range_returns_correct_results(): void
    {
        $results = SalaryGrade::range(14000, 14500);

        $this->assertNotEmpty($results);
        foreach ($results as $item) {
            $this->assertGreaterThanOrEqual(14000, $item['amount']);
            $this->assertLessThanOrEqual(14500, $item['amount']);
        }
    }

    public function test_min_and_max(): void
    {
        $this->assertSame(14061, SalaryGrade::min());
        $this->assertSame(226319, SalaryGrade::max());
    }
}
