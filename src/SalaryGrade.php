<?php

namespace PhSalaryGrade;

class SalaryGrade
{
    /**
     * Salary schedule based on NBC No. 601 (2026)
     * Keys: Salary Grade (1–30)
     * Values: array of 8 steps
     */
    public const SCHEDULE = [
        1  => [14061, 14164, 14278, 14393, 14509, 14626, 14743, 14862],
        2  => [14925, 15035, 15146, 15258, 15371, 15484, 15599, 15714],
        3  => [15852, 15971, 16088, 16208, 16329, 16448, 16571, 16693],
        4  => [16833, 16958, 17084, 17209, 17337, 17464, 17594, 17724],
        5  => [17866, 18000, 18133, 18267, 18401, 18538, 18676, 18813],
        6  => [18957, 19098, 19239, 19383, 19526, 19670, 19816, 19963],
        7  => [20110, 20258, 20408, 20560, 20711, 20865, 21019, 21175],
        8  => [21448, 21642, 21839, 22035, 22234, 22435, 22638, 22843],
        9  => [23226, 23411, 23599, 23788, 23978, 24170, 24364, 24558],
        10 => [25586, 25790, 25996, 26203, 26412, 26623, 26835, 27050],
        11 => [30024, 30308, 30597, 30889, 31185, 31486, 31790, 32099],
        12 => [32245, 32529, 32817, 33108, 33403, 33702, 34004, 34310],
        13 => [34421, 34733, 35049, 35369, 35694, 36022, 36354, 36691],
        14 => [37024, 37384, 37749, 38118, 38491, 38869, 39252, 39640],
        15 => [40208, 40604, 41006, 41413, 41824, 42241, 42662, 43090],
        16 => [43560, 43996, 44438, 44885, 45338, 45796, 46261, 46730],
        17 => [47247, 47727, 48213, 48705, 49203, 49708, 50218, 50735],
        18 => [51304, 51832, 52367, 52907, 53456, 54010, 54572, 55140],
        19 => [56390, 57165, 57953, 58753, 59567, 60394, 61235, 62089],
        20 => [62967, 63842, 64732, 65637, 66557, 67479, 68409, 69342],
        21 => [70013, 71000, 72004, 73024, 74061, 75115, 76151, 77239],
        22 => [78162, 79277, 80411, 81564, 82735, 83887, 85096, 86324],
        23 => [87315, 88574, 89855, 91163, 92592, 94043, 95518, 96955],
        24 => [98185, 99721, 101283, 102871, 104483, 106123, 107739, 109431],
        25 => [111727, 113476, 115254, 117062, 118899, 120766, 122664, 124591],
        26 => [126252, 128228, 130238, 132280, 134356, 136465, 138608, 140788],
        27 => [142663, 144897, 147169, 149407, 151752, 153850, 156267, 158723],
        28 => [160469, 162988, 165548, 167994, 170634, 173320, 175803, 178572],
        29 => [180492, 183332, 186218, 189151, 192131, 194797, 197870, 200993],
        30 => [203200, 206401, 209558, 212766, 216022, 219434, 222797, 226319],
    ];

    public const MIN_GRADE = 1;
    public const MAX_GRADE = 30;
    public const MIN_STEP  = 1;
    public const MAX_STEP  = 8;

    /**
     * Get the monthly salary for a given grade and step.
     *
     * @param  int  $grade  Salary grade (1–30)
     * @param  int  $step   Step increment (1–8), defaults to 1
     * @return int
     *
     * @throws \InvalidArgumentException
     */
    public static function get(int $grade, int $step = 1): int
    {
        self::validateGrade($grade);
        self::validateStep($step);

        return self::SCHEDULE[$grade][$step - 1];
    }

    /**
     * Get all 8 steps for a given salary grade.
     *
     * @param  int  $grade
     * @return array<int, int>  Step-indexed array (keys 1–8)
     *
     * @throws \InvalidArgumentException
     */
    public static function getSteps(int $grade): array
    {
        self::validateGrade($grade);

        $steps = [];
        foreach (self::SCHEDULE[$grade] as $index => $amount) {
            $steps[$index + 1] = $amount;
        }

        return $steps;
    }

    /**
     * Get the full salary schedule as a nested array.
     *
     * @return array<int, array<int, int>>  Grade => [Step => Amount]
     */
    public static function all(): array
    {
        $result = [];
        foreach (self::SCHEDULE as $grade => $steps) {
            foreach ($steps as $index => $amount) {
                $result[$grade][$index + 1] = $amount;
            }
        }

        return $result;
    }

    /**
     * Find which salary grade and step a given amount belongs to.
     * Returns the first exact match, or null if not found.
     *
     * @param  int  $amount
     * @return array{grade: int, step: int}|null
     */
    public static function find(int $amount): ?array
    {
        foreach (self::SCHEDULE as $grade => $steps) {
            foreach ($steps as $index => $salary) {
                if ($salary === $amount) {
                    return ['grade' => $grade, 'step' => $index + 1];
                }
            }
        }

        return null;
    }

    /**
     * Get all salary grades and steps within a salary range (inclusive).
     *
     * @param  int  $min
     * @param  int  $max
     * @return array<int, array{grade: int, step: int, amount: int}>
     */
    public static function range(int $min, int $max): array
    {
        $results = [];
        foreach (self::SCHEDULE as $grade => $steps) {
            foreach ($steps as $index => $amount) {
                if ($amount >= $min && $amount <= $max) {
                    $results[] = [
                        'grade'  => $grade,
                        'step'   => $index + 1,
                        'amount' => $amount,
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Get the minimum salary (Grade 1, Step 1).
     */
    public static function min(): int
    {
        return self::SCHEDULE[self::MIN_GRADE][0];
    }

    /**
     * Get the maximum salary (Grade 30, Step 8).
     */
    public static function max(): int
    {
        return self::SCHEDULE[self::MAX_GRADE][self::MAX_STEP - 1];
    }

    // -------------------------------------------------------------------------
    // Validation helpers
    // -------------------------------------------------------------------------

    private static function validateGrade(int $grade): void
    {
        if ($grade < self::MIN_GRADE || $grade > self::MAX_GRADE) {
            throw new \InvalidArgumentException(
                "Salary grade must be between " . self::MIN_GRADE . " and " . self::MAX_GRADE . ", got {$grade}."
            );
        }
    }

    private static function validateStep(int $step): void
    {
        if ($step < self::MIN_STEP || $step > self::MAX_STEP) {
            throw new \InvalidArgumentException(
                "Step must be between " . self::MIN_STEP . " and " . self::MAX_STEP . ", got {$step}."
            );
        }
    }
}
