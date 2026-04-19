<?php

namespace QuillBytes\PayrollEngine\Data;

use Money\Money;

final readonly class OvertimeEntry
{
    public function __construct(
        public string $type,
        public float $hours,
        public ?float $multiplier = null,
        public bool $taxable = true,
        public bool $nightDifferential = false,
        public ?Money $manualAmount = null,
    ) {}
}
