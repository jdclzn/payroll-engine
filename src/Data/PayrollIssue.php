<?php

namespace QuillBytes\PayrollEngine\Data;

final readonly class PayrollIssue
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $code,
        public string $message,
        public string $severity = 'warning',
        public array $metadata = [],
    ) {}
}
