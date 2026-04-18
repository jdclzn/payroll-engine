<?php

namespace Jdclzn\PayrollEngine\Data;

use Money\Money;

final class LoanDeduction extends Deduction
{
    public function __construct(
        string $label,
        Money $amount,
        public readonly ?string $loanReference = null,
    ) {
        parent::__construct($label, $amount);
    }
}
