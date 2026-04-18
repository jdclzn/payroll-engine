<?php

namespace Jdclzn\PayrollEngine\Policies;

use Jdclzn\PayrollEngine\Contracts\PayrollEdgeCasePolicy;
use Jdclzn\PayrollEngine\Data\CompanyProfile;
use Jdclzn\PayrollEngine\Data\EmployeeProfile;
use Jdclzn\PayrollEngine\Data\PayrollInput;
use Jdclzn\PayrollEngine\Data\PayrollResult;

final readonly class PayrollEdgeCasePolicyPipeline
{
    /**
     * @param  array<int, PayrollEdgeCasePolicy>  $policies
     */
    public function __construct(
        private array $policies,
    ) {
    }

    public function prepare(
        CompanyProfile $company,
        EmployeeProfile $employee,
        PayrollInput $input,
    ): PayrollInput {
        foreach ($this->policies as $policy) {
            $input = $policy->prepare($company, $employee, $input);
        }

        return $input;
    }

    public function finalize(
        CompanyProfile $company,
        EmployeeProfile $employee,
        PayrollInput $input,
        PayrollResult $result,
    ): PayrollResult {
        foreach ($this->policies as $policy) {
            $result = $policy->finalize($company, $employee, $input, $result);
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    public function policyNames(): array
    {
        return array_map(
            static fn (PayrollEdgeCasePolicy $policy) => $policy::class,
            $this->policies,
        );
    }
}
