<?php

namespace Jdclzn\PayrollEngine\Data;

use Jdclzn\PayrollEngine\Support\MoneyHelper;
use Money\Money;

final readonly class PayrollInput
{
    public Money $leaveDeduction;

    public Money $absenceDeduction;

    public Money $lateDeduction;

    public Money $undertimeDeduction;

    public Money $bonus;

    public Money $usedAnnualBonusShield;

    /**
     * @param  array<int, OvertimeEntry>  $overtimeEntries
     * @param  array<int, Adjustment>  $adjustments
     * @param  array<int, Deduction>  $manualDeductions
     * @param  array<int, LoanDeduction>  $loanDeductions
     */
    public function __construct(
        public PayrollPeriod $period,
        public array $overtimeEntries = [],
        public array $adjustments = [],
        public array $manualDeductions = [],
        public array $loanDeductions = [],
        public ?Money $manualOvertimePay = null,
        ?Money $leaveDeduction = null,
        ?Money $absenceDeduction = null,
        ?Money $lateDeduction = null,
        ?Money $undertimeDeduction = null,
        ?Money $bonus = null,
        ?Money $usedAnnualBonusShield = null,
        public ?Money $pagIbigLoanAmortization = null,
        public ?bool $pagIbigDueThisRun = null,
        public ?Money $projectedAnnualTaxableIncome = null,
    ) {
        $this->leaveDeduction = $leaveDeduction ?? MoneyHelper::zero();
        $this->absenceDeduction = $absenceDeduction ?? MoneyHelper::zero();
        $this->lateDeduction = $lateDeduction ?? MoneyHelper::zero();
        $this->undertimeDeduction = $undertimeDeduction ?? MoneyHelper::zero();
        $this->bonus = $bonus ?? MoneyHelper::zero();
        $this->usedAnnualBonusShield = $usedAnnualBonusShield ?? MoneyHelper::zero();
    }
}
