<?php

namespace QuillBytes\PayrollEngine\Calculators;

use QuillBytes\PayrollEngine\Contracts\VariableEarningCalculator as VariableEarningCalculatorContract;
use QuillBytes\PayrollEngine\Data\CompanyProfile;
use QuillBytes\PayrollEngine\Data\EmployeeProfile;
use QuillBytes\PayrollEngine\Data\PayrollInput;
use QuillBytes\PayrollEngine\Data\PayrollLine;
use QuillBytes\PayrollEngine\Data\RateSnapshot;
use QuillBytes\PayrollEngine\Support\TraceMetadata;

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
