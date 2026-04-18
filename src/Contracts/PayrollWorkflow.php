<?php

namespace Jdclzn\PayrollEngine\Contracts;

use Jdclzn\PayrollEngine\Data\CompanyProfile;
use Jdclzn\PayrollEngine\Data\EmployeeProfile;
use Jdclzn\PayrollEngine\Data\PayrollInput;
use Jdclzn\PayrollEngine\Data\PayrollResult;

interface PayrollWorkflow
{
    public function calculate(CompanyProfile $company, EmployeeProfile $employee, PayrollInput $input): PayrollResult;
}
