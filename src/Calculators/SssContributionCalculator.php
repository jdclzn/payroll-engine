<?php

namespace QuillBytes\PayrollEngine\Calculators;

use Money\Money;
use QuillBytes\PayrollEngine\Data\PayrollLine;
use QuillBytes\PayrollEngine\Support\MoneyHelper;
use QuillBytes\PayrollEngine\Support\TraceMetadata;

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
            'employee' => new PayrollLine(
                'employee_contribution',
                'SSS Contribution',
                $employee,
                false,
                TraceMetadata::line(
                    source: 'sss_calculator',
                    appliedRule: 'sss_employee_share',
                    formula: $manualEmployeeContribution !== null
                        ? 'manual employee contribution'
                        : 'salary_base * 5%',
                    basis: [
                        'monthly_salary' => $monthlySalary,
                        'salary_base' => $base,
                        'period_divisor' => $periodDivisor,
                    ],
                    extra: [
                        'manual_override' => $manualEmployeeContribution !== null,
                    ],
                ),
            ),
            'employer' => new PayrollLine(
                'employer_contribution',
                'Employer SSS Contribution',
                $employer,
                false,
                TraceMetadata::line(
                    source: 'sss_calculator',
                    appliedRule: 'sss_employer_share',
                    formula: 'salary_base * 10% + ec contribution',
                    basis: [
                        'monthly_salary' => $monthlySalary,
                        'salary_base' => $base,
                        'period_divisor' => $periodDivisor,
                    ],
                ),
            ),
        ];
    }
}
