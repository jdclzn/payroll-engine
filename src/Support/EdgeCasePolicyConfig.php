<?php

namespace QuillBytes\PayrollEngine\Support;

use QuillBytes\PayrollEngine\Data\CompanyProfile;
use QuillBytes\PayrollEngine\Data\EmployeeProfile;
use QuillBytes\PayrollEngine\Data\PayrollInput;

final class EdgeCasePolicyConfig
{
    /**
     * @return array<string, mixed>
     */
    public function merged(CompanyProfile $company, EmployeeProfile $employee, PayrollInput $input): array
    {
        return array_replace_recursive(
            $this->extract($company->metadata),
            $this->extract($employee->metadata),
            $this->extract($input->metadata),
        );
    }

    public function value(
        CompanyProfile $company,
        EmployeeProfile $employee,
        PayrollInput $input,
        string $key,
        mixed $default = null,
    ): mixed {
        $config = $this->merged($company, $employee, $input);

        return $config[$key] ?? $default;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function extract(array $metadata): array
    {
        $config = $metadata['edge_case_policy'] ?? [];

        return is_array($config) ? $config : [];
    }
}
