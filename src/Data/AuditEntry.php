<?php

namespace Jdclzn\PayrollEngine\Data;

use Carbon\CarbonImmutable;

final readonly class AuditEntry
{
    public function __construct(
        public string $actor,
        public string $action,
        public CarbonImmutable $occurredAt,
        public ?string $notes = null,
    ) {
    }
}
