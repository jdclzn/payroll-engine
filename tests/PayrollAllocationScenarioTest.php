<?php

namespace Jdclzn\PayrollEngine\Tests;

use Jdclzn\PayrollEngine\PayrollEngine;

function allocationEngine(): PayrollEngine
{
    return new PayrollEngine;
}

/**
 * @return array<string, mixed>
 */
function allocationCompany(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Allocation Client',
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
function allocationEmployee(array $overrides = []): array
{
    return array_replace_recursive([
        'employee_number' => 'EMP-ALLOC-001',
        'full_name' => 'Allocation Employee',
        'employment_status' => 'active',
        'date_hired' => '2024-01-10',
        'department' => 'Operations',
        'email' => 'allocation.employee@example.com',
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

it('surfaces project cost-center branch department vessel and custom allocation fields in payroll outputs', function () {
    $engine = allocationEngine();
    $result = $engine->compute(
        allocationCompany(),
        allocationEmployee([
            'employee_number' => 'EMP-ALLOC-OUTPUT',
            'full_name' => 'Output Allocation Employee',
            'project_code' => 'PRJ-ALPHA',
            'project_name' => 'Alpha Implementation',
            'cost_center' => 'CC-OPS',
            'department' => 'Operations',
            'branch' => 'Makati Main',
            'vessel' => 'MV Horizon',
            'allocation_dimensions' => [
                'cluster' => 'north',
                'client_group' => 'enterprise',
            ],
        ]),
        [
            'period' => [
                'key' => '2026-ALLOC-OUT',
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-15',
                'release_date' => '2026-10-15',
            ],
        ],
    );

    $register = $engine->payrollRegister([$result]);
    $payslip = $engine->payslip($result);

    expect($register)->toHaveCount(1)
        ->and($register[0]['project_code'])->toBe('PRJ-ALPHA')
        ->and($register[0]['project_name'])->toBe('Alpha Implementation')
        ->and($register[0]['cost_center'])->toBe('CC-OPS')
        ->and($register[0]['branch'])->toBe('Makati Main')
        ->and($register[0]['department'])->toBe('Operations')
        ->and($register[0]['vessel'])->toBe('MV Horizon')
        ->and($payslip['allocation']['project_code'])->toBe('PRJ-ALPHA')
        ->and($payslip['allocation']['project_name'])->toBe('Alpha Implementation')
        ->and($payslip['allocation']['cost_center'])->toBe('CC-OPS')
        ->and($payslip['allocation']['branch'])->toBe('Makati Main')
        ->and($payslip['allocation']['department'])->toBe('Operations')
        ->and($payslip['allocation']['vessel'])->toBe('MV Horizon')
        ->and($payslip['allocation']['dimensions'])->toMatchArray([
            'cluster' => 'north',
            'client_group' => 'enterprise',
        ]);
});

it('builds allocation summaries for project branch vessel department and custom dimensions', function () {
    $engine = allocationEngine();
    $run = $engine->run(
        allocationCompany(),
        [
            'key' => '2026-ALLOC-SUM',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-15',
            'release_date' => '2026-10-15',
        ],
        [
            [
                'employee' => allocationEmployee([
                    'employee_number' => 'EMP-ALLOC-001',
                    'full_name' => 'Alpha One',
                    'monthly_basic_salary' => 30000,
                    'project_code' => 'PRJ-ALPHA',
                    'project_name' => 'Alpha Implementation',
                    'cost_center' => 'CC-OPS',
                    'branch' => 'Makati Main',
                    'department' => 'Operations',
                    'allocation_dimensions' => ['cluster' => 'north'],
                ]),
            ],
            [
                'employee' => allocationEmployee([
                    'employee_number' => 'EMP-ALLOC-002',
                    'full_name' => 'Alpha Two',
                    'monthly_basic_salary' => 30000,
                    'project_code' => 'PRJ-ALPHA',
                    'project_name' => 'Alpha Implementation',
                    'cost_center' => 'CC-OPS',
                    'branch' => 'Makati Main',
                    'department' => 'Operations',
                    'allocation_dimensions' => ['cluster' => 'north'],
                ]),
            ],
            [
                'employee' => allocationEmployee([
                    'employee_number' => 'EMP-ALLOC-003',
                    'full_name' => 'Beta Marine',
                    'monthly_basic_salary' => 40000,
                    'project_code' => 'PRJ-BETA',
                    'project_name' => 'Beta Vessel Support',
                    'cost_center' => 'CC-MAR',
                    'branch' => 'Cebu Port',
                    'department' => 'Marine',
                    'vessel' => 'MV Pioneer',
                    'allocation_dimensions' => ['cluster' => 'south'],
                ]),
            ],
            [
                'employee' => allocationEmployee([
                    'employee_number' => 'EMP-ALLOC-004',
                    'full_name' => 'Shared Marine',
                    'monthly_basic_salary' => 20000,
                    'branch' => 'Cebu Port',
                    'department' => 'Marine',
                    'cost_center' => 'CC-MAR',
                    'vessel' => 'MV Pioneer',
                    'allocation_dimensions' => ['cluster' => 'south'],
                ]),
            ],
        ],
    );

    $register = $engine->payrollRegister($run->results);
    $projectSummary = $engine->allocationSummary($run->results, 'project_code');
    $branchSummary = $engine->allocationSummary($run->results, 'branch');
    $vesselSummary = $engine->allocationSummary($run->results, 'vessel');
    $departmentSummary = $engine->allocationSummary($run->results, 'department');
    $clusterSummary = $engine->allocationSummary($run->results, 'cluster');

    $projectRows = [];
    $branchRows = [];
    $vesselRows = [];
    $departmentRows = [];
    $clusterRows = [];

    foreach ($projectSummary as $row) {
        $projectRows[$row['value']] = $row;
    }

    foreach ($branchSummary as $row) {
        $branchRows[$row['value']] = $row;
    }

    foreach ($vesselSummary as $row) {
        $vesselRows[$row['value']] = $row;
    }

    foreach ($departmentSummary as $row) {
        $departmentRows[$row['value']] = $row;
    }

    foreach ($clusterSummary as $row) {
        $clusterRows[$row['value']] = $row;
    }

    expect($register)->toHaveCount(4)
        ->and($register[0]['project_code'])->toBe('PRJ-ALPHA')
        ->and($register[2]['vessel'])->toBe('MV Pioneer')
        ->and($register[3]['project_code'])->toBeNull()
        ->and($projectRows['PRJ-ALPHA'])->toMatchArray([
            'dimension' => 'project_code',
            'value' => 'PRJ-ALPHA',
            'employee_count' => 2,
            'gross_pay' => 30000.00,
        ])
        ->and($projectRows['PRJ-BETA'])->toMatchArray([
            'dimension' => 'project_code',
            'value' => 'PRJ-BETA',
            'employee_count' => 1,
            'gross_pay' => 20000.00,
        ])
        ->and($projectRows['unassigned'])->toMatchArray([
            'dimension' => 'project_code',
            'value' => 'unassigned',
            'employee_count' => 1,
            'gross_pay' => 10000.00,
        ])
        ->and($branchRows['Makati Main'])->toMatchArray([
            'dimension' => 'branch',
            'value' => 'Makati Main',
            'employee_count' => 2,
            'gross_pay' => 30000.00,
        ])
        ->and($branchRows['Cebu Port'])->toMatchArray([
            'dimension' => 'branch',
            'value' => 'Cebu Port',
            'employee_count' => 2,
            'gross_pay' => 30000.00,
        ])
        ->and($vesselRows['MV Pioneer'])->toMatchArray([
            'dimension' => 'vessel',
            'value' => 'MV Pioneer',
            'employee_count' => 2,
            'gross_pay' => 30000.00,
        ])
        ->and($vesselRows['unassigned'])->toMatchArray([
            'dimension' => 'vessel',
            'value' => 'unassigned',
            'employee_count' => 2,
            'gross_pay' => 30000.00,
        ])
        ->and($departmentRows['Operations'])->toMatchArray([
            'dimension' => 'department',
            'value' => 'Operations',
            'employee_count' => 2,
            'gross_pay' => 30000.00,
        ])
        ->and($departmentRows['Marine'])->toMatchArray([
            'dimension' => 'department',
            'value' => 'Marine',
            'employee_count' => 2,
            'gross_pay' => 30000.00,
        ])
        ->and($clusterRows['north'])->toMatchArray([
            'dimension' => 'cluster',
            'value' => 'north',
            'employee_count' => 2,
            'gross_pay' => 30000.00,
        ])
        ->and($clusterRows['south'])->toMatchArray([
            'dimension' => 'cluster',
            'value' => 'south',
            'employee_count' => 2,
            'gross_pay' => 30000.00,
        ]);
});

it('keeps non-project payroll working when all allocation fields are omitted', function () {
    $engine = allocationEngine();
    $result = $engine->compute(
        allocationCompany(),
        allocationEmployee([
            'employee_number' => 'EMP-ALLOC-NONE',
            'full_name' => 'No Allocation Employee',
            'department' => 'Shared Services',
        ]),
        [
            'period' => [
                'key' => '2026-ALLOC-NONE',
                'start_date' => '2026-10-01',
                'end_date' => '2026-10-15',
                'release_date' => '2026-10-15',
            ],
        ],
    );

    $register = $engine->payrollRegister([$result]);
    $payslip = $engine->payslip($result);
    $summary = $engine->allocationSummary([$result], 'project_code');

    expect($register)->toHaveCount(1)
        ->and($register[0]['project_code'])->toBeNull()
        ->and($register[0]['project_name'])->toBeNull()
        ->and($register[0]['cost_center'])->toBeNull()
        ->and($register[0]['branch'])->toBe('Makati Main')
        ->and($register[0]['department'])->toBe('Shared Services')
        ->and($register[0]['vessel'])->toBeNull()
        ->and($payslip['allocation'])->toMatchArray([
            'project_code' => null,
            'project_name' => null,
            'cost_center' => null,
            'branch' => 'Makati Main',
            'department' => 'Shared Services',
            'vessel' => null,
            'dimensions' => [],
        ])
        ->and($summary)->toHaveCount(1)
        ->and($summary[0])->toMatchArray([
            'dimension' => 'project_code',
            'value' => 'unassigned',
            'employee_count' => 1,
            'gross_pay' => 15000.00,
        ]);
});
