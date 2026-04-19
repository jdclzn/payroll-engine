<?php

namespace QuillBytes\PayrollEngine\Data;

use Money\Money;

final readonly class Adjustment
{
    public function __construct(
        public string $label,
        public Money $amount,
        public bool $taxable = true,
        public bool $separatePayout = false,
    ) {}
}
