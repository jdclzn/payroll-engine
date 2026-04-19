<?php

namespace QuillBytes\PayrollEngine\Data;

final readonly class AllocationProfile
{
    /**
     * @param  array<string, string>  $dimensions
     */
    public function __construct(
        public ?string $projectCode = null,
        public ?string $projectName = null,
        public ?string $costCenter = null,
        public ?string $branch = null,
        public ?string $department = null,
        public ?string $vessel = null,
        public array $dimensions = [],
    ) {}

    public function valueFor(string $dimension): ?string
    {
        $normalized = strtolower(trim($dimension));

        return match ($normalized) {
            'project', 'project_code', 'projectcode' => $this->projectCode,
            'project_name', 'projectname' => $this->projectName,
            'cost_center', 'costcenter', 'cost_centre', 'costcentre' => $this->costCenter,
            'branch' => $this->branch,
            'department' => $this->department,
            'vessel', 'vessel_name', 'vesselname' => $this->vessel,
            default => $this->dimensions[$normalized] ?? null,
        };
    }
}
