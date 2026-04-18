<?php

namespace Jdclzn\PayrollEngine\Contracts;

use Jdclzn\PayrollEngine\Data\CompanyProfile;
use Jdclzn\PayrollEngine\Data\PayrollInput;
use Jdclzn\PayrollEngine\Data\PayrollLine;
use Jdclzn\PayrollEngine\Data\RateSnapshot;

interface OvertimeCalculator
{
    /**
     * @return array<int, PayrollLine>
     */
    public function calculate(CompanyProfile $company, PayrollInput $input, RateSnapshot $rates): array;
}
