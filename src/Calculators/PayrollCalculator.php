<?php

namespace Jdclzn\PayrollEngine\Calculators;

use Jdclzn\PayrollEngine\Contracts\OvertimeCalculator as OvertimeCalculatorContract;
use Jdclzn\PayrollEngine\Contracts\PagIbigContributionCalculator as PagIbigContributionCalculatorContract;
use Jdclzn\PayrollEngine\Contracts\PayrollWorkflow;
use Jdclzn\PayrollEngine\Contracts\RateCalculator as RateCalculatorContract;
use Jdclzn\PayrollEngine\Contracts\WithholdingTaxCalculator as WithholdingTaxCalculatorContract;
use Jdclzn\PayrollEngine\Data\CompanyProfile;
use Jdclzn\PayrollEngine\Data\EmployeeProfile;
use Jdclzn\PayrollEngine\Data\PayrollInput;
use Jdclzn\PayrollEngine\Data\PayrollLine;
use Jdclzn\PayrollEngine\Data\PayrollResult;
use Jdclzn\PayrollEngine\Enums\PayrollFrequency;
use Jdclzn\PayrollEngine\Support\MoneyHelper;
use Money\Money;

final readonly class PayrollCalculator implements PayrollWorkflow
{
    public function __construct(
        private RateCalculatorContract $rateCalculator,
        private OvertimeCalculatorContract $overtimeCalculator,
        private SssContributionCalculator $sssCalculator,
        private PhilHealthContributionCalculator $philHealthCalculator,
        private PagIbigContributionCalculatorContract $pagIbigCalculator,
        private WithholdingTaxCalculatorContract $withholdingTaxCalculator,
    ) {
    }

    public function calculate(CompanyProfile $company, EmployeeProfile $employee, PayrollInput $input): PayrollResult
    {
        $rates = $this->rateCalculator->calculate($company, $employee, $input->period);
        $earnings = [];
        $separatePayouts = [];

        if (! $rates->scheduledBasicPay->isZero()) {
            $earnings[] = new PayrollLine('earning', 'Basic Pay', $rates->scheduledBasicPay, true);
        }

        if (! $input->period->isSpecialRun()) {
            $this->appendAllowanceLine($company, $earnings, $separatePayouts, 'Representation Allowance', $employee->compensation->representationAllowance);
            $this->appendAllowanceLine($company, $earnings, $separatePayouts, 'Allowance', $employee->compensation->otherAllowances);
        }

        foreach ($input->adjustments as $adjustment) {
            $line = new PayrollLine(
                type: $adjustment->separatePayout ? 'separate_payout' : 'earning',
                label: $adjustment->label,
                amount: $adjustment->amount,
                taxable: $adjustment->taxable,
            );

            if ($line->type === 'separate_payout') {
                $separatePayouts[] = $line;
                continue;
            }

            $earnings[] = $line;
        }

        foreach ($this->overtimeCalculator->calculate($company, $input, $rates) as $line) {
            $earnings[] = $line;
        }

        if (! $input->bonus->isZero()) {
            $earnings[] = new PayrollLine(
                type: 'earning',
                label: 'Bonus',
                amount: $input->bonus,
                taxable: true,
            );
        }

        $employeeContributions = [];
        $employerContributions = [];

        if (! $input->period->isSpecialRun()) {
            $periodDivisor = $this->statutoryPeriodDivisor($company);
            $sss = $this->sssCalculator->calculate($employee->compensation->monthlyBasicSalary, $employee->statutory->manualSssContribution, $periodDivisor);
            $philHealth = $this->philHealthCalculator->calculate($employee->compensation->monthlyBasicSalary, $employee->statutory->manualPhilHealthContribution, $periodDivisor);
            $pagIbig = $this->pagIbigCalculator->calculate($company, $employee, $input, $periodDivisor);

            $employeeContributions = [$sss['employee'], $philHealth['employee'], $pagIbig->employee];
            $employerContributions = [$sss['employer'], $philHealth['employer'], $pagIbig->employer];
        }

        $deductions = array_map(
            static fn ($deduction) => new PayrollLine('deduction', $deduction->label, $deduction->amount),
            [
                ...$input->loanDeductions,
                ...$input->manualDeductions,
            ]
        );

        if (isset($pagIbig) && $pagIbig->separateDeductions !== []) {
            foreach ($pagIbig->separateDeductions as $line) {
                $deductions[] = $line;
            }
        }

        foreach ([
            ['Leave Deduction', $input->leaveDeduction],
            ['Absence Deduction', $input->absenceDeduction],
            ['Late Deduction', $input->lateDeduction],
            ['Undertime Deduction', $input->undertimeDeduction],
        ] as [$label, $amount]) {
            if (! $amount->isZero()) {
                $deductions[] = new PayrollLine('deduction', $label, $amount);
            }
        }

        $taxableIncome = MoneyHelper::sum(array_map(
            static fn (PayrollLine $line) => $line->taxable ? $line->amount : MoneyHelper::zero(),
            $earnings
        ))->subtract(MoneyHelper::sum(array_map(
            static fn (PayrollLine $line) => $line->amount,
            $employeeContributions
        )));
        $taxableIncome = MoneyHelper::max($taxableIncome, MoneyHelper::zero());

        $withholdingTax = $this->withholdingTaxCalculator->calculateRegular($company, $employee, $input, $taxableIncome);
        $bonusTax = $this->withholdingTaxCalculator->calculateBonusTax(
            $company,
            $employee,
            $input,
            $input->projectedAnnualTaxableIncome
                ?? $employee->compensation->projectedAnnualTaxableIncome
                ?? MoneyHelper::multiply($taxableIncome, $company->periodsPerYear())
        );

        if (! $withholdingTax->amount->isZero()) {
            $deductions[] = $withholdingTax;
        }

        if (! $bonusTax->amount->isZero()) {
            $deductions[] = $bonusTax;
        }

        $grossPay = MoneyHelper::sum(array_map(static fn (PayrollLine $line) => $line->amount, $earnings));
        $totalEmployeeContributions = MoneyHelper::sum(array_map(static fn (PayrollLine $line) => $line->amount, $employeeContributions));
        $totalDeductions = MoneyHelper::sum(array_map(static fn (PayrollLine $line) => $line->amount, $deductions));
        $separatePayoutTotal = MoneyHelper::sum(array_map(static fn (PayrollLine $line) => $line->amount, $separatePayouts));
        $netPay = $grossPay->subtract($totalEmployeeContributions)->subtract($totalDeductions);
        $takeHomePay = $netPay->add($separatePayoutTotal);

        return new PayrollResult(
            company: $company,
            employee: $employee,
            period: $input->period,
            rates: $rates,
            earnings: $earnings,
            deductions: $deductions,
            employeeContributions: $employeeContributions,
            employerContributions: $employerContributions,
            separatePayouts: $separatePayouts,
            grossPay: $grossPay,
            taxableIncome: $taxableIncome,
            netPay: $netPay,
            takeHomePay: $takeHomePay,
            bonusTaxWithheld: $bonusTax->amount,
        );
    }

    /**
     * @param  array<int, PayrollLine>  $earnings
     * @param  array<int, PayrollLine>  $separatePayouts
     */
    private function appendAllowanceLine(CompanyProfile $company, array &$earnings, array &$separatePayouts, string $label, Money $amount): void
    {
        if ($amount->isZero()) {
            return;
        }

        $line = new PayrollLine(
            type: $company->separateAllowancePayout ? 'separate_payout' : 'earning',
            label: $label,
            amount: $amount,
            taxable: false,
        );

        if ($line->type === 'separate_payout') {
            $separatePayouts[] = $line;
            return;
        }

        $earnings[] = $line;
    }

    private function statutoryPeriodDivisor(CompanyProfile $company): int
    {
        if (! $company->splitMonthlyStatutoryAcrossPeriods) {
            return 1;
        }

        return match ($company->schedule->frequency) {
            PayrollFrequency::Monthly => 1,
            PayrollFrequency::SemiMonthly => 2,
            PayrollFrequency::Weekly => 4,
        };
    }
}
