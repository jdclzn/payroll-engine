<?php

namespace Jdclzn\PayrollEngine\Support;

use Jdclzn\PayrollEngine\Data\PayrollLine;
use Jdclzn\PayrollEngine\Data\PayrollResult;

final class PayrollResultTraceEnricher
{
    public function enrich(PayrollResult $result): PayrollResult
    {
        return $result->with([
            'earnings' => $this->enrichLines($result->earnings),
            'deductions' => $this->enrichLines($result->deductions),
            'employeeContributions' => $this->enrichLines($result->employeeContributions),
            'employerContributions' => $this->enrichLines($result->employerContributions),
            'separatePayouts' => $this->enrichLines($result->separatePayouts),
        ]);
    }

    /**
     * @param  array<int, PayrollLine>  $lines
     * @return array<int, PayrollLine>
     */
    private function enrichLines(array $lines): array
    {
        return array_map(function (PayrollLine $line): PayrollLine {
            $existing = TraceMetadata::normalize($line->metadata);
            $basis = is_array($existing['basis'] ?? null)
                ? $existing['basis']
                : ['amount' => MoneyHelper::toFloat($line->amount)];

            return new PayrollLine(
                type: $line->type,
                label: $line->label,
                amount: $line->amount,
                taxable: $line->taxable,
                metadata: [
                    ...$existing,
                    'source' => $existing['source'] ?? $this->defaultSource($line),
                    'applied_rule' => $existing['applied_rule'] ?? $this->defaultRule($line),
                    'formula' => $existing['formula'] ?? 'custom strategy or input-defined amount',
                    'basis' => $basis,
                ],
            );
        }, $lines);
    }

    private function defaultSource(PayrollLine $line): string
    {
        return match ($line->type) {
            'employee_contribution', 'employer_contribution' => 'statutory_calculator',
            'deduction' => 'deduction_input',
            'separate_payout' => 'payroll_payout',
            default => 'payroll_calculator',
        };
    }

    private function defaultRule(PayrollLine $line): string
    {
        $label = strtolower(trim($line->label));
        $label = preg_replace('/[^a-z0-9]+/', '_', $label) ?? 'payroll_line';

        return trim($label, '_') ?: 'payroll_line';
    }
}
