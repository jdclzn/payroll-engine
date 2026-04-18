<?php

namespace Jdclzn\PayrollEngine\Tests;

use Jdclzn\PayrollEngine\Contracts\OvertimeCalculator as OvertimeCalculatorContract;
use Jdclzn\PayrollEngine\Contracts\PayrollEdgeCasePolicy;
use Jdclzn\PayrollEngine\Data\CompanyProfile;
use Jdclzn\PayrollEngine\Data\EmployeeProfile;
use Jdclzn\PayrollEngine\Data\PayrollInput;
use Jdclzn\PayrollEngine\Data\PayrollIssue;
use Jdclzn\PayrollEngine\Data\PayrollLine;
use Jdclzn\PayrollEngine\Data\PayrollResult;
use Jdclzn\PayrollEngine\Data\RateSnapshot;
use Jdclzn\PayrollEngine\PayrollEngine;
use Jdclzn\PayrollEngine\Support\MoneyHelper;

function designRulesEngine(array $config = []): PayrollEngine
{
    return new PayrollEngine($config);
}

/**
 * @return array<string, mixed>
 */
function designRulesCompany(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Design Rules Client',
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
function designRulesEmployee(array $overrides = []): array
{
    return array_replace_recursive([
        'employee_number' => 'EMP-DESIGN-001',
        'full_name' => 'Design Rules Employee',
        'employment_status' => 'active',
        'date_hired' => '2024-01-10',
        'department' => 'Finance',
        'email' => 'design.rules@example.com',
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

function designRulesCustomOvertime(): OvertimeCalculatorContract
{
    return new class implements OvertimeCalculatorContract
    {
        public function calculate(CompanyProfile $company, PayrollInput $input, RateSnapshot $rates): array
        {
            return [
                new PayrollLine('earning', 'Design Overtime', MoneyHelper::fromNumeric(777, $rates->hourlyRate), true),
            ];
        }
    };
}

function designRulesCustomPolicy(): PayrollEdgeCasePolicy
{
    return new class implements PayrollEdgeCasePolicy
    {
        public function prepare(CompanyProfile $company, EmployeeProfile $employee, PayrollInput $input): PayrollInput
        {
            return $input;
        }

        public function finalize(CompanyProfile $company, EmployeeProfile $employee, PayrollInput $input, PayrollResult $result): PayrollResult
        {
            return $result->with([
                'issues' => [
                    ...$result->issues,
                    new PayrollIssue(
                        code: 'design_rule_policy',
                        message: 'Custom edge policy applied.',
                        severity: 'info',
                    ),
                ],
            ]);
        }
    };
}

it('normalizes raw input before computation and returns traceable audit breakdowns', function () {
    $engine = designRulesEngine();
    $result = $engine->compute(
        designRulesCompany([
            'attendance_required' => true,
            'edge_case_policy' => [
                'no_attendance_data' => 'warn',
            ],
        ]),
        designRulesEmployee(),
        [
            'period' => [
                'key' => '2026-DESIGN-A',
                'start_date' => '2026-07-01',
                'end_date' => '2026-07-15',
                'release_date' => '2026-07-15',
            ],
            'overtime' => [
                [
                    'type' => 'regular',
                    'hours' => 2,
                ],
            ],
            'adjustments' => [
                [
                    'label' => 'Traceable Adjustment',
                    'amount' => 250,
                    'taxable' => true,
                ],
            ],
            'manual_deductions' => [
                [
                    'label' => 'Traceable Deduction',
                    'amount' => 100,
                ],
            ],
        ],
    );
    $payslip = $engine->payslip($result);
    $allLines = [
        ...$result->earnings,
        ...$result->deductions,
        ...$result->employeeContributions,
        ...$result->employerContributions,
        ...$result->separatePayouts,
    ];

    expect($result->audit['input_normalization'])->toBe([
        'company' => \Jdclzn\PayrollEngine\Data\CompanyProfile::class,
        'employee' => \Jdclzn\PayrollEngine\Data\EmployeeProfile::class,
        'input' => \Jdclzn\PayrollEngine\Data\PayrollInput::class,
    ])
        ->and($result->audit['applied_rules']['strategies']['workflow'])->toContain('PayrollCalculator')
        ->and($result->audit['rates_used']['monthly_basic_salary'])->toBe(30000.00)
        ->and($result->audit['formulas']['daily_rate'])->toBe('monthly_basic_salary * 12 / eemr_factor')
        ->and($result->audit['warnings_exceptions'][0]['code'])->toBe('no_attendance_data')
        ->and($payslip['audit']['warnings_exceptions'][0]['code'])->toBe('no_attendance_data');

    foreach ($allLines as $line) {
        expect($line->metadata)->toHaveKey('source')
            ->and($line->metadata)->toHaveKey('applied_rule')
            ->and($line->metadata)->toHaveKey('formula')
            ->and($line->metadata)->toHaveKey('basis');
    }
});

it('keeps formulas and edge handling replaceable through strategies and policy objects', function () {
    $policy = designRulesCustomPolicy();
    $result = designRulesEngine([
        'strategies' => [
            'clients' => [
                'design-client' => [
                    'overtime' => designRulesCustomOvertime(),
                ],
            ],
        ],
        'edge_case_policies' => [$policy],
    ])->compute(
        designRulesCompany([
            'client_code' => 'design-client',
        ]),
        designRulesEmployee(),
        [
            'period' => [
                'key' => '2026-DESIGN-B',
                'start_date' => '2026-07-20',
                'end_date' => '2026-07-20',
                'release_date' => '2026-07-20',
                'run_type' => 'special',
            ],
            'overtime' => [
                [
                    'type' => 'regular',
                    'hours' => 1,
                ],
            ],
        ],
    );

    expect($result->earnings)->toHaveCount(1)
        ->and($result->earnings[0]->label)->toBe('Design Overtime')
        ->and($result->earnings[0]->metadata['source'])->toBe('payroll_calculator')
        ->and($result->audit['applied_rules']['strategies']['overtime'])->not->toBe(\Jdclzn\PayrollEngine\Calculators\OvertimeCalculator::class)
        ->and($result->audit['applied_rules']['strategies']['overtime'])->toContain('@anonymous')
        ->and($result->audit['applied_rules']['policies'])->toContain($policy::class)
        ->and($result->issues)->toHaveCount(1)
        ->and($result->issues[0]->code)->toBe('design_rule_policy')
        ->and($result->audit['warnings_exceptions'][0]['code'])->toBe('design_rule_policy');
});
