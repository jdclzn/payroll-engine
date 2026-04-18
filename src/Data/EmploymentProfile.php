<?php

namespace Jdclzn\PayrollEngine\Data;

use Carbon\CarbonImmutable;

final readonly class EmploymentProfile
{
    public function __construct(
        public ?CarbonImmutable $dateHired,
        public ?CarbonImmutable $dateRegularized,
        public ?CarbonImmutable $dateResigned,
        public ?string $reasonForResignation,
        public string $employmentStatus,
        public ?string $department,
    ) {
    }

    public function isActiveDuring(PayrollPeriod $period): bool
    {
        $status = strtolower(trim($this->employmentStatus));

        if ($this->dateHired !== null && $this->dateHired->greaterThan($period->endDate)) {
            return false;
        }

        if ($period->isFinalSettlementRun()) {
            return ! in_array($status, ['inactive', 'sabbatical', 'x', 's'], true);
        }

        if ($this->dateResigned !== null && $this->dateResigned->lessThan($period->startDate)) {
            return false;
        }

        return ! in_array($status, ['inactive', 'terminated', 'retired', 'resigned', 'sabbatical', 'x', 's'], true);
    }
}
