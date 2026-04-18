<?php

namespace Jdclzn\PayrollEngine\Policies;

use Jdclzn\PayrollEngine\Contracts\PayrollEdgeCasePolicy;
use Jdclzn\PayrollEngine\Data\CompanyProfile;
use Jdclzn\PayrollEngine\Data\EmployeeProfile;
use Jdclzn\PayrollEngine\Data\PayrollIssue;
use Jdclzn\PayrollEngine\Data\PayrollLine;
use Jdclzn\PayrollEngine\Data\PayrollInput;
use Jdclzn\PayrollEngine\Data\PayrollResult;
use Jdclzn\PayrollEngine\Exceptions\InvalidPayrollData;
use Jdclzn\PayrollEngine\Support\EdgeCasePolicyConfig;
use Jdclzn\PayrollEngine\Support\MoneyHelper;
use Money\Money;

final readonly class NetPayResolutionPolicy implements PayrollEdgeCasePolicy
{
    public function __construct(
        private EdgeCasePolicyConfig $config,
    ) {
    }

    public function prepare(
        CompanyProfile $company,
        EmployeeProfile $employee,
        PayrollInput $input,
    ): PayrollInput {
        return $input;
    }

    public function finalize(
        CompanyProfile $company,
        EmployeeProfile $employee,
        PayrollInput $input,
        PayrollResult $result,
    ): PayrollResult {
        $mode = strtolower((string) $this->config->value($company, $employee, $input, 'negative_net_pay', 'allow'));
        $minimumTakeHomePay = MoneyHelper::fromNumeric($this->config->value($company, $employee, $input, 'minimum_take_home_pay', 0), $result->netPay);
        $updated = $result;

        if ($updated->netPay->lessThan($minimumTakeHomePay)) {
            $updated = match ($mode) {
                'error' => throw new InvalidPayrollData('Net pay is insufficient under the configured payroll policy.'),
                'defer_deductions' => $this->deferDeductions($updated, $minimumTakeHomePay),
                default => $this->appendIssue(
                    $updated,
                    new PayrollIssue(
                        code: 'negative_payroll_result',
                        message: 'Net pay fell below the configured threshold and was allowed by policy.',
                        severity: 'warning',
                        metadata: [
                            'net_pay' => MoneyHelper::toFloat($updated->netPay),
                            'minimum_take_home_pay' => MoneyHelper::toFloat($minimumTakeHomePay),
                        ],
                    ),
                ),
            };
        }

        $partialPayoutLimit = $this->config->value($company, $employee, $input, 'partial_payout_limit');

        if ($partialPayoutLimit === null || $partialPayoutLimit === '') {
            return $updated;
        }

        $payoutLimit = MoneyHelper::fromNumeric($partialPayoutLimit, $updated->takeHomePay);

        if ($updated->takeHomePay->lessThanOrEqual($payoutLimit)) {
            return $updated;
        }

        $withheldAmount = $updated->takeHomePay->subtract($payoutLimit);

        return $this->appendIssue(
            $updated->with(['takeHomePay' => $payoutLimit]),
            new PayrollIssue(
                code: 'partial_payout',
                message: 'The payroll was released as a partial payout under the configured policy.',
                severity: 'warning',
                metadata: [
                    'released_amount' => MoneyHelper::toFloat($payoutLimit),
                    'withheld_amount' => MoneyHelper::toFloat($withheldAmount),
                ],
            ),
        );
    }

    private function deferDeductions(PayrollResult $result, Money $minimumTakeHomePay): PayrollResult
    {
        $shortfall = $minimumTakeHomePay->subtract($result->netPay);
        $deductions = $result->deductions;
        $deferred = [];

        for ($index = count($deductions) - 1; $index >= 0 && $shortfall->isPositive(); $index--) {
            $line = $deductions[$index];

            if (! $this->isDeferrable($line)) {
                continue;
            }

            $reducible = MoneyHelper::min($line->amount, $shortfall);
            $remaining = $line->amount->subtract($reducible);
            $shortfall = $shortfall->subtract($reducible);
            $deferred[] = [
                'label' => $line->label,
                'amount' => MoneyHelper::toFloat($reducible),
            ];

            if ($remaining->isZero()) {
                array_splice($deductions, $index, 1);
                continue;
            }

            $deductions[$index] = new PayrollLine(
                type: $line->type,
                label: $line->label,
                amount: $remaining,
                taxable: $line->taxable,
                metadata: $line->metadata + ['deferred_partial_amount' => MoneyHelper::toFloat($reducible)],
            );
        }

        if ($shortfall->isPositive()) {
            throw new InvalidPayrollData('Net pay remains insufficient after applying the configured deduction deferral policy.');
        }

        $totalDeductions = MoneyHelper::sum(array_map(static fn (PayrollLine $line) => $line->amount, $deductions), $result->grossPay);
        $totalEmployeeContributions = MoneyHelper::sum(array_map(static fn (PayrollLine $line) => $line->amount, $result->employeeContributions), $result->grossPay);
        $separatePayoutTotal = MoneyHelper::sum(array_map(static fn (PayrollLine $line) => $line->amount, $result->separatePayouts), $result->grossPay);
        $netPay = $result->grossPay->subtract($totalEmployeeContributions)->subtract($totalDeductions);
        $takeHomePay = $netPay->add($separatePayoutTotal);

        return $this->appendIssue(
            $result->with([
                'deductions' => array_values($deductions),
                'netPay' => $netPay,
                'takeHomePay' => $takeHomePay,
            ]),
            new PayrollIssue(
                code: 'insufficient_net_pay',
                message: 'Deferrable deductions were held back to satisfy the configured minimum take-home pay.',
                severity: 'warning',
                metadata: [
                    'minimum_take_home_pay' => MoneyHelper::toFloat($minimumTakeHomePay),
                    'deferred_deductions' => array_reverse($deferred),
                ],
            ),
        );
    }

    private function isDeferrable(PayrollLine $line): bool
    {
        if (($line->metadata['non_deferrable'] ?? false) === true) {
            return false;
        }

        return ! in_array($line->label, ['Withholding Tax', 'Bonus Tax Withheld'], true);
    }

    private function appendIssue(PayrollResult $result, PayrollIssue $issue): PayrollResult
    {
        return $result->with([
            'issues' => [...$result->issues, $issue],
        ]);
    }
}
