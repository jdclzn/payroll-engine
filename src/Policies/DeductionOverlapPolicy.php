<?php

namespace QuillBytes\PayrollEngine\Policies;

use QuillBytes\PayrollEngine\Contracts\PayrollEdgeCasePolicy;
use QuillBytes\PayrollEngine\Data\CompanyProfile;
use QuillBytes\PayrollEngine\Data\Deduction;
use QuillBytes\PayrollEngine\Data\EmployeeProfile;
use QuillBytes\PayrollEngine\Data\LoanDeduction;
use QuillBytes\PayrollEngine\Data\PayrollInput;
use QuillBytes\PayrollEngine\Data\PayrollResult;
use QuillBytes\PayrollEngine\Exceptions\InvalidPayrollData;
use QuillBytes\PayrollEngine\Support\EdgeCasePolicyConfig;

final readonly class DeductionOverlapPolicy implements PayrollEdgeCasePolicy
{
    public function __construct(
        private EdgeCasePolicyConfig $config,
    ) {}

    public function prepare(
        CompanyProfile $company,
        EmployeeProfile $employee,
        PayrollInput $input,
    ): PayrollInput {
        $mode = strtolower((string) $this->config->value($company, $employee, $input, 'overlapping_deductions', 'allow'));

        if ($mode === 'allow') {
            return $input;
        }

        $hasOverlap = $this->hasManualDeductionOverlap($input->manualDeductions) || $this->hasLoanDeductionOverlap($input->loanDeductions);

        if (! $hasOverlap) {
            return $input;
        }

        if ($mode === 'error') {
            throw new InvalidPayrollData('Overlapping deductions were detected and the current policy is configured to reject them.');
        }

        if ($mode !== 'merge') {
            return $input;
        }

        return $input->with([
            'manualDeductions' => $this->mergeManualDeductions($input->manualDeductions),
            'loanDeductions' => $this->mergeLoanDeductions($input->loanDeductions),
        ]);
    }

    public function finalize(
        CompanyProfile $company,
        EmployeeProfile $employee,
        PayrollInput $input,
        PayrollResult $result,
    ): PayrollResult {
        return $result;
    }

    /**
     * @param  array<int, Deduction>  $deductions
     */
    private function hasManualDeductionOverlap(array $deductions): bool
    {
        $keys = [];

        foreach ($deductions as $deduction) {
            $key = strtolower(trim($deduction->label));

            if (isset($keys[$key])) {
                return true;
            }

            $keys[$key] = true;
        }

        return false;
    }

    /**
     * @param  array<int, LoanDeduction>  $deductions
     */
    private function hasLoanDeductionOverlap(array $deductions): bool
    {
        $keys = [];

        foreach ($deductions as $deduction) {
            $key = strtolower(trim((string) ($deduction->loanReference ?: $deduction->label)));

            if (isset($keys[$key])) {
                return true;
            }

            $keys[$key] = true;
        }

        return false;
    }

    /**
     * @param  array<int, Deduction>  $deductions
     * @return array<int, Deduction>
     */
    private function mergeManualDeductions(array $deductions): array
    {
        $merged = [];

        foreach ($deductions as $deduction) {
            $key = strtolower(trim($deduction->label));

            if (! isset($merged[$key])) {
                $merged[$key] = $deduction;

                continue;
            }

            $merged[$key] = new Deduction(
                label: $merged[$key]->label,
                amount: $merged[$key]->amount->add($deduction->amount),
            );
        }

        return array_values($merged);
    }

    /**
     * @param  array<int, LoanDeduction>  $deductions
     * @return array<int, LoanDeduction>
     */
    private function mergeLoanDeductions(array $deductions): array
    {
        $merged = [];

        foreach ($deductions as $deduction) {
            $key = strtolower(trim((string) ($deduction->loanReference ?: $deduction->label)));

            if (! isset($merged[$key])) {
                $merged[$key] = $deduction;

                continue;
            }

            $merged[$key] = new LoanDeduction(
                label: $merged[$key]->label,
                amount: $merged[$key]->amount->add($deduction->amount),
                loanReference: $merged[$key]->loanReference ?: $deduction->loanReference,
            );
        }

        return array_values($merged);
    }
}
