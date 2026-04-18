<?php

namespace Jdclzn\PayrollEngine\Data;

use Money\Money;

class Deduction
{
    public function __construct(
        public readonly string $label,
        public readonly Money $amount,
    ) {
    }
}
