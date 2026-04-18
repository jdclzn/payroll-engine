<?php

namespace Jdclzn\PayrollEngine\Data;

use Jdclzn\PayrollEngine\Support\MoneyHelper;
use Money\Money;

final readonly class CompensationProfile
{
    public Money $representationAllowance;

    public Money $otherAllowances;

    public function __construct(
        public Money $monthlyBasicSalary,
        public ?Money $fixedDailyRate = null,
        public ?Money $fixedHourlyRate = null,
        public ?Money $projectedAnnualTaxableIncome = null,
        ?Money $representationAllowance = null,
        ?Money $otherAllowances = null,
    ) {
        $this->representationAllowance = $representationAllowance ?? MoneyHelper::zero();
        $this->otherAllowances = $otherAllowances ?? MoneyHelper::zero();
    }
}
