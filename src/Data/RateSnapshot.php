<?php

namespace QuillBytes\PayrollEngine\Data;

use Money\Money;

final readonly class RateSnapshot
{
    public function __construct(
        public Money $monthlyBasicSalary,
        public Money $scheduledBasicPay,
        public Money $dailyRate,
        public Money $hourlyRate,
        public bool $fixedPerDayApplied,
    ) {}
}
