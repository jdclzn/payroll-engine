<?php

namespace Jdclzn\PayrollEngine\Support;

use Jdclzn\PayrollEngine\Data\Adjustment;
use Jdclzn\PayrollEngine\Data\Deduction;
use Jdclzn\PayrollEngine\Data\PayrollInput;
use Jdclzn\PayrollEngine\Data\PayrollLine;
use Jdclzn\PayrollEngine\Data\PayrollPeriod;
use Jdclzn\PayrollEngine\Data\PayrollResult;
use Jdclzn\PayrollEngine\Exceptions\InvalidPayrollData;
use Money\Money;

final class RetroAdjustmentInputBuilder
{
    public function build(
        PayrollResult $original,
        PayrollResult $recomputed,
        PayrollPeriod $releasePeriod,
    ): PayrollInput {
        $this->assertComparable($original, $recomputed);

        $adjustments = [];
        $deductions = [];

        $this->appendEarningDifferences($adjustments, $deductions, $original->earnings, $recomputed->earnings, false);
        $this->appendEarningDifferences($adjustments, $deductions, $original->separatePayouts, $recomputed->separatePayouts, true);
        $this->appendDeductionDifferences($adjustments, $deductions, $original->deductions, $recomputed->deductions);
        $this->appendDeductionDifferences($adjustments, $deductions, $original->employeeContributions, $recomputed->employeeContributions);

        if ($adjustments === [] && $deductions === []) {
            throw new InvalidPayrollData('No retroactive payroll differences were found between the original and recomputed results.');
        }

        return new PayrollInput(
            period: $releasePeriod,
            adjustments: $adjustments,
            manualDeductions: $deductions,
        );
    }

    private function assertComparable(PayrollResult $original, PayrollResult $recomputed): void
    {
        if ($original->employee->employeeNumber !== $recomputed->employee->employeeNumber) {
            throw new InvalidPayrollData('Retroactive payroll comparison requires the same employee on both payroll results.');
        }

        if ($original->company->clientCode !== $recomputed->company->clientCode) {
            throw new InvalidPayrollData('Retroactive payroll comparison requires the same payroll client configuration on both payroll results.');
        }

        if (
            ! $original->period->startDate->equalTo($recomputed->period->startDate)
            || ! $original->period->endDate->equalTo($recomputed->period->endDate)
        ) {
            throw new InvalidPayrollData('Retroactive payroll comparison requires both payroll results to cover the same historical period.');
        }
    }

    /**
     * @param  array<int, Adjustment>  $adjustments
     * @param  array<int, Deduction>  $deductions
     * @param  array<int, PayrollLine>  $originalLines
     * @param  array<int, PayrollLine>  $recomputedLines
     */
    private function appendEarningDifferences(
        array &$adjustments,
        array &$deductions,
        array $originalLines,
        array $recomputedLines,
        bool $separatePayout,
    ): void {
        foreach ($this->differenceMap($originalLines, $recomputedLines) as $difference) {
            $label = $difference['label'];
            $taxable = $difference['taxable'];
            $amount = $difference['amount'];

            if ($amount->isPositive()) {
                $adjustments[] = new Adjustment(
                    label: sprintf('Retro %s', $label),
                    amount: $amount,
                    taxable: $taxable,
                    separatePayout: $separatePayout,
                );

                continue;
            }

            $deductions[] = new Deduction(
                label: sprintf('Retro Recovery - %s', $label),
                amount: $amount->absolute(),
            );
        }
    }

    /**
     * @param  array<int, Adjustment>  $adjustments
     * @param  array<int, Deduction>  $deductions
     * @param  array<int, PayrollLine>  $originalLines
     * @param  array<int, PayrollLine>  $recomputedLines
     */
    private function appendDeductionDifferences(
        array &$adjustments,
        array &$deductions,
        array $originalLines,
        array $recomputedLines,
    ): void {
        foreach ($this->differenceMap($originalLines, $recomputedLines) as $difference) {
            $label = $difference['label'];
            $amount = $difference['amount'];

            if ($amount->isPositive()) {
                $deductions[] = new Deduction(
                    label: sprintf('Retro %s', $label),
                    amount: $amount,
                );

                continue;
            }

            $adjustments[] = new Adjustment(
                label: sprintf('Retro Refund - %s', $label),
                amount: $amount->absolute(),
                taxable: false,
            );
        }
    }

    /**
     * @param  array<int, PayrollLine>  $originalLines
     * @param  array<int, PayrollLine>  $recomputedLines
     * @return array<int, array{label:string, taxable:bool, amount:Money}>
     */
    private function differenceMap(array $originalLines, array $recomputedLines): array
    {
        $originalMap = $this->aggregateLines($originalLines);
        $recomputedMap = $this->aggregateLines($recomputedLines);
        $keys = array_values(array_unique([...array_keys($originalMap), ...array_keys($recomputedMap)]));
        $differences = [];

        foreach ($keys as $key) {
            $originalEntry = $originalMap[$key] ?? null;
            $recomputedEntry = $recomputedMap[$key] ?? null;
            $anchor = $recomputedEntry['amount'] ?? $originalEntry['amount'] ?? null;
            $originalAmount = $originalEntry['amount'] ?? MoneyHelper::zero($anchor);
            $recomputedAmount = $recomputedEntry['amount'] ?? MoneyHelper::zero($anchor);
            $delta = $recomputedAmount->subtract($originalAmount);

            if ($delta->isZero()) {
                continue;
            }

            $differences[] = [
                'label' => $recomputedEntry['label'] ?? $originalEntry['label'] ?? 'Adjustment',
                'taxable' => $recomputedEntry['taxable'] ?? $originalEntry['taxable'] ?? false,
                'amount' => $delta,
            ];
        }

        return $differences;
    }

    /**
     * @param  array<int, PayrollLine>  $lines
     * @return array<string, array{label:string, taxable:bool, amount:Money}>
     */
    private function aggregateLines(array $lines): array
    {
        $aggregated = [];

        foreach ($lines as $line) {
            $key = sprintf('%s|%s', $line->label, $line->taxable ? '1' : '0');

            if (! isset($aggregated[$key])) {
                $aggregated[$key] = [
                    'label' => $line->label,
                    'taxable' => $line->taxable,
                    'amount' => $line->amount,
                ];

                continue;
            }

            $aggregated[$key]['amount'] = $aggregated[$key]['amount']->add($line->amount);
        }

        return $aggregated;
    }
}
