<?php

namespace Jdclzn\PayrollEngine\Normalizers;

use Carbon\CarbonImmutable;
use Jdclzn\PayrollEngine\Data\CompanyProfile;
use Jdclzn\PayrollEngine\Data\PayrollPeriod;
use Jdclzn\PayrollEngine\Support\AttributeReader;

final readonly class PayrollPeriodNormalizer
{
    public function __construct(
        ?AttributeReader $reader = null,
    ) {
        $this->reader = $reader ?? new AttributeReader();
    }

    private AttributeReader $reader;

    public function normalize(mixed $period, CompanyProfile $company): PayrollPeriod
    {
        if ($period instanceof PayrollPeriod) {
            return $period;
        }

        $start = $this->asDate($this->reader->get($period, ['start_date', 'startDate', 'from']));
        $end = $this->asDate($this->reader->get($period, ['end_date', 'endDate', 'to']));
        $release = $this->asDate($this->reader->get($period, ['release_date', 'releaseDate']));

        if ($release === null && $end !== null) {
            $release = $end->subDays($company->schedule->releaseLeadDays);
        }

        return new PayrollPeriod(
            key: (string) $this->reader->get($period, ['key', 'period_key', 'periodKey'], ($start?->format('Ymd') ?? 'period').'-'.($end?->format('Ymd') ?? 'end')),
            startDate: $start ?? CarbonImmutable::now()->startOfMonth(),
            endDate: $end ?? CarbonImmutable::now()->endOfMonth(),
            releaseDate: $release ?? CarbonImmutable::now(),
            runType: (string) $this->reader->get($period, ['run_type', 'runType'], 'regular'),
        );
    }

    private function asDate(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse((string) $value);
    }
}
