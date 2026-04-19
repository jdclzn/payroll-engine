<?php

namespace QuillBytes\PayrollEngine\Calculators;

use QuillBytes\PayrollEngine\Contracts\OvertimeCalculator as OvertimeCalculatorContract;
use QuillBytes\PayrollEngine\Data\CompanyProfile;
use QuillBytes\PayrollEngine\Data\OvertimeEntry;
use QuillBytes\PayrollEngine\Data\PayrollInput;
use QuillBytes\PayrollEngine\Data\PayrollLine;
use QuillBytes\PayrollEngine\Data\RateSnapshot;
use QuillBytes\PayrollEngine\Support\MoneyHelper;
use QuillBytes\PayrollEngine\Support\TraceMetadata;
use Money\Money;

/**
 * Default overtime computation strategy for the payroll engine.
 *
 * Responsibility:
 * - translate overtime input entries into payroll earning lines
 * - support company-level manual overtime mode
 * - resolve overtime multipliers from the overtime entry or company policy
 * - add night differential premium when requested by the overtime entry
 *
 * Default behavior:
 * - when the company enables `manualOvertimePay` and the payroll input provides
 *   `manualOvertimePay`, the calculator returns a single manual OT earning line
 *   and skips per-entry computation
 * - otherwise each {@see OvertimeEntry} is evaluated independently using the
 *   employee hourly rate from {@see RateSnapshot}
 * - an entry may override the multiplier or even the full amount through
 *   `manualAmount`
 *
 * Supported default overtime types:
 * - `regular`
 * - `rest_day`
 * - `special_holiday`
 * - `regular_holiday`
 * - `rest_day_regular_holiday`
 * - `rest_day_holiday`
 * - `night_differential`
 *
 * Use case:
 * - use this strategy for clients whose overtime rules can be expressed with
 *   standard hourly-rate multipliers and optional night differential add-ons
 * - replace this strategy when a client uses different OT business rules such
 *   as bracketed OT plans, union/CBA tables, role-based premiums, shift bands,
 *   or approval-driven overtime valuation
 *
 * Custom strategy example:
 * - implement {@see OvertimeCalculatorContract}
 * - register the custom class or instance under
 *   `payroll-engine.strategies.clients.<client_code>.overtime`
 * - the engine will keep the rest of the payroll workflow unchanged while
 *   swapping only the overtime computation for that client
 */
final class OvertimeCalculator implements OvertimeCalculatorContract
{
    /**
     * Converts overtime input into payroll earning lines.
     *
     * Resolution order:
     * - if company-level manual overtime mode is active and a manual overtime
     *   amount is supplied in the payroll input, return one manual OT line
     * - otherwise iterate each overtime entry and resolve its amount from:
     *   1. the entry manual amount, when present
     *   2. the computed hourly-rate formula from {@see computeAmount()}
     * - zero-value overtime entries are ignored so they do not pollute the
     *   final payroll result
     *
     * The generated payroll lines always use `earning` as the line type and
     * preserve entry-specific metadata such as hours and multiplier.
     *
     * @return array<int, PayrollLine>
     */
    public function calculate(CompanyProfile $company, PayrollInput $input, RateSnapshot $rates): array
    {
        if ($company->manualOvertimePay && $input->manualOvertimePay !== null) {
            return [
                new PayrollLine(
                    type: 'earning',
                    label: 'Manual Overtime Pay',
                    amount: $input->manualOvertimePay,
                    taxable: true,
                    metadata: TraceMetadata::line(
                        source: 'payroll_input.manual_overtime_pay',
                        appliedRule: 'manual_overtime_pay',
                        formula: 'input manual overtime amount',
                        basis: [
                            'manual_overtime_pay' => $input->manualOvertimePay,
                        ],
                        extra: ['mode' => 'manual'],
                    ),
                ),
            ];
        }

        $lines = [];

        foreach ($input->overtimeEntries as $entry) {
            $amount = $entry->manualAmount ?? $this->computeAmount($company, $entry, $rates->hourlyRate);

            if ($amount->isZero()) {
                continue;
            }

            $lines[] = new PayrollLine(
                type: 'earning',
                label: $this->labelFor($entry),
                amount: $amount,
                taxable: $entry->taxable,
                metadata: TraceMetadata::line(
                    source: 'overtime_calculator',
                    appliedRule: strtolower($entry->type),
                    formula: $entry->type === 'night_differential'
                        ? 'hourly_rate * hours * night_differential_multiplier'
                        : 'hourly_rate * hours * overtime_multiplier',
                    basis: [
                        'hourly_rate' => $rates->hourlyRate,
                        'hours' => $entry->hours,
                        'multiplier' => $entry->multiplier ?? $this->defaultMultiplier($company, $entry->type),
                    ],
                    extra: [
                        'hours' => $entry->hours,
                        'multiplier' => $entry->multiplier ?? $this->defaultMultiplier($company, $entry->type),
                        'night_differential' => $entry->nightDifferential,
                    ],
                ),
            );
        }

        return $lines;
    }

