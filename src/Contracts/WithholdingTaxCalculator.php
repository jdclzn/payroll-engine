<?php

namespace QuillBytes\PayrollEngine\Contracts;

use Money\Money;
use QuillBytes\PayrollEngine\Data\CompanyProfile;
use QuillBytes\PayrollEngine\Data\EmployeeProfile;
use QuillBytes\PayrollEngine\Data\PayrollInput;
use QuillBytes\PayrollEngine\Data\PayrollLine;

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
