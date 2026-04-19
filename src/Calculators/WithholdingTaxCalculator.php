<?php

namespace QuillBytes\PayrollEngine\Calculators;

use Money\Money;
use QuillBytes\PayrollEngine\Contracts\WithholdingTaxCalculator as WithholdingTaxCalculatorContract;
use QuillBytes\PayrollEngine\Data\CompanyProfile;
use QuillBytes\PayrollEngine\Data\EmployeeProfile;
use QuillBytes\PayrollEngine\Data\PayrollInput;
use QuillBytes\PayrollEngine\Data\PayrollLine;
use QuillBytes\PayrollEngine\Enums\TaxStrategy;
use QuillBytes\PayrollEngine\Support\MoneyHelper;
use QuillBytes\PayrollEngine\Support\TraceMetadata;

final class WithholdingTaxCalculator implements WithholdingTaxCalculatorContract
{
    public function calculateRegular(
        CompanyProfile $company,
        EmployeeProfile $employee,
        PayrollInput $input,
        Money $taxableIncomeAfterMandatory,
    ): PayrollLine {
        if (
            $employee->statutory->minimumWageEarner
            || ! $input->period->normalizedRunType()->usesRegularWithholding()
            || $taxableIncomeAfterMandatory->isNegative()
        ) {
            return new PayrollLine(
                'deduction',
                'Withholding Tax',
                MoneyHelper::zero(),
                false,
                TraceMetadata::line(
                    source: 'withholding_tax_calculator',
                    appliedRule: 'withholding_tax',
                    formula: 'not applicable for this payroll run',
                    basis: [
                        'taxable_income_after_mandatory' => $taxableIncomeAfterMandatory,
                    ],
                ),
            );
        }

        $annualizedIncome = match ($company->taxStrategy) {
            TaxStrategy::ProjectedAnnualized => $input->projectedAnnualTaxableIncome
                ?? $employee->compensation->projectedAnnualTaxableIncome
                ?? MoneyHelper::multiply($taxableIncomeAfterMandatory, $company->periodsPerYear()),
            default => MoneyHelper::multiply($taxableIncomeAfterMandatory, $company->periodsPerYear()),
        };

        $annualTax = $this->annualTax($annualizedIncome);

        return new PayrollLine(
            type: 'deduction',
            label: 'Withholding Tax',
            amount: MoneyHelper::divide($annualTax, $company->periodsPerYear()),
            taxable: false,
            metadata: TraceMetadata::line(
                source: 'withholding_tax_calculator',
                appliedRule: 'withholding_tax',
                formula: 'annual_tax(annualized_income) / periods_per_year',
                basis: [
                    'taxable_income_after_mandatory' => $taxableIncomeAfterMandatory,
                    'annualized_income' => $annualizedIncome,
                    'annual_tax' => $annualTax,
                    'periods_per_year' => $company->periodsPerYear(),
                ],
                extra: [
                    'tax_strategy' => $company->taxStrategy->value,
                ],
            ),
        );
    }

    public function calculateBonusTax(
        CompanyProfile $company,
        EmployeeProfile $employee,
        PayrollInput $input,
        Money $projectedAnnualTaxableIncome,
    ): PayrollLine {
        if ($input->bonus->isZero()) {
            return new PayrollLine(
                'deduction',
                'Bonus Tax Withheld',
                MoneyHelper::zero(),
                false,
                TraceMetadata::line(
                    source: 'withholding_tax_calculator',
                    appliedRule: 'bonus_tax_withheld',
                    formula: 'no taxable bonus',
                    basis: [
                        'bonus_amount' => $input->bonus,
                    ],
                ),
            );
        }

        $configuredShield = $employee->bonusTaxShieldAmount->isZero()
            ? $company->annualBonusTaxShield
            : $employee->bonusTaxShieldAmount;
        $remainingShield = MoneyHelper::max(
            $configuredShield->subtract($input->usedAnnualBonusShield),
            MoneyHelper::zero()
        );
        $taxableBonus = MoneyHelper::max(
            $input->bonus->subtract($remainingShield),
            MoneyHelper::zero()
        );

        if ($employee->statutory->minimumWageEarner || $taxableBonus->isZero()) {
            return new PayrollLine(
                'deduction',
                'Bonus Tax Withheld',
                MoneyHelper::zero(),
                false,
                TraceMetadata::line(
                    source: 'withholding_tax_calculator',
                    appliedRule: 'bonus_tax_withheld',
                    formula: 'no taxable bonus after shield',
                    basis: [
                        'bonus_amount' => $input->bonus,
                        'remaining_shield' => $remainingShield,
                        'taxable_bonus' => $taxableBonus,
                    ],
                ),
            );
        }

        $taxBefore = $this->annualTax($projectedAnnualTaxableIncome);
        $taxAfter = $this->annualTax($projectedAnnualTaxableIncome->add($taxableBonus));

        return new PayrollLine(
            type: 'deduction',
            label: 'Bonus Tax Withheld',
            amount: MoneyHelper::max($taxAfter->subtract($taxBefore), MoneyHelper::zero()),
            taxable: false,
            metadata: TraceMetadata::line(
                source: 'withholding_tax_calculator',
                appliedRule: 'bonus_tax_withheld',
                formula: 'annual_tax(projected_income + taxable_bonus) - annual_tax(projected_income)',
                basis: [
                    'projected_annual_taxable_income' => $projectedAnnualTaxableIncome,
                    'remaining_shield' => $remainingShield,
                    'taxable_bonus' => $taxableBonus,
                    'tax_before' => $taxBefore,
                    'tax_after' => $taxAfter,
                ],
            ),
        );
    }

    public function annualTax(Money $annualTaxableIncome): Money
    {
        $income = max(0, MoneyHelper::toFloat($annualTaxableIncome));

        $tax = match (true) {
            $income <= 250000 => 0,
            $income <= 400000 => ($income - 250000) * 0.15,
            $income <= 800000 => 22500 + (($income - 400000) * 0.20),
            $income <= 2000000 => 102500 + (($income - 800000) * 0.25),
            $income <= 8000000 => 402500 + (($income - 2000000) * 0.30),
            default => 2202500 + (($income - 8000000) * 0.35),
        };

        return MoneyHelper::fromNumeric($tax);
    }
}
