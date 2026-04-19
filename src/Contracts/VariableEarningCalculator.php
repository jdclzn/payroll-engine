<?php

namespace QuillBytes\PayrollEngine\Contracts;

use QuillBytes\PayrollEngine\Data\CompanyProfile;
use QuillBytes\PayrollEngine\Data\EmployeeProfile;
use QuillBytes\PayrollEngine\Data\PayrollInput;
use QuillBytes\PayrollEngine\Data\PayrollLine;
use QuillBytes\PayrollEngine\Data\RateSnapshot;

interface VariableEarningCalculator
{
    /**
     * @return array<int, PayrollLine>
     */
    public function calculate(
        CompanyProfile $company,
        EmployeeProfile $employee,
        PayrollInput $input,
        RateSnapshot $rates,
    ): array;
}
