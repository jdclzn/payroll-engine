# Payroll Engine

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jdclzn/payroll-engine.svg?style=flat-square)](https://packagist.org/packages/jdclzn/payroll-engine)
[![Total Downloads](https://img.shields.io/packagist/dt/jdclzn/payroll-engine.svg?style=flat-square)](https://packagist.org/packages/jdclzn/payroll-engine)
![GitHub Actions](https://github.com/jdclzn/payroll-engine/actions/workflows/main.yml/badge.svg)

`jdclzn/payroll-engine` is a reusable Laravel-friendly payroll computation library built for auditable, extensible payroll flows. It ships with a Philippines-oriented default computation model, but the engine itself is strategy-based so client, tenant, company, and country-specific rules can be replaced without forking the package.

The package is designed for payroll-heavy Laravel applications that need:
- normalized input before computation
- replaceable formulas for tax, overtime, statutory contributions, variable earnings, and workflow
- traceable payroll breakdowns with formulas, basis amounts, applied rules, and warnings
- payroll run, payslip, register, allocation, retro, off-cycle, and final-pay support

## Requirements

- PHP `^8.2`
- Laravel / Illuminate Support `^12.0|^13.0`

## Installation

```bash
composer require jdclzn/payroll-engine
```

Publish the package config when you want to override defaults or register client-specific strategies:

```bash
php artisan vendor:publish --tag=payroll-engine-config
```

## Quick Start

You can compute payroll from arrays, DTO-like payloads, or Laravel `Model` instances. Inputs are normalized before computation, so the engine can safely consume application-layer data without coupling itself to your Eloquent models.

```php
use Jdclzn\PayrollEngine\PayrollEngine;

$engine = app(PayrollEngine::class);

$result = $engine->compute(
    [
        'name' => 'KRBS Payroll',
        'client_code' => 'krbs',
        'prepared_by' => ['payroll.preparer'],
        'approvers' => ['finance.manager'],
        'administrators' => ['system.admin'],
        'payroll_schedules' => [
            [
                'pay_date' => '15',
                'period_start' => '1',
                'period_end' => '15',
            ],
        ],
    ],
    [
        'employee_number' => 'EMP-001',
        'full_name' => 'Payroll Employee',
        'employment_status' => 'active',
        'date_hired' => '2024-01-15',
        'department' => 'Finance',
        'email' => 'employee@example.com',
        'monthly_basic_salary' => 30000,
        'tax_shield_amount_for_bonuses' => 90000,
        'tin' => '123-456-789',
        'sss_number' => '11-1111111-1',
        'hdmf_number' => '123456789012',
        'phic_number' => '12-345678901-2',
        'account_number' => '001234567890',
        'bank' => 'Payroll Bank',
        'branch' => 'Makati Main',
    ],
    [
        'period' => [
            'key' => '2026-04-A',
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-15',
            'release_date' => '2026-04-15',
            'run_type' => 'regular',
        ],
        'overtime' => [
            ['type' => 'regular', 'hours' => 2],
        ],
        'allowances' => [
            ['label' => 'Rice Allowance', 'amount' => 1500, 'taxable' => false],
        ],
        'manual_deductions' => [
            ['label' => 'Cash Advance', 'amount' => 500],
        ],
    ],
);

$payslip = $engine->payslip($result);
$registerRow = $engine->payrollRegister([$result]);
```

## Laravel Usage

The package auto-registers its service provider and facade through Composer package discovery.

```php
use Jdclzn\PayrollEngine\PayrollEngine;
use Jdclzn\PayrollEngine\PayrollEngineFacade;

$engine = app(PayrollEngine::class);
$sameEngine = app('payroll-engine');

$result = PayrollEngineFacade::compute($company, $employee, $input);
```

Available engine methods:
- `compute()` for a single employee result
- `run()` for multi-employee payroll runs
- `payslip()` for payslip payloads
- `payrollRegister()` for register rows
- `allocationSummary()` for project, branch, department, vessel, or cost-center summaries
- `retroAdjustmentInput()` for difference-only retro releases
- `generatePayrollFiles()` and `generatePayslips()` for workflow-gated exports

## Configuration and Extensibility

The package config lives at [`config/config.php`](config/config.php). The important sections are:

- `defaults`
  Baseline payroll policy values like frequency, hours per day, OT premiums, tax strategy, and Pag-IBIG behavior.
- `presets`
  Client preset overrides such as `krbs`.
- `strategies`
  Replaceable calculators and workflows for rate, overtime, withholding, variable earnings, Pag-IBIG, or the full payroll workflow.
- `edge_case_policies`
  Policy objects for attendance gaps, deduction overlap, insufficient net pay, conflicting rules, and partial payout handling.

Example client override:

```php
'strategies' => [
    'clients' => [
        'tenant-a' => [
            'overtime' => \App\Payroll\Strategies\TenantAOvertimeCalculator::class,
            'withholding' => \App\Payroll\Strategies\TenantAWithholdingCalculator::class,
        ],
        'tenant-b' => [
            'workflow' => \App\Payroll\Strategies\TenantBPayrollWorkflow::class,
        ],
    ],
],
```

This lets you keep the core package stable while swapping only the formulas or business flow that differ by client.

## Auditability

Every computed result carries trace and audit metadata so payroll outputs remain explainable. The result preserves:

- applied strategies and policies
- rates used
- basis amounts
- formulas
- warnings and exceptions

This metadata also flows into payslip payloads, which makes the package easier to debug, review, and support in production payroll operations.

## Test Coverage

The library includes scenario coverage for:

- fixed salary, daily-rated, and hourly payroll
- earnings, deductions, attendance, and final payable breakdowns
- off-cycle, retroactive, final-pay, and allocation payroll runs
- tenant, company, and country-specific extension scenarios
- Laravel package integration for the provider, alias, config publishing, and facade resolution

Run the full suite with:

```bash
composer test
```

## Release Workflow

This package uses [standard-version](https://github.com/conventional-changelog/standard-version) for semantic version bumps based on conventional commits.

- First tagged release:
  `composer run release:first`
- Normal follow-up releases:
  `composer run release`
- Preflight checks only:
  `composer run release:check`

`composer run release` delegates to the local npm `standard-version` install, runs the PHP package checks first, updates `CHANGELOG.md`, bumps the release-tooling version in `package.json`, creates a release commit, and creates the matching git tag.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on recent changes.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for contribution guidelines.

## Security

If you discover any security related issues, please email `jovanie.daclizon@gmail.com` instead of using the issue tracker.

## Credits

- [Jovanie Daclizon](https://github.com/jdclzn)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [LICENSE.md](LICENSE.md) for more information.
