<?php

namespace QuillBytes\PayrollEngine\Calculators;

use QuillBytes\PayrollEngine\Data\PayrollLine;
use QuillBytes\PayrollEngine\Support\MoneyHelper;
use QuillBytes\PayrollEngine\Support\TraceMetadata;
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
            'employee' => new PayrollLine(
                'employee_contribution',
                'PhilHealth Contribution',
                $employee,
                false,
                TraceMetadata::line(
                    source: 'philhealth_calculator',
                    appliedRule: 'philhealth_employee_share',
                    formula: $manualEmployeeContribution !== null
                        ? 'manual employee contribution'
                        : 'monthly_premium / 2',
                    basis: [
                        'monthly_salary' => $monthlySalary,
                        'salary_base' => $salaryBase,
                        'monthly_premium' => $monthlyPremium,
                        'period_divisor' => $periodDivisor,
                    ],
                    extra: [
                        'manual_override' => $manualEmployeeContribution !== null,
                    ],
                ),
            ),
            'employer' => new PayrollLine(
                'employer_contribution',
                'Employer PhilHealth Contribution',
                $employer,
                false,
                TraceMetadata::line(
                    source: 'philhealth_calculator',
                    appliedRule: 'philhealth_employer_share',
                    formula: 'monthly_premium / 2',
                    basis: [
                        'monthly_salary' => $monthlySalary,
                        'salary_base' => $salaryBase,
                        'monthly_premium' => $monthlyPremium,
                        'period_divisor' => $periodDivisor,
                    ],
                ),
            ),
        ];
    }
}
