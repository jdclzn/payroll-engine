<?php

namespace QuillBytes\PayrollEngine\Tests;

use QuillBytes\PayrollEngine\Contracts\VariableEarningCalculator as VariableEarningCalculatorContract;
use QuillBytes\PayrollEngine\Data\CompanyProfile;
use QuillBytes\PayrollEngine\Data\EmployeeProfile;
use QuillBytes\PayrollEngine\Data\PayrollInput;
use QuillBytes\PayrollEngine\Data\PayrollLine;
use QuillBytes\PayrollEngine\Data\RateSnapshot;
use QuillBytes\PayrollEngine\Exceptions\InvalidPayrollData;
use QuillBytes\PayrollEngine\PayrollEngine;
use QuillBytes\PayrollEngine\Support\MoneyHelper;

function variableEarningEngine(array $config = []): PayrollEngine
{
    return new PayrollEngine($config);
}

/**
 * @return array<string, mixed>
 */
function variableEarningCompany(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Variable Earnings Client',
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
function variableEarningEmployee(array $overrides = []): array
{
    return array_replace_recursive([
        'employee_number' => 'EMP-VAR-001',
        'full_name' => 'Variable Earnings Employee',
        'employment_status' => 'active',
        'date_hired' => '2024-01-10',
        'department' => 'Sales',
        'email' => 'variable.earnings@example.com',
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

function clientVariableEarningCalculator(): VariableEarningCalculatorContract
{
    return new class implements VariableEarningCalculatorContract
    {
        public function calculate(
            CompanyProfile $company,
            EmployeeProfile $employee,
            PayrollInput $input,
            RateSnapshot $rates,
        ): array {
            $salesVolume = 0.0;
            $quotaHit = false;

            foreach ($input->variableEarningEntries as $entry) {
                $salesVolume += (float) ($entry->metadata['sales_volume'] ?? 0);
                $quotaHit = $quotaHit || (bool) ($entry->metadata['quota_hit'] ?? false);
            }

            $commission = MoneyHelper::fromNumeric($salesVolume * 0.05, $rates->monthlyBasicSalary);
            $quotaBonus = $quotaHit
                ? MoneyHelper::fromNumeric(750, $rates->monthlyBasicSalary)
                : MoneyHelper::zero($rates->monthlyBasicSalary);

            $lines = [];

            if (! $commission->isZero()) {
                $lines[] = new PayrollLine(
                    'earning',
                    'Client Sales Commission',
                    $commission,
                    true,
                    ['strategy' => 'client-variable-earnings'],
                );
            }

            if (! $quotaBonus->isZero()) {
                $lines[] = new PayrollLine(
                    'earning',
                    'Quota Accelerator Bonus',
                    $quotaBonus,
                    true,
                    ['strategy' => 'client-variable-earnings'],
                );
            }

            return $lines;
        }
    };
}

it('computes commission and performance earnings through the default variable earning calculator', function () {
    $result = variableEarningEngine()->compute(
        variableEarningCompany(),
        variableEarningEmployee(),
        [
            'period' => [
                'key' => '2026-04-COMMISSION',
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-15',
                'release_date' => '2026-04-15',
            ],
            'sales_commissions' => [
                [
                    'amount' => 2500,
                    'metadata' => [
                        'basis' => 'monthly-sales',
                    ],
                ],
            ],
            'production_incentives' => [1200],
            'quota_bonuses' => [
                [
                    'amount' => 800,
                ],
            ],
        ],
    );

    $variableLines = array_values(array_filter(
        $result->earnings,
        static fn (PayrollLine $line) => isset($line->metadata['variable_earning_type']),
    ));
    $variableTypes = array_map(static fn (PayrollLine $line) => $line->metadata['variable_earning_type'], $variableLines);
    $variableLabels = array_map(static fn (PayrollLine $line) => $line->label, $variableLines);
    $variableTotal = MoneyHelper::sum(array_map(static fn (PayrollLine $line) => $line->amount, $variableLines));
    $employeeShare = MoneyHelper::sum(array_map(static fn (PayrollLine $line) => $line->amount, $result->employeeContributions));

    expect($variableLines)->toHaveCount(3)
        ->and($variableLabels)->toBe(['Sales Commission', 'Production Incentive', 'Quota Bonus'])
        ->and($variableTypes)->toBe(['sales_commission', 'production_incentive', 'quota_bonus'])
        ->and($variableLines[0]->metadata['declared_basis'])->toBe('monthly-sales')
        ->and($variableLines[0]->metadata['basis']['amount'])->toBe(2500.00)
        ->and(MoneyHelper::toFloat($variableTotal))->toBe(4500.00)
        ->and(MoneyHelper::toFloat($result->grossPay))->toBe(19500.00)
        ->and(MoneyHelper::toFloat($result->taxableIncome))->toBe(MoneyHelper::toFloat($result->grossPay->subtract($employeeShare)));
});

it('allows a client to replace variable earning computation without changing the rest of the engine', function () {
    $result = variableEarningEngine([
        'strategies' => [
            'clients' => [
                'commission-client' => [
                    'variable_earnings' => clientVariableEarningCalculator(),
                ],
            ],
        ],
    ])->compute(
        variableEarningCompany([
            'client_code' => 'commission-client',
        ]),
        variableEarningEmployee([
            'employee_number' => 'EMP-VAR-002',
            'full_name' => 'Commission Client Employee',
        ]),
        [
            'period' => [
                'key' => '2026-COMMISSION-SPECIAL',
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-01',
                'release_date' => '2026-04-01',
                'run_type' => 'special',
            ],
            'variable_earnings' => [
                [
                    'label' => 'Sales Metric',
                    'amount' => 0,
                    'metadata' => [
                        'sales_volume' => 25000,
                    ],
                ],
                [
                    'label' => 'Quota Achievement',
                    'amount' => 0,
                    'metadata' => [
                        'quota_hit' => true,
                    ],
                ],
            ],
        ],
    );

    expect($result->earnings)->toHaveCount(2)
        ->and($result->earnings[0]->label)->toBe('Client Sales Commission')
        ->and($result->earnings[1]->label)->toBe('Quota Accelerator Bonus')
        ->and($result->earnings[0]->metadata['strategy'])->toBe('client-variable-earnings')
        ->and(MoneyHelper::toFloat($result->grossPay))->toBe(2000.00)
        ->and(MoneyHelper::toFloat($result->taxableIncome))->toBe(2000.00)
        ->and(MoneyHelper::toFloat($result->netPay))->toBe(2000.00);
});

it('rejects negative variable earnings input', function () {
    expect(fn () => variableEarningEngine()->compute(
        variableEarningCompany(),
        variableEarningEmployee(),
        [
            'period' => [
                'key' => '2026-NEGATIVE-COMMISSION',
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-15',
                'release_date' => '2026-04-15',
            ],
            'variable_earnings' => [
                [
                    'label' => 'Negative Commission',
                    'amount' => -100,
                ],
            ],
        ],
    ))->toThrow(
        InvalidPayrollData::class,
        'Payroll input variable earnings, adjustments, and deductions cannot be negative.',
    );
});
