<?php

namespace QuillBytes\PayrollEngine\Contracts;

use QuillBytes\PayrollEngine\Data\CompanyProfile;
use QuillBytes\PayrollEngine\Data\EmployeeProfile;
use QuillBytes\PayrollEngine\Data\PayrollInput;
use QuillBytes\PayrollEngine\Data\PayrollResult;

interface PayrollWorkflow
{
    public function calculate(CompanyProfile $company, EmployeeProfile $employee, PayrollInput $input): PayrollResult;
}
