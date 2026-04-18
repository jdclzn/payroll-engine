<?php

namespace Jdclzn\PayrollEngine\Policies;

use Jdclzn\PayrollEngine\Contracts\PayrollEdgeCasePolicy;
use Jdclzn\PayrollEngine\Data\CompanyProfile;
use Jdclzn\PayrollEngine\Data\EmployeeProfile;
use Jdclzn\PayrollEngine\Data\PayrollInput;
use Jdclzn\PayrollEngine\Data\PayrollResult;
use Jdclzn\PayrollEngine\Exceptions\InvalidPayrollData;
use Jdclzn\PayrollEngine\Support\EdgeCasePolicyConfig;

final readonly class RuleConflictPolicy implements PayrollEdgeCasePolicy
{
    public function __construct(
        private EdgeCasePolicyConfig $config,
    ) {
    }

    public function prepare(
        CompanyProfile $company,
        EmployeeProfile $employee,
        PayrollInput $input,
    ): PayrollInput {
        $negativeNetPayMode = strtolower((string) $this->config->value($company, $employee, $input, 'negative_net_pay', 'allow'));
        $minimumTakeHomePay = (float) $this->config->value($company, $employee, $input, 'minimum_take_home_pay', 0);
        $partialPayoutLimit = $this->config->value($company, $employee, $input, 'partial_payout_limit');

        if ($negativeNetPayMode === 'allow' && ($minimumTakeHomePay > 0 || ($partialPayoutLimit !== null && $partialPayoutLimit !== ''))) {
            throw new InvalidPayrollData('Edge case policies conflict: negative net pay cannot be allowed when minimum take-home pay or partial payout rules are also configured.');
        }

        if ($partialPayoutLimit !== null && $partialPayoutLimit !== '' && (float) $partialPayoutLimit < 0) {
            throw new InvalidPayrollData('Partial payout limit cannot be negative.');
        }

        return $input;
    }

    public function finalize(
        CompanyProfile $company,
        EmployeeProfile $employee,
        PayrollInput $input,
        PayrollResult $result,
    ): PayrollResult {
        return $result;
    }
}
