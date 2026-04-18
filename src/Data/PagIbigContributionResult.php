<?php

namespace Jdclzn\PayrollEngine\Data;

final readonly class PagIbigContributionResult
{
    /**
     * @param  array<int, PayrollLine>  $separateDeductions
     */
    public function __construct(
        public PayrollLine $employee,
        public PayrollLine $employer,
        public array $separateDeductions = [],
    ) {
    }
}