    /**
     * Computes the overtime amount for a single overtime entry.
     *
     * Formula behavior:
     * - `night_differential` entries are computed as:
     *   `hourlyRate * hours * nightDifferentialPremium`
     * - all other entries are computed as:
     *   `hourlyRate * hours * resolvedMultiplier`
     * - when `nightDifferential` is enabled on a non-night-differential entry,
     *   an additional night premium is added on top of the overtime amount
     *
     * Multiplier resolution:
     * - use the overtime entry multiplier when explicitly provided
     * - otherwise fall back to the company default multiplier for the entry type
     */
    private function computeAmount(CompanyProfile $company, OvertimeEntry $entry, Money $hourlyRate): Money
    {
        if ($entry->type === 'night_differential') {
            return MoneyHelper::multiply(
                MoneyHelper::multiply($hourlyRate, $entry->hours),
                $entry->multiplier ?? $company->nightShiftDifferentialPremium
            );
        }

        $amount = MoneyHelper::multiply(
            MoneyHelper::multiply($hourlyRate, $entry->hours),
            $entry->multiplier ?? $this->defaultMultiplier($company, $entry->type)
        );

        if ($entry->nightDifferential) {
            $amount = $amount->add(
                MoneyHelper::multiply(
                    MoneyHelper::multiply($hourlyRate, $entry->hours),
                    $company->nightShiftDifferentialPremium
                )
            );
        }

        return $amount;
    }

    /**
     * Returns the company default overtime multiplier for a given overtime type.
     *
     * The company premium settings are stored as decimal multipliers such as
     * `1.25` for 125% or `3.38` for rest-day holiday overtime.
     */
    private function defaultMultiplier(CompanyProfile $company, string $type): float
    {
        return match (strtolower($type)) {
            'rest_day', 'special_holiday' => $company->restDayOtPremium,
            'regular_holiday', 'holiday' => $company->holidayOtPremium,
            'rest_day_regular_holiday', 'rest_day_holiday' => $company->restDayHolidayOtPremium,
            default => $company->workDayOtPremium,
        };
    }

    /**
     * Returns the human-readable payroll line label for an overtime entry.
     *
     * This keeps the resulting payroll register and payslip readable without
     * leaking the raw internal overtime type values.
     */
    private function labelFor(OvertimeEntry $entry): string
    {
        return match (strtolower($entry->type)) {
            'rest_day' => 'Rest Day Overtime',
            'special_holiday' => 'Special Holiday Overtime',
            'regular_holiday' => 'Regular Holiday Overtime',
            'rest_day_regular_holiday' => 'Rest Day + Regular Holiday Overtime',
            'night_differential' => 'Night Differential',
            default => 'Overtime Pay',
        };
    }
}
