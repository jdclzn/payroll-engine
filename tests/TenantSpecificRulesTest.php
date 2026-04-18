<?php

namespace Jdclzn\PayrollEngine\Tests;

use Jdclzn\PayrollEngine\Contracts\OvertimeCalculator as OvertimeCalculatorContract;
use Jdclzn\PayrollEngine\Contracts\PayrollWorkflow as PayrollWorkflowContract;
use Jdclzn\PayrollEngine\Data\CompanyProfile;
use Jdclzn\PayrollEngine\Data\EmployeeProfile;
use Jdclzn\PayrollEngine\Data\PayrollInput;
use Jdclzn\PayrollEngine\Data\PayrollLine;
use Jdclzn\PayrollEngine\Data\PayrollResult;
use Jdclzn\PayrollEngine\Data\RateSnapshot;
use Jdclzn\PayrollEngine\PayrollEngine;
use Jdclzn\PayrollEngine\Support\MoneyHelper;

function tenantRulesEngine(array $config = []): PayrollEngine
{
    return new PayrollEngine($config);
}

/**
 * @return array<string, mixed>
 */
function tenantRulesCompany(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Tenant Rules Client',
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
function tenantRulesEmployee(array $overrides = []): array
{
    return array_replace_recursive([
        'employee_number' => 'EMP-TENANT-001',
        'full_name' => 'Tenant Employee',
        'employment_status' => 'active',
        'date_hired' => '2024-01-10',
        'department' => 'Operations',
        'email' => 'tenant.employee@example.com',
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

function tenantAOvertimeCalculator(): OvertimeCalculatorContract
{
    return new class implements OvertimeCalculatorContract
    {
        public function calculate(CompanyProfile $company, PayrollInput $input, RateSnapshot $rates): array
        {
            $hours = 0.0;

            foreach ($input->overtimeEntries as $entry) {
                $hours += $entry->hours;
            }

            $amount = MoneyHelper::multiply(
                MoneyHelper::multiply($rates->hourlyRate, $hours),
                3
            );

            return [
                new PayrollLine(
                    'earning',
                    'Tenant A Overtime',
                    $amount,
                    true,
                    ['tenant' => 'tenant-a', 'multiplier' => 3.0],
                ),
            ];
        }
    };
}

function tenantBWorkflow(): PayrollWorkflowContract
{
    return new class implements PayrollWorkflowContract
    {
        public function calculate(CompanyProfile $company, EmployeeProfile $employee, PayrollInput $input): PayrollResult
        {
            $basePay = MoneyHelper::fromNumeric(10000, $employee->compensation->monthlyBasicSalary);
            $housingBenefit = MoneyHelper::fromNumeric(2000, $basePay);
            $transportBenefit = MoneyHelper::fromNumeric(1000, $basePay);
            $tax = MoneyHelper::fromNumeric(500, $basePay);
            $rates = new RateSnapshot(
                monthlyBasicSalary: $employee->compensation->monthlyBasicSalary,
                scheduledBasicPay: $basePay,
                dailyRate: MoneyHelper::fromNumeric(500, $basePay),
                hourlyRate: MoneyHelper::fromNumeric(62.5, $basePay),
                fixedPerDayApplied: false,
            );
            $earnings = [
                new PayrollLine('earning', 'Base Pay', $basePay, true, ['tenant' => 'tenant-b']),
                new PayrollLine('earning', 'Housing Benefit', $housingBenefit, false, ['tenant' => 'tenant-b', 'excluded' => true]),
                new PayrollLine('earning', 'Transport Benefit', $transportBenefit, false, ['tenant' => 'tenant-b', 'excluded' => true]),
            ];
            $deductions = [
                new PayrollLine('deduction', 'Tenant B Withholding', $tax, false, ['tenant' => 'tenant-b']),
            ];
            $grossPay = MoneyHelper::sum(array_map(static fn (PayrollLine $line) => $line->amount, $earnings));
            $netPay = $grossPay->subtract($tax);

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
                taxableIncome: $basePay,
                netPay: $netPay,
                takeHomePay: $netPay,
                bonusTaxWithheld: MoneyHelper::zero($grossPay),
            );
        }
    };
}

function tenantCWorkflow(): PayrollWorkflowContract
{
    return new class implements PayrollWorkflowContract
    {
        public function calculate(CompanyProfile $company, EmployeeProfile $employee, PayrollInput $input): PayrollResult
        {
            $basicPay = MoneyHelper::fromNumeric(15000, $employee->compensation->monthlyBasicSalary);
            $baseAllowance = $employee->compensation->representationAllowance->add($employee->compensation->otherAllowances);
            $transformedAllowance = $baseAllowance->add(MoneyHelper::fromNumeric(750, $baseAllowance));
            $rates = new RateSnapshot(
                monthlyBasicSalary: $employee->compensation->monthlyBasicSalary,
                scheduledBasicPay: $basicPay,
                dailyRate: MoneyHelper::fromNumeric(750, $basicPay),
                hourlyRate: MoneyHelper::fromNumeric(93.75, $basicPay),
                fixedPerDayApplied: false,
            );
            $earnings = [
                new PayrollLine('earning', 'Basic Pay', $basicPay, true, ['tenant' => 'tenant-c']),
                new PayrollLine('earning', 'Tenant C Computed Allowance', $transformedAllowance, false, [
                    'tenant' => 'tenant-c',
                    'policy' => 'allowance-transform',
                ]),
            ];
            $grossPay = MoneyHelper::sum(array_map(static fn (PayrollLine $line) => $line->amount, $earnings));

            return new PayrollResult(
                company: $company,
                employee: $employee,
                period: $input->period,
                rates: $rates,
                earnings: $earnings,
                deductions: [],
                employeeContributions: [],
                employerContributions: [],
                separatePayouts: [],
                grossPay: $grossPay,
                taxableIncome: $basicPay,
                netPay: $grossPay,
                takeHomePay: $grossPay,
                bonusTaxWithheld: MoneyHelper::zero($grossPay),
            );
        }
    };
}

it('allows Tenant A to override overtime computation without changing the rest of the engine', function () {
    $result = tenantRulesEngine([
        'strategies' => [
            'clients' => [
                'tenant-a' => [
                    'overtime' => tenantAOvertimeCalculator(),
                ],
            ],
        ],
    ])->compute(
        tenantRulesCompany([
            'client_code' => 'tenant-a',
        ]),
        tenantRulesEmployee([
            'employee_number' => 'EMP-TENANT-A',
            'full_name' => 'Tenant A Employee',
            'hourly_rate' => 150,
        ]),
        [
            'period' => [
                'key' => '2026-TENANT-A',
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-01',
                'release_date' => '2026-08-01',
                'run_type' => 'special',
            ],
            'overtime' => [
                [
                    'type' => 'regular',
                    'hours' => 2,
                ],
                [
                    'type' => 'rest_day',
                    'hours' => 1,
                ],
            ],
        ],
    );

    expect($result->earnings)->toHaveCount(1)
        ->and($result->earnings[0]->label)->toBe('Tenant A Overtime')
        ->and($result->earnings[0]->metadata['tenant'])->toBe('tenant-a')
        ->and($result->earnings[0]->metadata['multiplier'])->toBe(3.0)
        ->and(MoneyHelper::toFloat($result->grossPay))->toBe(1350.00)
        ->and(MoneyHelper::toFloat($result->netPay))->toBe(1350.00);
});

it('allows Tenant B to exclude selected benefits through a tenant workflow extension', function () {
    $result = tenantRulesEngine([
        'strategies' => [
            'clients' => [
                'tenant-b' => [
                    'workflow' => tenantBWorkflow(),
                ],
            ],
        ],
    ])->compute(
        tenantRulesCompany([
            'client_code' => 'tenant-b',
        ]),
        tenantRulesEmployee([
            'employee_number' => 'EMP-TENANT-B',
            'full_name' => 'Tenant B Employee',
        ]),
        [
            'period' => [
                'key' => '2026-TENANT-B',
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-15',
                'release_date' => '2026-08-15',
            ],
        ],
    );

    expect($result->earnings)->toHaveCount(3)
        ->and($result->earnings[1]->label)->toBe('Housing Benefit')
        ->and($result->earnings[1]->metadata['excluded'])->toBeTrue()
        ->and($result->earnings[2]->label)->toBe('Transport Benefit')
        ->and(MoneyHelper::toFloat($result->grossPay))->toBe(13000.00)
        ->and(MoneyHelper::toFloat($result->taxableIncome))->toBe(10000.00)
        ->and($result->deductions[0]->label)->toBe('Tenant B Withholding')
        ->and(MoneyHelper::toFloat($result->netPay))->toBe(12500.00);
});

it('allows Tenant C to compute allowances differently through tenant-specific extension rules', function () {
    $result = tenantRulesEngine([
        'strategies' => [
            'clients' => [
                'tenant-c' => [
                    'workflow' => tenantCWorkflow(),
                ],
            ],
        ],
    ])->compute(
        tenantRulesCompany([
            'client_code' => 'tenant-c',
        ]),
        tenantRulesEmployee([
            'employee_number' => 'EMP-TENANT-C',
            'full_name' => 'Tenant C Employee',
            'representation' => 1000,
            'allowances' => 500,
        ]),
        [
            'period' => [
                'key' => '2026-TENANT-C',
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-15',
                'release_date' => '2026-08-15',
            ],
        ],
    );

    expect($result->earnings)->toHaveCount(2)
        ->and($result->earnings[1]->label)->toBe('Tenant C Computed Allowance')
        ->and($result->earnings[1]->metadata['policy'])->toBe('allowance-transform')
        ->and(MoneyHelper::toFloat($result->earnings[1]->amount))->toBe(2250.00)
        ->and(MoneyHelper::toFloat($result->grossPay))->toBe(17250.00)
        ->and(MoneyHelper::toFloat($result->taxableIncome))->toBe(15000.00)
        ->and(MoneyHelper::toFloat($result->netPay))->toBe(17250.00);
});
