<?php

namespace Jdclzn\PayrollEngine\Tests;

use Jdclzn\PayrollEngine\PayrollEngine;
use Jdclzn\PayrollEngine\Support\MoneyHelper;

function offCycleEngine(): PayrollEngine
{
    return new PayrollEngine;
}

/**
 * @return array<string, mixed>
 */
function offCycleCompany(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Off Cycle Client',
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
function offCycleEmployee(array $overrides = []): array
{
    return array_replace_recursive([
        'employee_number' => 'EMP-OFF-001',
        'full_name' => 'Off Cycle Employee',
        'employment_status' => 'active',
        'date_hired' => '2024-01-10',
        'department' => 'Finance',
        'email' => 'offcycle.employee@example.com',
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

it('treats adjustment payroll as an off-cycle run type in the same engine', function () {
    $result = offCycleEngine()->compute(
        offCycleCompany(),
        offCycleEmployee(),
        [
            'period' => [
                'key' => '2026-ADJUSTMENT',
                'start_date' => '2026-09-01',
                'end_date' => '2026-09-01',
                'release_date' => '2026-09-05',
                'run_type' => 'adjustment',
            ],
            'adjustments' => [
                [
                    'label' => 'Payroll Adjustment',
                    'amount' => 2500,
                    'taxable' => true,
                ],
            ],
        ],
    );

    expect($result->period->runType)->toBe('adjustment')
        ->and($result->period->isOffCycleRun())->toBeTrue()
        ->and(MoneyHelper::toFloat($result->rates->scheduledBasicPay))->toBe(0.00)
        ->and($result->earnings)->toHaveCount(1)
        ->and($result->earnings[0]->label)->toBe('Payroll Adjustment')
        ->and($result->employeeContributions)->toBeEmpty()
        ->and($result->employerContributions)->toBeEmpty()
        ->and($result->deductions)->toBeEmpty()
        ->and(MoneyHelper::toFloat($result->grossPay))->toBe(2500.00)
        ->and(MoneyHelper::toFloat($result->netPay))->toBe(2500.00);
});

it('treats correction payroll as an off-cycle run type in the same engine', function () {
    $result = offCycleEngine()->compute(
        offCycleCompany(),
        offCycleEmployee(),
        [
            'period' => [
                'key' => '2026-CORRECTION',
                'start_date' => '2026-09-02',
                'end_date' => '2026-09-02',
                'release_date' => '2026-09-05',
                'run_type' => 'correction',
            ],
            'adjustments' => [
                [
                    'label' => 'Correction Earning',
                    'amount' => 1800,
                    'taxable' => true,
                ],
            ],
            'manual_deductions' => [
                [
                    'label' => 'Correction Recovery',
                    'amount' => 300,
                ],
            ],
        ],
    );

    expect($result->period->runType)->toBe('correction')
        ->and($result->period->isOffCycleRun())->toBeTrue()
        ->and(MoneyHelper::toFloat($result->rates->scheduledBasicPay))->toBe(0.00)
        ->and($result->earnings)->toHaveCount(1)
        ->and($result->earnings[0]->label)->toBe('Correction Earning')
        ->and($result->employeeContributions)->toBeEmpty()
        ->and($result->deductions)->toHaveCount(1)
        ->and($result->deductions[0]->label)->toBe('Correction Recovery')
        ->and(MoneyHelper::toFloat($result->netPay))->toBe(1500.00);
});

it('treats emergency payroll as an off-cycle run type in the same engine', function () {
    $result = offCycleEngine()->compute(
        offCycleCompany(),
        offCycleEmployee(),
        [
            'period' => [
                'key' => '2026-EMERGENCY',
                'start_date' => '2026-09-03',
                'end_date' => '2026-09-03',
                'release_date' => '2026-09-03',
                'run_type' => 'emergency',
            ],
            'adjustments' => [
                [
                    'label' => 'Emergency Payroll Release',
                    'amount' => 5000,
                    'taxable' => true,
                ],
            ],
        ],
    );

    expect($result->period->runType)->toBe('emergency')
        ->and($result->period->isOffCycleRun())->toBeTrue()
        ->and(MoneyHelper::toFloat($result->rates->scheduledBasicPay))->toBe(0.00)
        ->and($result->earnings[0]->label)->toBe('Emergency Payroll Release')
        ->and($result->employeeContributions)->toBeEmpty()
        ->and($result->deductions)->toBeEmpty()
        ->and(MoneyHelper::toFloat($result->grossPay))->toBe(5000.00)
        ->and(MoneyHelper::toFloat($result->takeHomePay))->toBe(5000.00);
});

it('treats bonus release as an off-cycle run type and still computes bonus tax with the same engine', function () {
    $result = offCycleEngine()->compute(
        offCycleCompany(),
        offCycleEmployee([
            'projected_annual_taxable_income' => 600000,
            'tax_shield_amount_for_bonuses' => 70000,
        ]),
        [
            'period' => [
                'key' => '2026-BONUS-RELEASE',
                'start_date' => '2026-12-01',
                'end_date' => '2026-12-01',
                'release_date' => '2026-12-05',
                'run_type' => 'bonus_release',
            ],
            'bonus_amount' => 120000,
            'used_annual_bonus_shield' => 20000,
        ],
    );

    expect($result->period->runType)->toBe('bonus_release')
        ->and($result->period->isOffCycleRun())->toBeTrue()
        ->and(MoneyHelper::toFloat($result->rates->scheduledBasicPay))->toBe(0.00)
        ->and($result->earnings)->toHaveCount(1)
        ->and($result->earnings[0]->label)->toBe('Bonus')
        ->and($result->employeeContributions)->toBeEmpty()
        ->and(MoneyHelper::toFloat($result->bonusTaxWithheld))->toBe(14000.00)
        ->and(MoneyHelper::toFloat($result->netPay))->toBe(106000.00);
});

it('treats retro pay as an off-cycle run type in the same engine', function () {
    $result = offCycleEngine()->compute(
        offCycleCompany(),
        offCycleEmployee(),
        [
            'period' => [
                'key' => '2026-RETRO',
                'start_date' => '2026-09-04',
                'end_date' => '2026-09-04',
                'release_date' => '2026-09-06',
                'run_type' => 'retro_pay',
            ],
            'adjustments' => [
                [
                    'label' => 'Retro Pay Adjustment',
                    'amount' => 3200,
                    'taxable' => true,
                ],
            ],
        ],
    );

    expect($result->period->runType)->toBe('retro_pay')
        ->and($result->period->isOffCycleRun())->toBeTrue()
        ->and(MoneyHelper::toFloat($result->rates->scheduledBasicPay))->toBe(0.00)
        ->and($result->earnings)->toHaveCount(1)
        ->and($result->earnings[0]->label)->toBe('Retro Pay Adjustment')
        ->and($result->employeeContributions)->toBeEmpty()
        ->and($result->deductions)->toBeEmpty()
        ->and(MoneyHelper::toFloat($result->grossPay))->toBe(3200.00)
        ->and(MoneyHelper::toFloat($result->netPay))->toBe(3200.00);
});
