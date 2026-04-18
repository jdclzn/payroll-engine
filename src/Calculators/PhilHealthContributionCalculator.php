<?php

namespace Jdclzn\PayrollEngine\Calculators;

use Jdclzn\PayrollEngine\Data\PayrollLine;
use Jdclzn\PayrollEngine\Support\MoneyHelper;
use Money\Money;

final class PhilHealthContributionCalculator
{
    /**
     * @return array{employee: PayrollLine, employer: PayrollLine}
     */
    public function calculate(Money $monthlySalary, ?Money $manualEmployeeContribution, int $periodDivisor = 1): array
    {
        $salaryBase = max(10000, min(100000, MoneyHelper::toFloat($monthlySalary)));
        $monthlyPremium = MoneyHelper::percentage(MoneyHelper::fromNumeric($salaryBase), 5);
        $employee = $manualEmployeeContribution ?? MoneyHelper::divide($monthlyPremium, 2);
        $employer = MoneyHelper::divide($monthlyPremium, 2);

        if ($periodDivisor > 1) {
            $employee = MoneyHelper::divide($employee, $periodDivisor);
            $employer = MoneyHelper::divide($employer, $periodDivisor);
        }

        return [
            'employee' => new PayrollLine('employee_contribution', 'PhilHealth Contribution', $employee),
            'employer' => new PayrollLine('employer_contribution', 'Employer PhilHealth Contribution', $employer),
        ];
    }
}
