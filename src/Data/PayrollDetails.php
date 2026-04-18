<?php

namespace Jdclzn\PayrollEngine\Data;

final readonly class PayrollDetails
{
    public function __construct(
        public ?string $accountNumber,
        public ?string $bank,
        public ?string $branch,
    ) {
    }
}
