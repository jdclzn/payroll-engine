<?php

namespace QuillBytes\PayrollEngine\Contracts;

use QuillBytes\PayrollEngine\Data\CompanyProfile;
use QuillBytes\PayrollEngine\Data\EmployeeProfile;
use QuillBytes\PayrollEngine\Data\PagIbigContributionResult;
use QuillBytes\PayrollEngine\Data\PayrollInput;

interface PagIbigContributionCalculator
{
    public function calculate(
        CompanyProfile $company,
        EmployeeProfile $employee,
        PayrollInput $input,
        int $periodDivisor = 1,
    ): PagIbigContributionResult;
}
