<?php

namespace QuillBytes\PayrollEngine\Tests;

use QuillBytes\PayrollEngine\PayrollEngine;
use QuillBytes\PayrollEngine\Support\MoneyHelper;

function finalSettlementEngine(): PayrollEngine
{
    return new PayrollEngine;
}

/**
 * @return array<string, mixed>
 */
function finalSettlementCompany(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Final Settlement Client',
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
function finalSettlementEmployee(array $overrides = []): array
{
    return array_replace_recursive([
        'employee_number' => 'EMP-FS-001',
        'full_name' => 'Final Settlement Employee',
        'employment_status' => 'active',
        'date_hired' => '2024-01-10',
        'department' => 'Finance',
        'email' => 'final.settlement@example.com',
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

it('computes resignation final pay with prorated salary leave conversion last deductions and clearance hold', function () {
    $result = finalSettlementEngine()->compute(
        finalSettlementCompany(),
        finalSettlementEmployee([
            'employee_number' => 'EMP-FS-RESIGN',
            'full_name' => 'Resigned Employee',
            'employment_status' => 'resigned',
            'date_resigned' => '2026-09-10',
        ]),
        [
            'period' => [
                'key' => '2026-RESIGN',
                'start_date' => '2026-09-01',
                'end_date' => '2026-09-15',
                'release_date' => '2026-09-20',
                'run_type' => 'resignation',
            ],
            'adjustments' => [
                [
                    'label' => 'Leave Conversion',
                    'amount' => 2000,
                    'taxable' => true,
                ],
                [
                    'label' => 'Final Settlement Adjustment',
                    'amount' => 500,
                    'taxable' => true,
                ],
            ],
            'loan_deductions' => [
                [
                    'label' => 'Last Salary Loan',
                    'amount' => 600,
                ],
            ],
            'manual_deductions' => [
                [
                    'label' => 'Clearance Hold',
                    'amount' => 1200,
                ],
            ],
        ],
    );

    $deductions = [];

    foreach ($result->deductions as $line) {
        $deductions[$line->label] = MoneyHelper::toFloat($line->amount);
    }

    expect($result->period->runType)->toBe('resignation')
        ->and($result->period->isFinalSettlementRun())->toBeTrue()
        ->and(MoneyHelper::toFloat($result->rates->scheduledBasicPay))->toBe(10000.00)
        ->and($result->earnings[1]->label)->toBe('Leave Conversion')
        ->and($result->earnings[2]->label)->toBe('Final Settlement Adjustment')
        ->and($result->employeeContributions)->toBeEmpty()
        ->and($result->employerContributions)->toBeEmpty()
        ->and($deductions)->toMatchArray([
            'Last Salary Loan' => 600.00,
            'Clearance Hold' => 1200.00,
            'Withholding Tax' => 312.50,
        ])
        ->and(MoneyHelper::toFloat($result->grossPay))->toBe(12500.00)
        ->and(MoneyHelper::toFloat($result->taxableIncome))->toBe(12500.00)
        ->and(MoneyHelper::toFloat($result->netPay))->toBe(10387.50);
});

it('allows termination final pay runs to include separated employees through the same payroll run workflow', function () {
    $run = finalSettlementEngine()->run(
        finalSettlementCompany([
            'payroll_schedules' => [
                [
                    'pay_date' => '30',
                    'period_start' => '16',
                    'period_end' => '30',
                ],
            ],
        ]),
        [
            'key' => '2026-TERM',
            'start_date' => '2026-09-16',
            'end_date' => '2026-09-30',
            'release_date' => '2026-10-05',
            'run_type' => 'termination',
        ],
        [
            [
                'employee' => finalSettlementEmployee([
                    'employee_number' => 'EMP-FS-TERM',
                    'full_name' => 'Terminated Employee',
                    'employment_status' => 'terminated',
                    'monthly_basic_salary' => 36000,
                    'date_resigned' => '2026-09-22',
                ]),
                'input' => [
                    'adjustments' => [
                        [
                            'label' => 'Leave Conversion',
                            'amount' => 3000,
                            'taxable' => true,
                        ],
                        [
                            'label' => 'Final Settlement Adjustment',
                            'amount' => 700,
                            'taxable' => true,
                        ],
                    ],
                    'manual_deductions' => [
                        [
                            'label' => 'Asset Accountability Hold',
                            'amount' => 1500,
                        ],
                        [
                            'label' => 'Last Cash Advance',
                            'amount' => 400,
                        ],
                    ],
                ],
            ],
        ],
    );

    $result = $run->results[0];
    $deductions = [];

    foreach ($result->deductions as $line) {
        $deductions[$line->label] = MoneyHelper::toFloat($line->amount);
    }

    expect($run->results)->toHaveCount(1)
        ->and($result->employee->employeeNumber)->toBe('EMP-FS-TERM')
        ->and($result->period->runType)->toBe('termination')
        ->and($result->period->isFinalSettlementRun())->toBeTrue()
        ->and(MoneyHelper::toFloat($result->rates->scheduledBasicPay))->toBe(8400.00)
        ->and(MoneyHelper::toFloat($result->grossPay))->toBe(12100.00)
        ->and($deductions)->toMatchArray([
            'Asset Accountability Hold' => 1500.00,
            'Last Cash Advance' => 400.00,
            'Withholding Tax' => 252.50,
        ])
        ->and(MoneyHelper::toFloat($result->netPay))->toBe(9947.50);
});

it('computes retirement final pay with settlement adjustments and clearance-based release handling', function () {
    $result = finalSettlementEngine()->compute(
        finalSettlementCompany(),
        finalSettlementEmployee([
            'employee_number' => 'EMP-FS-RETIRE',
            'full_name' => 'Retired Employee',
            'employment_status' => 'retired',
            'monthly_basic_salary' => 50000,
            'date_hired' => '1999-01-10',
            'date_resigned' => '2026-09-05',
        ]),
        [
            'period' => [
                'key' => '2026-RETIRE',
                'start_date' => '2026-09-01',
                'end_date' => '2026-09-15',
                'release_date' => '2026-09-25',
                'run_type' => 'retirement',
            ],
            'adjustments' => [
                [
                    'label' => 'Leave Conversion',
                    'amount' => 4000,
                    'taxable' => true,
                ],
                [
                    'label' => 'Retirement Settlement Adjustment',
                    'amount' => 2000,
                    'taxable' => true,
                ],
                [
                    'label' => 'Clearance Hold Release',
                    'amount' => 1500,
                    'taxable' => false,
                    'separate_payout' => true,
                ],
            ],
            'manual_deductions' => [
                [
                    'label' => 'Last Cooperative Deduction',
                    'amount' => 800,
                ],
            ],
        ],
    );

    expect($result->period->runType)->toBe('retirement')
        ->and($result->period->isFinalSettlementRun())->toBeTrue()
        ->and(MoneyHelper::toFloat($result->rates->scheduledBasicPay))->toBe(8333.33)
        ->and(MoneyHelper::toFloat($result->grossPay))->toBe(14333.33)
        ->and(MoneyHelper::toFloat($result->taxableIncome))->toBe(14333.33)
        ->and($result->deductions[0]->label)->toBe('Last Cooperative Deduction')
        ->and(MoneyHelper::toFloat($result->deductions[0]->amount))->toBe(800.00)
        ->and($result->separatePayouts)->toHaveCount(1)
        ->and($result->separatePayouts[0]->label)->toBe('Clearance Hold Release')
        ->and(MoneyHelper::toFloat($result->separatePayouts[0]->amount))->toBe(1500.00)
        ->and(MoneyHelper::toFloat($result->netPay))->toBe(12945.83)
        ->and(MoneyHelper::toFloat($result->takeHomePay))->toBe(14445.83);
});
