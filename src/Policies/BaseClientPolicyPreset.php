<?php

namespace QuillBytes\PayrollEngine\Policies;

use QuillBytes\PayrollEngine\Contracts\ClientPolicyPreset;

final class BaseClientPolicyPreset implements ClientPolicyPreset
{
    public function supports(string $clientCode): bool
    {
        return $clientCode === '' || $clientCode === 'base';
    }

    public function defaults(): array
    {
        return [
            'client_code' => 'base',
            'eemr_factor' => 313,
            'hours_per_day' => 8,
            'work_days_per_year' => 313,
            'frequency' => 'semi_monthly',
            'release_lead_days' => 0,
            'manual_overtime_pay' => false,
            'fixed_per_day_rate' => false,
            'separate_allowance_payout' => false,
            'external_leave_management' => false,
            'split_monthly_statutory_across_periods' => true,
            'tax_strategy' => 'current_period_annualized',
            'annual_bonus_tax_shield' => 90000,
        ];
    }
}
