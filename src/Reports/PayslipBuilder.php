<?php

namespace QuillBytes\PayrollEngine\Reports;

use QuillBytes\PayrollEngine\Data\PayrollIssue;
use QuillBytes\PayrollEngine\Data\PayrollLine;
use QuillBytes\PayrollEngine\Data\PayrollResult;
use QuillBytes\PayrollEngine\Support\MoneyHelper;

final class PayslipBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(PayrollResult $result): array
    {
        return [
            'company' => [
                'name' => $result->company->name,
                'logo' => $result->company->logo,
            ],
            'period' => [
                'key' => $result->period->key,
                'start_date' => $result->period->startDate->toDateString(),
                'end_date' => $result->period->endDate->toDateString(),
                'release_date' => $result->period->releaseDate->toDateString(),
                'run_type' => $result->period->runType,
            ],
            'employee' => [
                'employee_number' => $result->employee->employeeNumber,
                'full_name' => $result->employee->fullName,
                'email' => $result->employee->email,
                'position' => $result->employee->position,
                'department' => $result->employee->allocation->department,
                'account_number' => $result->employee->payrollDetails->accountNumber,
                'bank' => $result->employee->payrollDetails->bank,
                'branch' => $result->employee->allocation->branch,
            ],
            'allocation' => [
                'project_code' => $result->employee->allocation->projectCode,
                'project_name' => $result->employee->allocation->projectName,
                'cost_center' => $result->employee->allocation->costCenter,
                'branch' => $result->employee->allocation->branch,
                'department' => $result->employee->allocation->department,
                'vessel' => $result->employee->allocation->vessel,
                'dimensions' => $result->employee->allocation->dimensions,
            ],
            'rates' => [
                'monthly_basic_salary' => MoneyHelper::toFloat($result->rates->monthlyBasicSalary),
                'scheduled_basic_pay' => MoneyHelper::toFloat($result->rates->scheduledBasicPay),
                'daily_rate' => MoneyHelper::toFloat($result->rates->dailyRate),
                'hourly_rate' => MoneyHelper::toFloat($result->rates->hourlyRate),
                'fixed_per_day_applied' => $result->rates->fixedPerDayApplied,
            ],
            'earnings' => $this->serializeLines($result->earnings),
            'employee_contributions' => $this->serializeLines($result->employeeContributions),
            'deductions' => $this->serializeLines($result->deductions),
            'separate_payouts' => $this->serializeLines($result->separatePayouts),
            'issues' => $this->serializeIssues($result->issues),
            'audit' => $result->audit,
            'totals' => [
                'gross_pay' => MoneyHelper::toFloat($result->grossPay),
                'taxable_income' => MoneyHelper::toFloat($result->taxableIncome),
                'net_pay' => MoneyHelper::toFloat($result->netPay),
                'take_home_pay' => MoneyHelper::toFloat($result->takeHomePay),
                'bonus_tax_withheld' => MoneyHelper::toFloat($result->bonusTaxWithheld),
            ],
        ];
    }

    /**
     * @param  array<int, PayrollLine>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function serializeLines(array $lines): array
    {
        return array_map(
            static fn ($line) => [
                'type' => $line->type,
                'label' => $line->label,
                'amount' => MoneyHelper::toFloat($line->amount),
                'taxable' => $line->taxable,
                'metadata' => $line->metadata,
            ],
            $lines
        );
    }

    /**
     * @param  array<int, PayrollIssue>  $issues
     * @return array<int, array<string, mixed>>
     */
    private function serializeIssues(array $issues): array
    {
        return array_map(
            static fn (PayrollIssue $issue) => [
                'code' => $issue->code,
                'message' => $issue->message,
                'severity' => $issue->severity,
                'metadata' => $issue->metadata,
            ],
            $issues
        );
    }
}
