<?php

namespace QuillBytes\PayrollEngine\Data;

use QuillBytes\PayrollEngine\Enums\PayrollFrequency;

final readonly class PayrollSchedule
{
    public function __construct(
        public PayrollFrequency $frequency,
        public int $hoursPerDay,
        public int $workDaysPerYear,
        public int $releaseLeadDays,
    ) {}

    public function periodsPerYear(): int
    {
        return $this->frequency->periodsPerYear();
    }
}
