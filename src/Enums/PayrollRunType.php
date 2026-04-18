<?php

namespace Jdclzn\PayrollEngine\Enums;

enum PayrollRunType: string
{
    case Regular = 'regular';
    case Special = 'special';
    case Adjustment = 'adjustment';
    case Correction = 'correction';
    case Emergency = 'emergency';
    case BonusRelease = 'bonus_release';
    case RetroPay = 'retro_pay';
    case FinalPay = 'final_pay';
    case Resignation = 'resignation';
    case Termination = 'termination';
    case Retirement = 'retirement';

    public static function parse(string $value): self
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            '', 'regular' => self::Regular,
            'special', 'off_cycle', 'off-cycle' => self::Special,
            'adjustment', 'adjustment_payroll' => self::Adjustment,
            'correction', 'correction_run' => self::Correction,
            'emergency', 'emergency_payroll' => self::Emergency,
            'bonus_release', 'bonus-release', 'bonus release' => self::BonusRelease,
            'retro_pay', 'retro-pay', 'retro pay', 'retropay' => self::RetroPay,
            'final_pay', 'final-pay', 'final pay', 'separation_pay', 'separation-pay', 'separation pay' => self::FinalPay,
            'resignation', 'resigned' => self::Resignation,
            'termination', 'terminated' => self::Termination,
            'retirement', 'retired' => self::Retirement,
            default => self::Special,
        };
    }

    public function isOffCycle(): bool
    {
        return $this !== self::Regular;
    }

    public function isFinalSettlement(): bool
    {
        return match ($this) {
            self::FinalPay,
            self::Resignation,
            self::Termination,
            self::Retirement => true,
            default => false,
        };
    }

    public function usesScheduledBasicPay(): bool
    {
        return $this === self::Regular || $this->isFinalSettlement();
    }

    public function usesRegularAllowances(): bool
    {
        return $this === self::Regular;
    }

    public function usesMandatoryContributions(): bool
    {
        return $this === self::Regular;
    }

    public function usesRegularWithholding(): bool
    {
        return $this === self::Regular || $this->isFinalSettlement();
    }
}
