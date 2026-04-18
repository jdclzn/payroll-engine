<?php

namespace Jdclzn\PayrollEngine\Data;

use Money\Money;

final readonly class PayrollResult
{
    /**
     * @param  array<int, PayrollLine>  $earnings
     * @param  array<int, PayrollLine>  $deductions
     * @param  array<int, PayrollLine>  $employeeContributions
     * @param  array<int, PayrollLine>  $employerContributions
     * @param  array<int, PayrollLine>  $separatePayouts
     */
    public function __construct(
        public CompanyProfile $company,
        public EmployeeProfile $employee,
        public PayrollPeriod $period,
        public RateSnapshot $rates,
        public array $earnings,
        public array $deductions,
        public array $employeeContributions,
        public array $employerContributions,
        public array $separatePayouts,
        public Money $grossPay,
        public Money $taxableIncome,
        public Money $netPay,
        public Money $takeHomePay,
        public Money $bonusTaxWithheld,
    ) {
    }
}
