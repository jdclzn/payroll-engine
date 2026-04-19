<?php

namespace QuillBytes\PayrollEngine\Data;

use Money\Money;

final readonly class PayrollResult
{
    /**
     * @param  array<int, PayrollLine>  $earnings
     * @param  array<int, PayrollLine>  $deductions
     * @param  array<int, PayrollLine>  $employeeContributions
     * @param  array<int, PayrollLine>  $employerContributions
     * @param  array<int, PayrollLine>  $separatePayouts
     * @param  array<int, PayrollIssue>  $issues
     * @param  array<string, mixed>  $audit
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
        public array $issues = [],
        public array $audit = [],
    ) {}

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function with(array $overrides = []): self
    {
        return new self(
            company: $overrides['company'] ?? $this->company,
            employee: $overrides['employee'] ?? $this->employee,
            period: $overrides['period'] ?? $this->period,
            rates: $overrides['rates'] ?? $this->rates,
            earnings: $overrides['earnings'] ?? $this->earnings,
            deductions: $overrides['deductions'] ?? $this->deductions,
            employeeContributions: $overrides['employeeContributions'] ?? $this->employeeContributions,
            employerContributions: $overrides['employerContributions'] ?? $this->employerContributions,
            separatePayouts: $overrides['separatePayouts'] ?? $this->separatePayouts,
            grossPay: $overrides['grossPay'] ?? $this->grossPay,
            taxableIncome: $overrides['taxableIncome'] ?? $this->taxableIncome,
            netPay: $overrides['netPay'] ?? $this->netPay,
            takeHomePay: $overrides['takeHomePay'] ?? $this->takeHomePay,
            bonusTaxWithheld: $overrides['bonusTaxWithheld'] ?? $this->bonusTaxWithheld,
            issues: $overrides['issues'] ?? $this->issues,
            audit: $overrides['audit'] ?? $this->audit,
        );
    }
}
