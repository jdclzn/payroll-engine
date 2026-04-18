<?php

namespace Jdclzn\PayrollEngine\Calculators;

use Jdclzn\PayrollEngine\Contracts\WithholdingTaxCalculator as WithholdingTaxCalculatorContract;
use Jdclzn\PayrollEngine\Data\CompanyProfile;
use Jdclzn\PayrollEngine\Data\EmployeeProfile;
use Jdclzn\PayrollEngine\Data\PayrollInput;
use Jdclzn\PayrollEngine\Data\PayrollLine;
use Jdclzn\PayrollEngine\Enums\TaxStrategy;
use Jdclzn\PayrollEngine\Support\MoneyHelper;
use Money\Money;

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
            return new PayrollLine('deduction', 'Withholding Tax', MoneyHelper::zero());
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
        );
    }

    public function calculateBonusTax(
        CompanyProfile $company,
        EmployeeProfile $employee,
        PayrollInput $input,
        Money $projectedAnnualTaxableIncome,
    ): PayrollLine {
        if ($input->bonus->isZero()) {
            return new PayrollLine('deduction', 'Bonus Tax Withheld', MoneyHelper::zero());
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
            return new PayrollLine('deduction', 'Bonus Tax Withheld', MoneyHelper::zero());
        }

        $taxBefore = $this->annualTax($projectedAnnualTaxableIncome);
        $taxAfter = $this->annualTax($projectedAnnualTaxableIncome->add($taxableBonus));

        return new PayrollLine(
            type: 'deduction',
            label: 'Bonus Tax Withheld',
            amount: MoneyHelper::max($taxAfter->subtract($taxBefore), MoneyHelper::zero()),
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
