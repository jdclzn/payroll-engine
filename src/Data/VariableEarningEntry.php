<?php

namespace QuillBytes\PayrollEngine\Data;

use Money\Money;

final readonly class VariableEarningEntry
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $type,
        public string $label,
        public Money $amount,
        public bool $taxable = true,
        public array $metadata = [],
    ) {}
}
