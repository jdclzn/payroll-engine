<?php

namespace Jdclzn\PayrollEngine\Data;

use Money\Money;

final readonly class PayrollLine
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $type,
        public string $label,
        public Money $amount,
        public bool $taxable = false,
        public array $metadata = [],
    ) {}
}
