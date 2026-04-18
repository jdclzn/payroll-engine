<?php

namespace Jdclzn\PayrollEngine\Calculators;

use Jdclzn\PayrollEngine\Data\PayrollLine;
use Jdclzn\PayrollEngine\Support\MoneyHelper;
use Money\Money;

final class SssContributionCalculator
{
    /**
     * @return array{employee: PayrollLine, employer: PayrollLine}
     */
    public function calculate(Money $monthlySalary, ?Money $manualEmployeeContribution, int $periodDivisor = 1): array
    {
        $salaryBase = max(5000, min(35000, MoneyHelper::toFloat($monthlySalary)));
        $base = MoneyHelper::fromNumeric($salaryBase);
        $employee = $manualEmployeeContribution ?? MoneyHelper::percentage($base, 5);
        $employer = MoneyHelper::percentage($base, 10)->add(MoneyHelper::fromNumeric($salaryBase <= 14500 ? 10 : 30));

        if ($periodDivisor > 1) {
            $employee = MoneyHelper::divide($employee, $periodDivisor);
            $employer = MoneyHelper::divide($employer, $periodDivisor);
        }

        return [
            'employee' => new PayrollLine('employee_contribution', 'SSS Contribution', $employee),
            'employer' => new PayrollLine('employer_contribution', 'Employer SSS Contribution', $employer),
        ];
    }
}
