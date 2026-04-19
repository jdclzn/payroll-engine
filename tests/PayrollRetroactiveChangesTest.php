<?php

namespace QuillBytes\PayrollEngine\Tests;

use QuillBytes\PayrollEngine\PayrollEngine;
use QuillBytes\PayrollEngine\Support\MoneyHelper;

function retroEngine(): PayrollEngine
{
    return new PayrollEngine;
}

/**
 * @return array<string, mixed>
 */
function retroCompany(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Retroactive Payroll Client',
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
function retroEmployee(array $overrides = []): array
{
    return array_replace_recursive([
        'employee_number' => 'EMP-RETRO-001',
        'full_name' => 'Retro Employee',
        'employment_status' => 'active',
        'date_hired' => '2024-01-10',
        'department' => 'Operations',
        'email' => 'retro.employee@example.com',
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

it('recomputes a historical salary increase and releases only the net retro difference as an adjustment run', function () {
    $engine = retroEngine();
    $company = retroCompany();
    $historicalPeriod = [
        'key' => '2026-02-A',
        'start_date' => '2026-02-01',
        'end_date' => '2026-02-15',
        'release_date' => '2026-02-15',
    ];
    $original = $engine->compute(
        $company,
        retroEmployee([
            'monthly_basic_salary' => 30000,
        ]),
        [
            'period' => $historicalPeriod,
        ],
    );
    $recomputed = $engine->compute(
        $company,
        retroEmployee([
            'monthly_basic_salary' => 36000,
        ]),
        [
            'period' => $historicalPeriod,
        ],
    );
    $retroInput = $engine->retroAdjustmentInput(
        $original,
        $recomputed,
        [
            'key' => '2026-05-RETRO-SALARY',
            'start_date' => '2026-05-05',
            'end_date' => '2026-05-05',
            'release_date' => '2026-05-05',
        ],
    );
    $release = $engine->compute(
        $company,
        retroEmployee([
            'monthly_basic_salary' => 36000,
        ]),
        $retroInput,
    );
    $expectedDifference = $recomputed->takeHomePay->subtract($original->takeHomePay);
    $earningLabels = array_map(static fn ($line) => $line->label, $release->earnings);
    $deductionLabels = array_map(static fn ($line) => $line->label, $release->deductions);

    expect($release->period->runType)->toBe('adjustment')
        ->and($release->period->isOffCycleRun())->toBeTrue()
        ->and(MoneyHelper::toFloat($release->rates->scheduledBasicPay))->toBe(0.00)
        ->and($earningLabels)->toContain('Retro Basic Pay')
        ->and($deductionLabels)->toContain('Retro Withholding Tax')
        ->and(MoneyHelper::toFloat($release->takeHomePay))->toBe(MoneyHelper::toFloat($expectedDifference));
});

it('releases a corrected holiday pay amount as a difference-only retro pay run', function () {
    $engine = retroEngine();
    $company = retroCompany();
    $historicalPeriod = [
        'key' => '2026-03-A',
        'start_date' => '2026-03-01',
        'end_date' => '2026-03-15',
        'release_date' => '2026-03-15',
    ];
    $original = $engine->compute(
        $company,
        retroEmployee(),
        [
            'period' => $historicalPeriod,
        ],
    );
    $recomputed = $engine->compute(
        $company,
        retroEmployee(),
        [
            'period' => $historicalPeriod,
            'adjustments' => [
                [
                    'label' => 'Holiday Pay Correction',
                    'amount' => 1800,
                    'taxable' => true,
                ],
            ],
        ],
    );
    $retroInput = $engine->retroAdjustmentInput(
        $original,
        $recomputed,
        [
            'key' => '2026-05-RETRO-HOLIDAY',
            'start_date' => '2026-05-06',
            'end_date' => '2026-05-06',
            'release_date' => '2026-05-06',
            'run_type' => 'retro_pay',
        ],
    );
    $release = $engine->compute($company, retroEmployee(), $retroInput);
    $expectedDifference = $recomputed->takeHomePay->subtract($original->takeHomePay);

    expect($release->period->runType)->toBe('retro_pay')
        ->and($release->earnings)->toHaveCount(1)
        ->and($release->earnings[0]->label)->toBe('Retro Holiday Pay Correction')
        ->and($release->deductions)->toHaveCount(1)
        ->and($release->deductions[0]->label)->toBe('Retro Withholding Tax')
        ->and(MoneyHelper::toFloat($release->takeHomePay))->toBe(MoneyHelper::toFloat($expectedDifference));
});

it('releases late overtime approval as a retro difference-only adjustment', function () {
    $engine = retroEngine();
    $company = retroCompany();
    $historicalPeriod = [
        'key' => '2026-04-A',
        'start_date' => '2026-04-01',
        'end_date' => '2026-04-15',
        'release_date' => '2026-04-15',
    ];
    $original = $engine->compute(
        $company,
        retroEmployee(),
        [
            'period' => $historicalPeriod,
        ],
    );
    $recomputed = $engine->compute(
        $company,
        retroEmployee(),
        [
            'period' => $historicalPeriod,
            'overtime' => [
                [
                    'type' => 'regular',
                    'hours' => 4,
                ],
            ],
        ],
    );
    $retroInput = $engine->retroAdjustmentInput(
        $original,
        $recomputed,
        [
            'key' => '2026-05-RETRO-OT',
            'start_date' => '2026-05-07',
            'end_date' => '2026-05-07',
            'release_date' => '2026-05-07',
            'run_type' => 'adjustment',
        ],
    );
    $release = $engine->compute($company, retroEmployee(), $retroInput);
    $expectedDifference = $recomputed->takeHomePay->subtract($original->takeHomePay);
    $earningLabels = array_map(static fn ($line) => $line->label, $release->earnings);

    expect($release->period->runType)->toBe('adjustment')
        ->and($release->employeeContributions)->toBeEmpty()
        ->and($earningLabels)->toContain('Retro Overtime Pay')
        ->and(MoneyHelper::toFloat($release->takeHomePay))->toBe(MoneyHelper::toFloat($expectedDifference));
});
