<?php

namespace Jdclzn\PayrollEngine\Reports;

use Jdclzn\PayrollEngine\Data\PayrollResult;
use Jdclzn\PayrollEngine\Support\MoneyHelper;

final class PayrollRegisterBuilder
{
    /**
     * @param  array<int, PayrollResult>  $results
     * @return array<int, array<string, mixed>>
     */
    public function build(array $results): array
    {
        return array_map(
            static fn (PayrollResult $result) => [
                'employee_number' => $result->employee->employeeNumber,
                'employee_name' => $result->employee->fullName,
                'project_code' => $result->employee->allocation->projectCode,
                'project_name' => $result->employee->allocation->projectName,
                'cost_center' => $result->employee->allocation->costCenter,
                'account_number' => $result->employee->payrollDetails->accountNumber,
                'bank' => $result->employee->payrollDetails->bank,
                'branch' => $result->employee->allocation->branch,
                'department' => $result->employee->allocation->department,
                'vessel' => $result->employee->allocation->vessel,
                'gross_pay' => MoneyHelper::toFloat($result->grossPay),
                'taxable_income' => MoneyHelper::toFloat($result->taxableIncome),
                'net_pay' => MoneyHelper::toFloat($result->netPay),
                'take_home_pay' => MoneyHelper::toFloat($result->takeHomePay),
                'bonus_tax_withheld' => MoneyHelper::toFloat($result->bonusTaxWithheld),
                'release_date' => $result->period->releaseDate->toDateString(),
                'run_type' => $result->period->runType,
                'issues' => array_map(static fn ($issue) => $issue->code, $result->issues),
            ],
            $results
        );
    }
}
