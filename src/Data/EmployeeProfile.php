<?php

namespace Jdclzn\PayrollEngine\Data;

use Money\Money;

final readonly class EmployeeProfile
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $employeeNumber,
        public string $fullName,
        public ?string $email,
        public ?string $position,
        public EmploymentProfile $employment,
        public CompensationProfile $compensation,
        public StatutoryProfile $statutory,
        public PayrollDetails $payrollDetails,
        public Money $bonusTaxShieldAmount,
        public array $metadata = [],
    ) {
    }

    public function isActiveDuring(PayrollPeriod $period): bool
    {
        return $this->employment->isActiveDuring($period);
    }
}
