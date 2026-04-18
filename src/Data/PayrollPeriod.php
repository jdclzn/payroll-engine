<?php

namespace Jdclzn\PayrollEngine\Data;

use Carbon\CarbonImmutable;
use Jdclzn\PayrollEngine\Enums\PayrollRunType;

final readonly class PayrollPeriod
{
    public function __construct(
        public string $key,
        public CarbonImmutable $startDate,
        public CarbonImmutable $endDate,
        public CarbonImmutable $releaseDate,
        public string $runType = 'regular',
    ) {
    }

    public function normalizedRunType(): PayrollRunType
    {
        return PayrollRunType::parse($this->runType);
    }

    public function isOffCycleRun(): bool
    {
        return $this->normalizedRunType()->isOffCycle();
    }

    public function isFinalSettlementRun(): bool
    {
        return $this->normalizedRunType()->isFinalSettlement();
    }

    public function isSpecialRun(): bool
    {
        return $this->isOffCycleRun();
    }
}
