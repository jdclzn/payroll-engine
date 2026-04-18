<?php

namespace Jdclzn\PayrollEngine\Normalizers;

use Jdclzn\PayrollEngine\Data\Adjustment;
use Jdclzn\PayrollEngine\Data\Deduction;
use Jdclzn\PayrollEngine\Data\LoanDeduction;
use Jdclzn\PayrollEngine\Data\OvertimeEntry;
use Jdclzn\PayrollEngine\Data\PayrollInput;
use Jdclzn\PayrollEngine\Data\PayrollPeriod;
use Jdclzn\PayrollEngine\Support\AttributeReader;
use Jdclzn\PayrollEngine\Support\MoneyHelper;
use Money\Money;

final class PayrollInputNormalizer
{
    public function __construct(
        ?AttributeReader $reader = null,
        ?PayrollPeriodNormalizer $periodNormalizer = null,
    ) {
        $this->reader = $reader ?? new AttributeReader();
        $this->periodNormalizer = $periodNormalizer ?? new PayrollPeriodNormalizer($this->reader);
    }

    private readonly AttributeReader $reader;

    private readonly PayrollPeriodNormalizer $periodNormalizer;

    public function normalize(mixed $input, \Jdclzn\PayrollEngine\Data\CompanyProfile $company): PayrollInput
    {
        if ($input instanceof PayrollInput) {
            return $input;
        }

        $period = $this->reader->get($input, ['period']);

        if (! $period instanceof PayrollPeriod) {
            $period = $this->periodNormalizer->normalize($period ?? $input, $company);
        }

        return new PayrollInput(
            period: $period,
            overtimeEntries: $this->normalizeOvertimeEntries($this->reader->get($input, ['overtime', 'overtime_entries', 'overtimeEntries'], [])),
            adjustments: $this->normalizeAdjustments($this->reader->get($input, ['adjustments', 'earnings_adjustments', 'earningsAdjustments'], [])),
            manualDeductions: $this->normalizeDeductions($this->reader->get($input, ['manual_deductions', 'manualDeductions', 'deductions'], [])),
            loanDeductions: $this->normalizeLoanDeductions($this->reader->get($input, ['loan_deductions', 'loanDeductions', 'loans'], [])),
            manualOvertimePay: $this->nullableMoney($this->reader->get($input, ['manual_overtime_pay', 'manualOvertimePay'])),
            leaveDeduction: MoneyHelper::fromNumeric($this->reader->get($input, ['leave_deduction', 'leaveDeduction'], 0)),
            absenceDeduction: MoneyHelper::fromNumeric($this->reader->get($input, ['absence_deduction', 'absenceDeduction'], 0)),
            lateDeduction: MoneyHelper::fromNumeric($this->reader->get($input, ['late_deduction', 'lateDeduction'], 0)),
            undertimeDeduction: MoneyHelper::fromNumeric($this->reader->get($input, ['undertime_deduction', 'undertimeDeduction'], 0)),
            bonus: MoneyHelper::fromNumeric($this->reader->get($input, ['bonus', 'bonus_amount', 'bonusAmount'], 0)),
            usedAnnualBonusShield: MoneyHelper::fromNumeric($this->reader->get($input, ['used_annual_bonus_shield', 'usedAnnualBonusShield'], 0)),
            pagIbigLoanAmortization: $this->nullableMoney($this->reader->get($input, ['pagibig_loan_amortization', 'pagIbigLoanAmortization', 'hdmf_loan_amortization', 'hdmfLoanAmortization'])),
            pagIbigDueThisRun: $this->nullableBool($this->reader->get($input, ['pagibig_due_this_run', 'pagIbigDueThisRun'])),
            projectedAnnualTaxableIncome: $this->nullableMoney($this->reader->get($input, ['projected_annual_taxable_income', 'projectedAnnualTaxableIncome'])),
        );
    }

    /**
     * @return array<int, OvertimeEntry>
     */
    private function normalizeOvertimeEntries(mixed $entries): array
    {
        if (! is_iterable($entries)) {
            return [];
        }

        $normalized = [];

        foreach ($entries as $entry) {
            if ($entry instanceof OvertimeEntry) {
                $normalized[] = $entry;
                continue;
            }

            $normalized[] = new OvertimeEntry(
                type: (string) $this->reader->get($entry, ['type'], 'regular'),
                hours: (float) $this->reader->get($entry, ['hours'], 0),
                multiplier: ($multiplier = $this->reader->get($entry, ['multiplier'])) !== null ? (float) $multiplier : null,
                taxable: (bool) $this->reader->get($entry, ['taxable'], true),
                nightDifferential: (bool) $this->reader->get($entry, ['night_differential', 'nightDifferential'], false),
                manualAmount: $this->nullableMoney($this->reader->get($entry, ['manual_amount', 'manualAmount'])),
            );
        }

        return $normalized;
    }

    /**
     * @return array<int, Adjustment>
     */
    private function normalizeAdjustments(mixed $entries): array
    {
        if (! is_iterable($entries)) {
            return [];
        }

        $normalized = [];

        foreach ($entries as $entry) {
            if ($entry instanceof Adjustment) {
                $normalized[] = $entry;
                continue;
            }

            $normalized[] = new Adjustment(
                label: (string) $this->reader->get($entry, ['label', 'name'], 'Adjustment'),
                amount: MoneyHelper::fromNumeric($this->reader->get($entry, ['amount'], 0)),
                taxable: (bool) $this->reader->get($entry, ['taxable'], true),
                separatePayout: (bool) $this->reader->get($entry, ['separate_payout', 'separatePayout'], false),
            );
        }

        return $normalized;
    }

    /**
     * @return array<int, Deduction>
     */
    private function normalizeDeductions(mixed $entries): array
    {
        if (! is_iterable($entries)) {
            return [];
        }

        $normalized = [];

        foreach ($entries as $entry) {
            if ($entry instanceof Deduction) {
                $normalized[] = $entry;
                continue;
            }

            $normalized[] = new Deduction(
                label: (string) $this->reader->get($entry, ['label', 'name'], 'Deduction'),
                amount: MoneyHelper::fromNumeric($this->reader->get($entry, ['amount'], 0)),
            );
        }

        return $normalized;
    }

    /**
     * @return array<int, LoanDeduction>
     */
    private function normalizeLoanDeductions(mixed $entries): array
    {
        if (! is_iterable($entries)) {
            return [];
        }

        $normalized = [];

        foreach ($entries as $entry) {
            if ($entry instanceof LoanDeduction) {
                $normalized[] = $entry;
                continue;
            }

            $normalized[] = new LoanDeduction(
                label: (string) $this->reader->get($entry, ['label', 'name'], 'Loan'),
                amount: MoneyHelper::fromNumeric($this->reader->get($entry, ['amount'], 0)),
                loanReference: $this->reader->get($entry, ['loan_reference', 'loanReference', 'reference']),
            );
        }

        return $normalized;
    }

    private function nullableMoney(mixed $value): ?Money
    {
        if ($value === null || $value === '') {
            return null;
        }

        return MoneyHelper::fromNumeric($value);
    }

    private function nullableBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return (bool) $value;
    }
}
