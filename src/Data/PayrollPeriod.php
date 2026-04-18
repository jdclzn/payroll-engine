<?php

namespace Jdclzn\PayrollEngine\Data;

use Carbon\CarbonImmutable;

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

    public function isSpecialRun(): bool
    {
        return strtolower($this->runType) !== 'regular';
    }
}
