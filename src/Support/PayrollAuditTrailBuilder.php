<?php

namespace QuillBytes\PayrollEngine\Support;

use QuillBytes\PayrollEngine\Data\CompanyProfile;
use QuillBytes\PayrollEngine\Data\EmployeeProfile;
use QuillBytes\PayrollEngine\Data\PayrollInput;
use QuillBytes\PayrollEngine\Data\PayrollIssue;
use QuillBytes\PayrollEngine\Data\PayrollLine;
use QuillBytes\PayrollEngine\Data\PayrollResult;

final class PayrollAuditTrailBuilder
{
    /**
     * @param  array<string, string>  $strategies
     * @param  array<int, string>  $policies
     * @return array<string, mixed>
     */
    public function build(
        CompanyProfile $company,
        EmployeeProfile $employee,
        PayrollInput $input,
        PayrollResult $result,
        array $strategies,
        array $policies,
    ): array {
        return [
            'input_normalization' => [
                'company' => CompanyProfile::class,
                'employee' => EmployeeProfile::class,
                'input' => PayrollInput::class,
            ],
            'applied_rules' => [
                'run_type' => $result->period->runType,
                'strategies' => $strategies,
                'policies' => $policies,
                'tax_strategy' => $company->taxStrategy->value,
                'pagibig_mode' => $company->pagIbigContributionMode->value,
                'pagibig_schedule' => $company->pagIbigContributionSchedule->value,
                'manual_overtime_pay' => $company->manualOvertimePay,
                'fixed_per_day_rate' => $company->fixedPerDayRate,
                'separate_allowance_payout' => $company->separateAllowancePayout,
            ],
            'rates_used' => [
                'monthly_basic_salary' => MoneyHelper::toFloat($result->rates->monthlyBasicSalary),
                'scheduled_basic_pay' => MoneyHelper::toFloat($result->rates->scheduledBasicPay),
                'daily_rate' => MoneyHelper::toFloat($result->rates->dailyRate),
                'hourly_rate' => MoneyHelper::toFloat($result->rates->hourlyRate),
                'fixed_per_day_applied' => $result->rates->fixedPerDayApplied,
            ],
            'basis_amounts' => [
                'gross_pay' => MoneyHelper::toFloat($result->grossPay),
                'taxable_income' => MoneyHelper::toFloat($result->taxableIncome),
                'net_pay' => MoneyHelper::toFloat($result->netPay),
                'take_home_pay' => MoneyHelper::toFloat($result->takeHomePay),
                'earnings' => $this->lineBreakdowns($result->earnings),
                'deductions' => $this->lineBreakdowns($result->deductions),
                'employee_contributions' => $this->lineBreakdowns($result->employeeContributions),
                'employer_contributions' => $this->lineBreakdowns($result->employerContributions),
                'separate_payouts' => $this->lineBreakdowns($result->separatePayouts),
            ],
            'formulas' => [
                'scheduled_basic_pay' => $result->period->isFinalSettlementRun()
                    ? 'regular_scheduled_basic_pay * payable_days / covered_days'
                    : ($result->period->isOffCycleRun() ? '0 for off-cycle runs without scheduled basic pay' : 'monthly_basic_salary * 12 / periods_per_year'),
                'daily_rate' => $result->rates->fixedPerDayApplied
                    ? 'employee fixed daily rate'
                    : 'monthly_basic_salary * 12 / eemr_factor',
                'hourly_rate' => $employee->compensation->fixedHourlyRate !== null
                    ? 'employee fixed hourly rate'
                    : 'daily_rate / hours_per_day',
                'tax_strategy' => $company->taxStrategy->value,
            ],
            'warnings_exceptions' => $this->issues($result->issues),
        ];
    }

    /**
     * @param  array<int, PayrollLine>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function lineBreakdowns(array $lines): array
    {
        return array_map(
            static fn (PayrollLine $line) => [
                'label' => $line->label,
                'amount' => MoneyHelper::toFloat($line->amount),
                'taxable' => $line->taxable,
                'source' => $line->metadata['source'] ?? null,
                'applied_rule' => $line->metadata['applied_rule'] ?? null,
                'formula' => $line->metadata['formula'] ?? null,
                'basis' => $line->metadata['basis'] ?? [],
            ],
            $lines,
        );
    }

    /**
     * @param  array<int, PayrollIssue>  $issues
     * @return array<int, array<string, mixed>>
     */
    private function issues(array $issues): array
    {
        return array_map(
            static fn (PayrollIssue $issue) => [
                'code' => $issue->code,
                'message' => $issue->message,
                'severity' => $issue->severity,
                'metadata' => $issue->metadata,
            ],
            $issues,
        );
    }
}
