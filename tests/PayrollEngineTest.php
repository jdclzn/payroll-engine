<?php

namespace Jdclzn\PayrollEngine\Tests;

use Carbon\CarbonImmutable;
use Jdclzn\PayrollEngine\Enums\PayrollRunStatus;
use Jdclzn\PayrollEngine\Exceptions\InvalidPayrollData;
use Jdclzn\PayrollEngine\PayrollEngine;
use Jdclzn\PayrollEngine\Support\MoneyHelper;
use Illuminate\Database\Eloquent\Model;

function engine(): PayrollEngine
{
    return new PayrollEngine();
}

function testPayrollModel(array $attributes = []): Model
{
    return new class($attributes) extends Model
    {
        protected $guarded = [];

        public $timestamps = false;

        protected $casts
            = [
                'approvers' => 'array',
                'prepared_by' => 'array',
                'administrators' => 'array',
                'minimum_wage_earner' => 'boolean',
                'payroll_schedules' => 'array',
            ];
    };
}

/**
 * @return array<string, mixed>
 */
function baseCompany(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Base Client',
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
            [
                'pay_date' => '30',
                'period_start' => '16',
                'period_end' => '30',
            ],
        ],
    ], $overrides);
}

/**
 * @return array<string, mixed>
 */
function baseEmployee(array $overrides = []): array
{
    return array_replace_recursive([
        'employee_number' => 'EMP-001',
        'full_name' => 'Ana Santos',
        'employment_status' => 'active',
        'date_hired' => '2024-01-10',
        'department' => 'Finance',
        'email' => 'ana.santos@example.com',
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

it('computes base payroll for a regular semi-monthly run', function () {
    $result = engine()->compute(
        baseCompany(),
        baseEmployee([
            'representation' => 2000,
            'allowances' => 1000,
        ]),
        [
            'period' => [
                'key' => '2026-04-A',
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-15',
                'release_date' => '2026-04-15',
            ],
            'overtime' => [
                [
                    'type' => 'regular',
                    'hours' => 5,
                ],
            ],
            'adjustments' => [
                [
                    'label' => 'Taxable Adjustment',
                    'amount' => 500,
                    'taxable' => true,
                ],
            ],
            'manual_deductions' => [
                [
                    'label' => 'Uniform Deduction',
                    'amount' => 250,
                ],
            ],
            'loan_deductions' => [
                [
                    'label' => 'Loan Payment',
                    'amount' => 1000,
                ],
            ],
            'absence_deduction' => 300,
        ],
    );

    expect(MoneyHelper::toFloat($result->rates->scheduledBasicPay))->toBe(15000.00)
        ->and(MoneyHelper::toFloat($result->rates->dailyRate))->toBe(1150.16)
        ->and(MoneyHelper::toFloat($result->rates->hourlyRate))->toBe(143.77)
        ->and(MoneyHelper::toFloat($result->grossPay))->toBe(19398.56)
        ->and(MoneyHelper::toFloat($result->taxableIncome))->toBe(15223.56)
        ->and(MoneyHelper::toFloat($result->netPay))->toBe(15952.53)
        ->and(MoneyHelper::toFloat($result->takeHomePay))->toBe(15952.53)
        ->and($result->employeeContributions)->toHaveCount(3)
        ->and($result->separatePayouts)->toBeEmpty();
});

it('applies KRBS overrides for manual overtime, separate payouts, and projected tax', function () {
    $result = engine()->compute(
        baseCompany([
            'name' => 'KRBS',
            'client_code' => 'krbs',
            'prepared_by' => ['krbs.preparer'],
            'approvers' => ['krbs.approver'],
            'administrators' => ['krbs.admin'],
        ]),
        baseEmployee([
            'employee_number' => 'EMP-002',
            'full_name' => 'Mark Dela Cruz',
            'monthly_basic_salary' => 40000,
            'daily_rate' => 2000,
            'representation' => 3000,
            'allowances' => 1500,
            'projected_annual_taxable_income' => 520000,
        ]),
        [
            'period' => [
                'key' => '2026-04-B',
                'start_date' => '2026-04-16',
                'end_date' => '2026-04-30',
            ],
            'manual_overtime_pay' => 1200,
            'adjustments' => [
                [
                    'label' => 'KRBS Taxable Adjustment',
                    'amount' => 800,
                    'taxable' => true,
                ],
            ],
            'loan_deductions' => [
                [
                    'label' => 'Salary Loan',
                    'amount' => 500,
                ],
            ],
        ],
    );

    expect($result->period->releaseDate->toDateString())->toBe('2026-04-29')
        ->and(MoneyHelper::toFloat($result->rates->dailyRate))->toBe(2000.00)
        ->and(MoneyHelper::toFloat($result->rates->hourlyRate))->toBe(250.00)
        ->and($result->rates->fixedPerDayApplied)->toBeTrue()
        ->and(MoneyHelper::toFloat($result->grossPay))->toBe(22000.00)
        ->and(MoneyHelper::toFloat($result->netPay))->toBe(18137.50)
        ->and(MoneyHelper::toFloat($result->takeHomePay))->toBe(22637.50)
        ->and($result->separatePayouts)->toHaveCount(2)
        ->and(MoneyHelper::toFloat($result->bonusTaxWithheld))->toBe(0.00);
});

it('ignores employee fixed daily rate when the company does not enable fixed-per-day pricing', function () {
    $result = engine()->compute(
        baseCompany([
            'fixed_per_day_rate' => false,
            'eemr_factor' => 300,
        ]),
        baseEmployee([
            'monthly_basic_salary' => 30000,
            'daily_rate' => 2000,
        ]),
        [
            'period' => [
                'key' => '2026-04-FIXED-CHECK',
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-15',
                'release_date' => '2026-04-15',
            ],
        ],
    );

    expect(MoneyHelper::toFloat($result->rates->dailyRate))->toBe(1200.00)
        ->and(MoneyHelper::toFloat($result->rates->hourlyRate))->toBe(150.00)
        ->and($result->rates->fixedPerDayApplied)->toBeFalse();
});

it('supports split-per-cutoff pagibig mode even when general statutory splitting is disabled', function () {
    $result = engine()->compute(
        baseCompany([
            'split_monthly_statutory_across_periods' => false,
            'pagibig_mode' => 'split_per_cutoff',
        ]),
        baseEmployee(),
        [
            'period' => [
                'key' => '2026-04-PAGIBIG-SPLIT',
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-15',
                'release_date' => '2026-04-15',
            ],
        ],
    );

    expect($result->employeeContributions[2]->label)->toBe('Pag-IBIG Contribution')
        ->and(MoneyHelper::toFloat($result->employeeContributions[2]->amount))->toBe(50.00)
        ->and(MoneyHelper::toFloat($result->employerContributions[2]->amount))->toBe(50.00);
});

it('supports upgraded voluntary pagibig contribution mode', function () {
    $result = engine()->compute(
        baseCompany([
            'pagibig_mode' => 'upgraded_voluntary',
        ]),
        baseEmployee([
            'upgraded_pagibig_contribution' => 3000,
        ]),
        [
            'period' => [
                'key' => '2026-04-PAGIBIG-UPGRADE',
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-15',
                'release_date' => '2026-04-15',
            ],
        ],
    );

    expect(MoneyHelper::toFloat($result->employeeContributions[2]->amount))->toBe(1500.00)
        ->and(MoneyHelper::toFloat($result->employerContributions[2]->amount))->toBe(50.00);
});

it('keeps pagibig loan amortization separate when loan-amortization-separated mode is enabled', function () {
    $result = engine()->compute(
        baseCompany([
            'pagibig_mode' => 'loan_amortization_separated',
        ]),
        baseEmployee([
            'upgraded_pagibig_contribution' => 3000,
        ]),
        [
            'period' => [
                'key' => '2026-04-PAGIBIG-LOAN',
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-15',
                'release_date' => '2026-04-15',
            ],
            'pagibig_loan_amortization' => 1400,
        ],
    );

    $separatedLoanDeductions = array_values(array_filter(
        $result->deductions,
        static fn ($line) => $line->label === 'Pag-IBIG Loan Amortization' && MoneyHelper::toFloat($line->amount) === 1400.00,
    ));

    expect(MoneyHelper::toFloat($result->employeeContributions[2]->amount))->toBe(1500.00)
        ->and($separatedLoanDeductions)->toHaveCount(1);
});

it('lets a monthly pagibig employee defer deduction until the monthly due run even when company statutory defaults split', function () {
    $firstCutoff = engine()->compute(
        baseCompany([
            'split_monthly_statutory_across_periods' => true,
        ]),
        baseEmployee([
            'pagibig_schedule' => 'monthly',
        ]),
        [
            'period' => [
                'key' => '2026-04-PAGIBIG-MONTHLY-A',
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-15',
                'release_date' => '2026-04-15',
            ],
        ],
    );

    $secondCutoff = engine()->compute(
        baseCompany([
            'split_monthly_statutory_across_periods' => true,
        ]),
        baseEmployee([
            'pagibig_schedule' => 'monthly',
        ]),
        [
            'period' => [
                'key' => '2026-04-PAGIBIG-MONTHLY-B',
                'start_date' => '2026-04-16',
                'end_date' => '2026-04-30',
                'release_date' => '2026-04-30',
            ],
        ],
    );

    expect(MoneyHelper::toFloat($firstCutoff->employeeContributions[2]->amount))->toBe(0.00)
        ->and(MoneyHelper::toFloat($firstCutoff->employerContributions[2]->amount))->toBe(0.00)
        ->and(MoneyHelper::toFloat($secondCutoff->employeeContributions[2]->amount))->toBe(100.00)
        ->and(MoneyHelper::toFloat($secondCutoff->employerContributions[2]->amount))->toBe(100.00);
});

it('lets an employee split pagibig by cutoff even when the company default is monthly', function () {
    $result = engine()->compute(
        baseCompany([
            'split_monthly_statutory_across_periods' => false,
            'pagibig_schedule' => 'monthly',
        ]),
        baseEmployee([
            'pagibig_schedule' => 'split_per_cutoff',
        ]),
        [
            'period' => [
                'key' => '2026-04-PAGIBIG-EMPLOYEE-SPLIT',
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-15',
                'release_date' => '2026-04-15',
            ],
        ],
    );

    expect(MoneyHelper::toFloat($result->employeeContributions[2]->amount))->toBe(50.00)
        ->and(MoneyHelper::toFloat($result->employerContributions[2]->amount))->toBe(50.00);
});

it('allows payroll input to explicitly mark a monthly pagibig deduction as due for the current run', function () {
    $result = engine()->compute(
        baseCompany([
            'split_monthly_statutory_across_periods' => true,
        ]),
        baseEmployee([
            'pagibig_schedule' => 'monthly',
        ]),
        [
            'period' => [
                'key' => '2026-04-PAGIBIG-MONTHLY-OVERRIDE',
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-15',
                'release_date' => '2026-04-15',
            ],
            'pagibig_due_this_run' => true,
        ],
    );

    expect(MoneyHelper::toFloat($result->employeeContributions[2]->amount))->toBe(100.00)
        ->and(MoneyHelper::toFloat($result->employerContributions[2]->amount))->toBe(100.00);
});

it('computes special payroll bonus tax using the employee tax shield override', function () {
    $result = engine()->compute(
        baseCompany(),
        baseEmployee([
            'employee_number' => 'EMP-003',
            'full_name' => 'Leah Reyes',
            'projected_annual_taxable_income' => 600000,
            'tax_shield_amount_for_bonuses' => 70000,
        ]),
        [
            'period' => [
                'key' => '2026-BONUS',
                'start_date' => '2026-12-01',
                'end_date' => '2026-12-01',
                'release_date' => '2026-12-05',
                'run_type' => 'special',
            ],
            'bonus_amount' => 120000,
            'used_annual_bonus_shield' => 20000,
        ],
    );

    expect(MoneyHelper::toFloat($result->rates->scheduledBasicPay))->toBe(0.00)
        ->and(MoneyHelper::toFloat($result->grossPay))->toBe(120000.00)
        ->and(MoneyHelper::toFloat($result->bonusTaxWithheld))->toBe(14000.00)
        ->and($result->employeeContributions)->toBeEmpty()
        ->and(MoneyHelper::toFloat($result->netPay))->toBe(106000.00)
        ->and(MoneyHelper::toFloat($result->takeHomePay))->toBe(106000.00);
});

it('runs payroll from laravel models and enforces processed state before files and payslips', function () {
    $company = testPayrollModel(baseCompany(['name' => 'Workflow Client']));

    $activeEmployee = testPayrollModel(baseEmployee([
        'employee_number' => 'EMP-004',
        'full_name' => 'Paolo Ramos',
        'email' => 'paolo@example.com',
        'monthly_basic_salary' => 25000,
    ]));

    $inactiveEmployee = testPayrollModel(baseEmployee([
        'employee_number' => 'EMP-005',
        'full_name' => 'Inactive User',
        'employment_status' => 'inactive',
        'monthly_basic_salary' => 25000,
        'date_resigned' => '2026-03-31',
    ]));

    $run = engine()->run(
        $company,
        [
            'key' => '2026-04-A',
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-15',
            'release_date' => '2026-04-15',
        ],
        [
            [
                'employee' => $activeEmployee,
                'input' => [
                    'adjustments' => [
                        [
                            'label' => 'Attendance Incentive',
                            'amount' => 1000,
                            'taxable' => true,
                        ],
                    ],
                ],
            ],
            [
                'employee' => $inactiveEmployee,
                'input' => [],
            ],
        ],
    );

    expect(fn() => engine()->generatePayrollFiles($run))
        ->toThrow(InvalidPayrollData::class, 'processed');

    $run->prepare('payroll.preparer', CarbonImmutable::parse('2026-04-10'))
        ->approve('chief.approver', CarbonImmutable::parse('2026-04-11'))
        ->process('payroll.preparer', CarbonImmutable::parse('2026-04-12'));

    expect(fn() => engine()->generatePayslips($run, CarbonImmutable::parse('2026-04-14')))
        ->toThrow(InvalidPayrollData::class, 'on or after');

    $register = engine()->generatePayrollFiles($run);
    $payslips = engine()->generatePayslips($run, CarbonImmutable::parse('2026-04-15'));

    $run->release('treasury.release', CarbonImmutable::parse('2026-04-15'));

    expect($run->results)->toHaveCount(1)
        ->and($run->status)->toBe(PayrollRunStatus::Released)
        ->and($run->auditTrail)->toHaveCount(4)
        ->and($register)->toHaveCount(1)
        ->and($register[0]['employee_number'])->toBe('EMP-004')
        ->and($register[0]['account_number'])->toBe('001234567890')
        ->and($payslips)->toHaveCount(1)
        ->and($payslips[0]['employee']['full_name'])->toBe('Paolo Ramos')
        ->and($payslips[0]['company']['name'])->toBe('Workflow Client');
});

it('rejects incomplete employee setup that violates required capability fields', function () {
    expect(fn() => engine()->compute(
        baseCompany(),
        baseEmployee([
            'email' => null,
        ]),
        [
            'period' => [
                'key' => '2026-04-A',
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-15',
                'release_date' => '2026-04-15',
            ],
        ],
    ))->toThrow(InvalidPayrollData::class, 'email address');
});

it('rejects invalid payroll setup and workflow dates', function () {
    expect(fn() => engine()->compute(
        baseCompany([
            'prepared_by' => ['p1', 'p2', 'p3', 'p4', 'p5', 'p6'],
        ]),
        baseEmployee(),
        [
            'period' => [
                'key' => '2026-04-A',
                'start_date' => '2026-04-16',
                'end_date' => '2026-04-15',
                'release_date' => '2026-04-15',
            ],
        ],
    ))->toThrow(InvalidPayrollData::class);
});
