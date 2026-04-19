<?php

namespace QuillBytes\PayrollEngine\Enums;

enum PayrollFrequency: string
{
    case Monthly = 'monthly';
    case SemiMonthly = 'semi_monthly';
    case Weekly = 'weekly';

    public function periodsPerYear(): int
    {
        return match ($this) {
            self::Monthly => 12,
            self::SemiMonthly => 24,
            self::Weekly => 52,
        };
    }
}
