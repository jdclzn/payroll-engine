<?php

namespace QuillBytes\PayrollEngine\Tests;

use QuillBytes\PayrollEngine\Contracts\PayrollWorkflow as PayrollWorkflowContract;
use QuillBytes\PayrollEngine\Data\CompanyProfile;
use QuillBytes\PayrollEngine\Data\EmployeeProfile;
use QuillBytes\PayrollEngine\Data\PayrollInput;
use QuillBytes\PayrollEngine\Data\PayrollLine;
use QuillBytes\PayrollEngine\Data\PayrollResult;
use QuillBytes\PayrollEngine\Data\RateSnapshot;
use QuillBytes\PayrollEngine\PayrollEngine;
use QuillBytes\PayrollEngine\Support\MoneyHelper;

function extensionScenarioEngine(array $config = []): PayrollEngine
{
    return new PayrollEngine($config);
}

/**
 * @return array<string, mixed>
 */
function extensionScenarioCompany(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Extension Scenario Client',
        'client_code' => 'base',
        'prepared_by' => ['payroll.preparer'],
        'approvers' => ['chief.approver'],
        'administrators' => ['admin.user'],
        'payroll_schedules' => [
            [
                'pay_date' => '15',
                'period_start' => '1',
                'period_end' => '15',
            ],
        ],
    ], $overrides);
}

/**
 * @return array<string, mixed>
 */
function extensionScenarioEmployee(array $overrides = []): array
{
    return array_replace_recursive([
        'employee_number' => 'EMP-EXT-001',
        'full_name' => 'Extension Scenario Employee',
        'employment_status' => 'active',
        'date_hired' => '2024-01-10',
        'department' => 'Finance',
        'email' => 'extension.employee@example.com',
        'monthly_basic_salary' => 30000,
        'tax_shield_amount_for_bonuses' => 90000,
        'tin' => '123-456-789',
        'sss_number' => '11-1111111-1',
        'hdmf_number' => '123456789012',
        'phic_number' => '12-345678901-2',
        'account_number' => '001234567890',
        'bank' => 'Payroll Bank',
        'branch' => 'Makati Main',
    ], $overrides);
}

function countrySpecificExtensionWorkflow(): PayrollWorkflowContract
{
    return new class implements PayrollWorkflowContract
    {
        public function calculate(CompanyProfile $company, EmployeeProfile $employee, PayrollInput $input): PayrollResult
        {
            $grossPay = MoneyHelper::fromNumeric(5000, $employee->compensation->monthlyBasicSalary);
            $rates = new RateSnapshot(
                monthlyBasicSalary: $employee->compensation->monthlyBasicSalary,
                scheduledBasicPay: $grossPay,
                dailyRate: MoneyHelper::fromNumeric(250, $grossPay),
                hourlyRate: MoneyHelper::fromNumeric(31.25, $grossPay),
                fixedPerDayApplied: false,
            );
            $earnings = [
                new PayrollLine('earning', 'Country Base Pay', $grossPay, true, ['country_rule' => 'custom']),
            ];
            $employeeContributions = [
                new PayrollLine('employee_contribution', 'Social Insurance', MoneyHelper::fromNumeric(210, $grossPay)),
                new PayrollLine('employee_contribution', 'National Pension', MoneyHelper::fromNumeric(155, $grossPay)),
            ];
            $employerContributions = [
                new PayrollLine('employer_contribution', 'Employer Social Insurance', MoneyHelper::fromNumeric(210, $grossPay)),
                new PayrollLine('employer_contribution', 'Employer Pension', MoneyHelper::fromNumeric(155, $grossPay)),
            ];
            $deductions = [
                new PayrollLine('deduction', 'State Income Tax', MoneyHelper::fromNumeric(325, $grossPay)),
            ];
            $employeeShare = MoneyHelper::sum(array_map(static fn (PayrollLine $line) => $line->amount, $employeeContributions));
            $deductionTotal = MoneyHelper::sum(array_map(static fn (PayrollLine $line) => $line->amount, $deductions));
            $netPay = $grossPay->subtract($employeeShare)->subtract($deductionTotal);

            return new PayrollResult(
                company: $company,
                employee: $employee,
                period: $input->period,
                rates: $rates,
                earnings: $earnings,
                deductions: $deductions,
                employeeContributions: $employeeContributions,
                employerContributions: $employerContributions,
                separatePayouts: [],
                grossPay: $grossPay,
                taxableIncome: $grossPay,
                netPay: $netPay,
                takeHomePay: $netPay,
                bonusTaxWithheld: MoneyHelper::zero($grossPay),
            );
        }
    };
}

