<?php

namespace Jdclzn\PayrollEngine\Contracts;

use Jdclzn\PayrollEngine\Data\CompanyProfile;
use Jdclzn\PayrollEngine\Data\EmployeeProfile;
use Jdclzn\PayrollEngine\Data\PayrollInput;
use Jdclzn\PayrollEngine\Data\PayrollLine;
use Money\Money;

interface WithholdingTaxCalculator
{
    public function calculateRegular(
        CompanyProfile $company,
        EmployeeProfile $employee,
        PayrollInput $input,
        Money $taxableIncomeAfterMandatory,
    ): PayrollLine;

    public function calculateBonusTax(
        CompanyProfile $company,
        EmployeeProfile $employee,
        PayrollInput $input,
        Money $projectedAnnualTaxableIncome,
    ): PayrollLine;

    public function annualTax(Money $annualTaxableIncome): Money;
}
