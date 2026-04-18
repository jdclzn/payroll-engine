<?php

namespace Jdclzn\PayrollEngine\Calculators;

use Jdclzn\PayrollEngine\Contracts\VariableEarningCalculator as VariableEarningCalculatorContract;
use Jdclzn\PayrollEngine\Data\CompanyProfile;
use Jdclzn\PayrollEngine\Data\EmployeeProfile;
use Jdclzn\PayrollEngine\Data\PayrollInput;
use Jdclzn\PayrollEngine\Data\PayrollLine;
use Jdclzn\PayrollEngine\Data\RateSnapshot;
use Jdclzn\PayrollEngine\Support\TraceMetadata;

final class VariableEarningCalculator implements VariableEarningCalculatorContract
{
    public function calculate(
        CompanyProfile $company,
        EmployeeProfile $employee,
        PayrollInput $input,
        RateSnapshot $rates,
    ): array {
        $lines = [];

        foreach ($input->variableEarningEntries as $entry) {
            $lines[] = new PayrollLine(
                type: 'earning',
                label: $entry->label,
                amount: $entry->amount,
                taxable: $entry->taxable,
                metadata: TraceMetadata::line(
                    source: 'payroll_input.variable_earnings',
                    appliedRule: $entry->type,
                    formula: 'input variable earning amount',
                    basis: [
                        'amount' => $entry->amount,
                        'type' => $entry->type,
                    ],
                    extra: ['variable_earning_type' => $entry->type, ...$entry->metadata],
                ),
            );
        }

        return $lines;
    }
}