function companyPolicyExtensionWorkflow(): PayrollWorkflowContract
{
    return new class implements PayrollWorkflowContract
    {
        public function calculate(CompanyProfile $company, EmployeeProfile $employee, PayrollInput $input): PayrollResult
        {
            $basicPay = MoneyHelper::fromNumeric(15000, $employee->compensation->monthlyBasicSalary);
            $mobilityAllowance = MoneyHelper::fromNumeric(1200, $basicPay);
            $holidayPremium = MoneyHelper::fromNumeric(800, $basicPay);
            $rates = new RateSnapshot(
                monthlyBasicSalary: $employee->compensation->monthlyBasicSalary,
                scheduledBasicPay: $basicPay,
                dailyRate: MoneyHelper::fromNumeric(750, $basicPay),
                hourlyRate: MoneyHelper::fromNumeric(93.75, $basicPay),
                fixedPerDayApplied: false,
            );
            $earnings = [
                new PayrollLine('earning', 'Basic Pay', $basicPay, true),
                new PayrollLine('earning', 'Special Mobility Allowance', $mobilityAllowance, false, ['policy' => 'allowance-override']),
                new PayrollLine('earning', 'Company Holiday Premium', $holidayPremium, true, ['policy' => 'holiday-override']),
            ];
            $deductions = [];

            foreach ($input->manualDeductions as $deduction) {
                $deductions[] = new PayrollLine('deduction', $deduction->label, $deduction->amount);
            }

            if (! $input->lateDeduction->isZero()) {
                $deductions[] = new PayrollLine(
                    'deduction',
                    'Tardiness Deduction',
                    MoneyHelper::max(
                        $input->lateDeduction->subtract(MoneyHelper::fromNumeric(25, $input->lateDeduction)),
                        MoneyHelper::zero($input->lateDeduction),
                    ),
                    false,
                    ['policy' => '10-minute-grace-period'],
                );
            }

            $deductions[] = new PayrollLine(
                'deduction',
                'Custom Policy Tax',
                MoneyHelper::fromNumeric(500, $basicPay),
                false,
                ['policy' => 'deduction-order'],
            );

            $grossPay = MoneyHelper::sum(array_map(static fn (PayrollLine $line) => $line->amount, $earnings));
            $deductionTotal = MoneyHelper::sum(array_map(static fn (PayrollLine $line) => $line->amount, $deductions));
            $taxableIncome = MoneyHelper::sum(array_map(
                static fn (PayrollLine $line) => $line->taxable ? $line->amount : MoneyHelper::zero($line->amount),
                $earnings,
            ));
            $netPay = $grossPay->subtract($deductionTotal);

            return new PayrollResult(
                company: $company,
                employee: $employee,
                period: $input->period,
                rates: $rates,
                earnings: $earnings,
                deductions: $deductions,
                employeeContributions: [],
                employerContributions: [],
                separatePayouts: [],
                grossPay: $grossPay,
                taxableIncome: $taxableIncome,
                netPay: $netPay,
                takeHomePay: $netPay,
                bonusTaxWithheld: MoneyHelper::zero($grossPay),
            );
        }
    };
}

it('allows country-specific payroll rules through a pluggable workflow extension', function () {
    $result = extensionScenarioEngine([
        'strategies' => [
            'clients' => [
                'country-extension' => [
                    'workflow' => countrySpecificExtensionWorkflow(),
                ],
            ],
        ],
    ])->compute(
        extensionScenarioCompany([
            'client_code' => 'country-extension',
            'name' => 'Country Extension Client',
        ]),
        extensionScenarioEmployee(),
        [
            'period' => [
                'key' => '2026-COUNTRY-EXT',
                'start_date' => '2026-07-01',
                'end_date' => '2026-07-15',
                'release_date' => '2026-07-15',
            ],
        ],
    );

    $employeeContributionLabels = array_map(static fn ($line) => $line->label, $result->employeeContributions);
    $employerContributionLabels = array_map(static fn ($line) => $line->label, $result->employerContributions);
    $deductionLabels = array_map(static fn ($line) => $line->label, $result->deductions);

    expect($result->earnings[0]->label)->toBe('Country Base Pay')
        ->and($result->earnings[0]->metadata['country_rule'])->toBe('custom')
        ->and($employeeContributionLabels)->toBe(['Social Insurance', 'National Pension'])
        ->and($employerContributionLabels)->toBe(['Employer Social Insurance', 'Employer Pension'])
        ->and($deductionLabels)->toBe(['State Income Tax'])
        ->and(MoneyHelper::toFloat($result->grossPay))->toBe(5000.00)
        ->and(MoneyHelper::toFloat($result->taxableIncome))->toBe(5000.00)
        ->and(MoneyHelper::toFloat($result->netPay))->toBe(4310.00);
});

it('allows company-specific payroll policies through a replaceable workflow extension', function () {
    $result = extensionScenarioEngine([
        'strategies' => [
            'clients' => [
                'policy-extension' => [
                    'workflow' => companyPolicyExtensionWorkflow(),
                ],
            ],
        ],
    ])->compute(
        extensionScenarioCompany([
            'client_code' => 'policy-extension',
            'name' => 'Policy Extension Client',
        ]),
        extensionScenarioEmployee(),
        [
            'period' => [
                'key' => '2026-POLICY-EXT',
                'start_date' => '2026-07-01',
                'end_date' => '2026-07-15',
                'release_date' => '2026-07-15',
            ],
            'late_deduction' => 100,
            'manual_deductions' => [
                [
                    'label' => 'Cash Advance',
                    'amount' => 300,
                ],
            ],
        ],
    );

    $earningLabels = array_map(static fn ($line) => $line->label, $result->earnings);
    $deductionLabels = array_map(static fn ($line) => $line->label, $result->deductions);

    expect($earningLabels)->toBe(['Basic Pay', 'Special Mobility Allowance', 'Company Holiday Premium'])
        ->and($result->earnings[1]->metadata['policy'])->toBe('allowance-override')
        ->and($result->earnings[2]->metadata['policy'])->toBe('holiday-override')
        ->and($deductionLabels)->toBe(['Cash Advance', 'Tardiness Deduction', 'Custom Policy Tax'])
        ->and($result->deductions[1]->metadata['policy'])->toBe('10-minute-grace-period')
        ->and($result->deductions[2]->metadata['policy'])->toBe('deduction-order')
        ->and(MoneyHelper::toFloat($result->taxableIncome))->toBe(15800.00)
        ->and(MoneyHelper::toFloat($result->grossPay))->toBe(17000.00)
        ->and(MoneyHelper::toFloat($result->netPay))->toBe(16125.00);
});
