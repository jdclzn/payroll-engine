<?php

namespace Jdclzn\PayrollEngine\Contracts;

use Jdclzn\PayrollEngine\Data\CompanyProfile;
use Jdclzn\PayrollEngine\Data\EmployeeProfile;
use Jdclzn\PayrollEngine\Data\PagIbigContributionResult;
use Jdclzn\PayrollEngine\Data\PayrollInput;

interface PagIbigContributionCalculator
{
    public function calculate(
        CompanyProfile $company,
        EmployeeProfile $employee,
        PayrollInput $input,
        int $periodDivisor = 1,
    ): PagIbigContributionResult;
}
