<?php

namespace Jdclzn\PayrollEngine\Contracts;

use Jdclzn\PayrollEngine\Data\CompanyProfile;
use Jdclzn\PayrollEngine\Data\EmployeeProfile;
use Jdclzn\PayrollEngine\Data\PayrollPeriod;
use Jdclzn\PayrollEngine\Data\RateSnapshot;

/**
 * Strategy contract for resolving payroll rates for a specific client flow.
 *
 * Implement this contract when a company needs custom logic for scheduled basic
 * pay, daily rate, or hourly rate while keeping the rest of the payroll engine
 * unchanged.
 */
interface RateCalculator
{
    /**
     * Calculates the normalized rate snapshot for the current payroll period.
     */
    public function calculate(CompanyProfile $company, EmployeeProfile $employee, PayrollPeriod $period): RateSnapshot;
}
