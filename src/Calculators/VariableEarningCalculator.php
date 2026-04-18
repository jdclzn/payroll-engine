<?php

namespace Jdclzn\PayrollEngine\Calculators;

use Jdclzn\PayrollEngine\Contracts\VariableEarningCalculator as VariableEarningCalculatorContract;
use Jdclzn\PayrollEngine\Data\CompanyProfile;
use Jdclzn\PayrollEngine\Data\EmployeeProfile;
use Jdclzn\PayrollEngine\Data\PayrollInput;
use Jdclzn\PayrollEngine\Data\PayrollLine;
use Jdclzn\PayrollEngine\Data\RateSnapshot;

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
                metadata: ['variable_earning_type' => $entry->type, ...$entry->metadata],
            );
        }

        return $lines;
    }
}
