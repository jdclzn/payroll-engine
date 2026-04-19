<?php

namespace QuillBytes\PayrollEngine\Calculators;

use Money\Money;
use QuillBytes\PayrollEngine\Contracts\RateCalculator as RateCalculatorContract;
use QuillBytes\PayrollEngine\Data\CompanyProfile;
use QuillBytes\PayrollEngine\Data\EmployeeProfile;
use QuillBytes\PayrollEngine\Data\PayrollPeriod;
use QuillBytes\PayrollEngine\Data\RateSnapshot;
use QuillBytes\PayrollEngine\Support\MoneyHelper;

/**
 * Default rate-calculation strategy used by the payroll engine.
 *
 * Responsibility:
 * - derive the scheduled basic pay for the current payroll period
 * - derive the employee daily rate
 * - derive the employee hourly rate
 * - return a normalized {@see RateSnapshot} for downstream payroll steps
 *
 * Default formula behavior:
 * - `scheduledBasicPay`:
 *   `monthlyBasicSalary * 12 / periodsPerYear`
 * - `dailyRate`:
 *   `monthlyBasicSalary * 12 / eemrFactor`
 * - `hourlyRate`:
 *   `dailyRate / hoursPerDay`
 *
 * Special-run behavior:
 * - for special runs such as bonus-only payroll, scheduled basic pay is set to
 *   zero so the engine does not inject regular basic salary into the result
 *
 * Use case:
 * - use this class for the package default Philippine payroll flow where the
 *   company follows EEMR-based daily-rate computation and standard hourly-rate
 *   derivation from the company work schedule
 * - replace this strategy when a client uses different business logic such as:
 *   grade-step tables, location-based rates, CBA rules, project-based rates,
 *   or a daily/hourly rate that does not come from the PH annualized divisor
 *
 * Custom strategy example:
 * - implement {@see RateCalculatorContract}
 * - register the class under `payroll-engine.strategies.clients.<client_code>.rate`
 * - the core engine will keep using the same payroll workflow while swapping
 *   only the rate computation for that client
 */
final class RateCalculator implements RateCalculatorContract
{
    /**
     * Builds the rate snapshot used by the payroll workflow.
     *
     * Inputs considered:
     * - company payroll schedule and periods-per-year
     * - company EEMR factor
     * - company hours-per-day setting
     * - employee monthly basic salary
     * - employee fixed daily/hourly overrides when present
     *
     * Resolution order:
     * - monthly basic salary always comes from the employee compensation profile
     * - scheduled basic pay becomes zero for special runs, otherwise it is
     *   prorated from the monthly salary using the company payroll frequency
     * - daily rate uses the employee fixed daily rate only when the company
     *   enables `fixedPerDayRate`; otherwise it always uses the computed
     *   annualized divisor formula
     * - hourly rate falls back to `dailyRate / hoursPerDay` when no employee
     *   fixed hourly rate is supplied
     *
     * Returned values are normalized as Money objects inside {@see RateSnapshot}
     * so the rest of the payroll pipeline can continue without float drift.
     */
    public function calculate(CompanyProfile $company, EmployeeProfile $employee, PayrollPeriod $period): RateSnapshot
    {
        $monthlyBasic = $employee->compensation->monthlyBasicSalary;
        $normalizedRunType = $period->normalizedRunType();
        $regularScheduledBasicPay = MoneyHelper::multiply($monthlyBasic, 12 / $company->periodsPerYear());
        $scheduledBasicPay = ! $normalizedRunType->usesScheduledBasicPay()
            ? MoneyHelper::zero()
            : ($normalizedRunType->isFinalSettlement()
                ? $this->proratedFinalSettlementBasicPay($employee, $period, $regularScheduledBasicPay)
                : $regularScheduledBasicPay);

        $computedDailyRate = MoneyHelper::divide(MoneyHelper::multiply($monthlyBasic, 12), max($company->eemrFactor, 1));
        $fixedPerDayApplied = $company->fixedPerDayRate && $employee->compensation->fixedDailyRate !== null;
        $dailyRate = $fixedPerDayApplied
            ? $employee->compensation->fixedDailyRate
            : $computedDailyRate;

        $hourlyRate = $employee->compensation->fixedHourlyRate
            ?? MoneyHelper::divide($dailyRate, max($company->schedule->hoursPerDay, 1));

        return new RateSnapshot(
            monthlyBasicSalary: $monthlyBasic,
            scheduledBasicPay: $scheduledBasicPay,
            dailyRate: $dailyRate,
            hourlyRate: $hourlyRate,
            fixedPerDayApplied: $fixedPerDayApplied,
        );
    }

    private function proratedFinalSettlementBasicPay(
        EmployeeProfile $employee,
        PayrollPeriod $period,
        Money $regularScheduledBasicPay,
    ): Money {
        $separationDate = $employee->employment->dateResigned;

        if ($separationDate === null) {
            return $regularScheduledBasicPay;
        }

        if ($separationDate->lessThan($period->startDate)) {
            return MoneyHelper::zero($regularScheduledBasicPay);
        }

        $coveredDays = max(1, $period->startDate->diffInDays($period->endDate) + 1);
        $payableEnd = $separationDate->lessThan($period->endDate) ? $separationDate : $period->endDate;
        $payableDays = max(0, $period->startDate->diffInDays($payableEnd) + 1);

        return MoneyHelper::multiply($regularScheduledBasicPay, $payableDays / $coveredDays);
    }
}
