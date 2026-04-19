<?php

namespace QuillBytes\PayrollEngine\Tests;

use QuillBytes\PayrollEngine\Exceptions\InvalidPayrollData;
use QuillBytes\PayrollEngine\PayrollEngine;
use QuillBytes\PayrollEngine\Support\MoneyHelper;

function edgePolicyEngine(): PayrollEngine
{
    return new PayrollEngine;
}

/**
 * @return array<string, mixed>
 */
function edgePolicyCompany(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Edge Policy Client',
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
function edgePolicyEmployee(array $overrides = []): array
{
    return array_replace_recursive([
        'employee_number' => 'EMP-EDGE-001',
        'full_name' => 'Edge Policy Employee',
        'employment_status' => 'active',
        'date_hired' => '2024-01-10',
        'department' => 'Finance',
        'email' => 'edge.policy@example.com',
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

it('rejects missing attendance data when the attendance policy requires it', function () {
    expect(fn () => edgePolicyEngine()->compute(
        edgePolicyCompany([
            'edge_case_policy' => [
                'no_attendance_data' => 'error',
            ],
        ]),
        edgePolicyEmployee([
            'attendance_required' => true,
        ]),
        [
            'period' => [
                'key' => '2026-EDGE-ATTENDANCE',
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-15',
                'release_date' => '2026-06-15',
            ],
        ],
    ))->toThrow(
        InvalidPayrollData::class,
        'Attendance data is required by policy but was not provided.',
    );
});

it('merges overlapping deductions through the deduction overlap policy', function () {
    $result = edgePolicyEngine()->compute(
        edgePolicyCompany([
            'edge_case_policy' => [
                'overlapping_deductions' => 'merge',
            ],
        ]),
        edgePolicyEmployee(),
        [
            'period' => [
                'key' => '2026-EDGE-OVERLAP',
                'start_date' => '2026-06-05',
                'end_date' => '2026-06-05',
                'release_date' => '2026-06-05',
                'run_type' => 'adjustment',
            ],
            'adjustments' => [
                [
                    'label' => 'Manual Correction',
                    'amount' => 5000,
                    'taxable' => true,
                ],
            ],
            'manual_deductions' => [
                [
                    'label' => 'Cash Advance',
                    'amount' => 300,
                ],
                [
                    'label' => 'Cash Advance',
                    'amount' => 200,
                ],
            ],
            'loan_deductions' => [
                [
                    'label' => 'Salary Loan',
                    'amount' => 150,
                    'loan_reference' => 'LN-001',
                ],
                [
                    'label' => 'Salary Loan',
                    'amount' => 250,
                    'loan_reference' => 'LN-001',
                ],
            ],
        ],
    );

    expect($result->deductions)->toHaveCount(2)
        ->and($result->deductions[0]->label)->toBe('Salary Loan')
        ->and(MoneyHelper::toFloat($result->deductions[0]->amount))->toBe(400.00)
        ->and($result->deductions[1]->label)->toBe('Cash Advance')
        ->and(MoneyHelper::toFloat($result->deductions[1]->amount))->toBe(500.00)
        ->and(MoneyHelper::toFloat($result->netPay))->toBe(4100.00);
});

it('rejects conflicting rule sets through the rule conflict policy', function () {
    expect(fn () => edgePolicyEngine()->compute(
        edgePolicyCompany([
            'edge_case_policy' => [
                'negative_net_pay' => 'allow',
            ],
        ]),
        edgePolicyEmployee(),
        [
            'period' => [
                'key' => '2026-EDGE-CONFLICT',
                'start_date' => '2026-06-10',
                'end_date' => '2026-06-10',
                'release_date' => '2026-06-10',
                'run_type' => 'adjustment',
            ],
            'edge_case_policy' => [
                'partial_payout_limit' => 1000,
            ],
            'adjustments' => [
                [
                    'label' => 'Conflict Check',
                    'amount' => 2000,
                ],
            ],
        ],
    ))->toThrow(
        InvalidPayrollData::class,
        'Edge case policies conflict: negative net pay cannot be allowed when minimum take-home pay or partial payout rules are also configured.',
    );
});

it('defers deferrable deductions when net pay is insufficient under policy', function () {
    $result = edgePolicyEngine()->compute(
        edgePolicyCompany([
            'edge_case_policy' => [
                'negative_net_pay' => 'defer_deductions',
                'minimum_take_home_pay' => 500,
            ],
        ]),
        edgePolicyEmployee(),
        [
            'period' => [
                'key' => '2026-EDGE-INSUFFICIENT',
                'start_date' => '2026-06-12',
                'end_date' => '2026-06-12',
                'release_date' => '2026-06-12',
                'run_type' => 'adjustment',
            ],
            'adjustments' => [
                [
                    'label' => 'Manual Correction',
                    'amount' => 1000,
                    'taxable' => true,
                ],
            ],
            'manual_deductions' => [
                [
                    'label' => 'Cash Advance',
                    'amount' => 900,
                ],
                [
                    'label' => 'Penalty',
                    'amount' => 300,
                ],
            ],
        ],
    );

    expect($result->deductions)->toHaveCount(1)
        ->and($result->deductions[0]->label)->toBe('Cash Advance')
        ->and(MoneyHelper::toFloat($result->deductions[0]->amount))->toBe(500.00)
        ->and(MoneyHelper::toFloat($result->netPay))->toBe(500.00)
        ->and($result->issues)->toHaveCount(1)
        ->and($result->issues[0]->code)->toBe('insufficient_net_pay')
        ->and($result->issues[0]->metadata['deferred_deductions'])->toHaveCount(2);
});

it('rejects negative payroll results when policy forbids them', function () {
    expect(fn () => edgePolicyEngine()->compute(
        edgePolicyCompany([
            'edge_case_policy' => [
                'negative_net_pay' => 'error',
            ],
        ]),
        edgePolicyEmployee(),
        [
            'period' => [
                'key' => '2026-EDGE-NEGATIVE',
                'start_date' => '2026-06-14',
                'end_date' => '2026-06-14',
                'release_date' => '2026-06-14',
                'run_type' => 'adjustment',
            ],
            'adjustments' => [
                [
                    'label' => 'Tiny Adjustment',
                    'amount' => 100,
                    'taxable' => true,
                ],
            ],
            'manual_deductions' => [
                [
                    'label' => 'Recovery',
                    'amount' => 300,
                ],
            ],
        ],
    ))->toThrow(
        InvalidPayrollData::class,
        'Net pay is insufficient under the configured payroll policy.',
    );
});

it('supports partial payout through the net pay resolution policy', function () {
    $engine = edgePolicyEngine();
    $result = $engine->compute(
        edgePolicyCompany([
            'edge_case_policy' => [
                'negative_net_pay' => 'error',
                'partial_payout_limit' => 2000,
            ],
        ]),
        edgePolicyEmployee(),
        [
            'period' => [
                'key' => '2026-EDGE-PARTIAL',
                'start_date' => '2026-06-15',
                'end_date' => '2026-06-15',
                'release_date' => '2026-06-15',
                'run_type' => 'adjustment',
            ],
            'adjustments' => [
                [
                    'label' => 'Special Release',
                    'amount' => 5000,
                    'taxable' => true,
                ],
            ],
        ],
    );
    $payslip = $engine->payslip($result);

    expect(MoneyHelper::toFloat($result->netPay))->toBe(5000.00)
        ->and(MoneyHelper::toFloat($result->takeHomePay))->toBe(2000.00)
        ->and($result->issues)->toHaveCount(1)
        ->and($result->issues[0]->code)->toBe('partial_payout')
        ->and($payslip['issues'][0]['code'])->toBe('partial_payout')
        ->and($payslip['issues'][0]['metadata']['withheld_amount'])->toBe(3000.00);
});
