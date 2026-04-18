<?php

namespace Jdclzn\PayrollEngine;

use Jdclzn\PayrollEngine\Data\PayrollResult;
use Jdclzn\PayrollEngine\Data\PayrollRun;
use Illuminate\Support\Facades\Facade;

/**
 * Facade entrypoint for the payroll engine service.
 *
 * Available facade methods:
 * compute:
 *   Compute a single employee payroll result for a given company and payroll input.
 *
 * run:
 *   Build a payroll run for multiple employees for the supplied payroll period.
 *
 * payslip:
 *   Transform one computed payroll result into a payslip-ready payload array.
 *
 * payrollRegister:
 *   Transform computed payroll results into a payroll register payload array.
 *
 * allocationSummary:
 *   Group payroll results by project, branch, department, vessel, cost center,
 *   or another allocation dimension for allocation-ready outputs.
 *
 * retroAdjustmentInput:
 *   Compare an original payroll result against a recomputed historical result
 *   and build a difference-only adjustment input for release in a retro or
 *   adjustment payroll run.
 *
 * generatePayrollFiles:
 *   Generate processed payroll register/export payloads for a payroll run.
 *
 * generatePayslips:
 *   Generate payslip payloads for a payroll run on or after the configured release date.
 *
 * @see PayrollEngine
 */
class PayrollEngineFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PayrollEngine::class;
    }
}
