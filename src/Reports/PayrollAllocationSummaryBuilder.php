<?php

namespace Jdclzn\PayrollEngine\Reports;

use Jdclzn\PayrollEngine\Data\PayrollResult;
use Jdclzn\PayrollEngine\Support\MoneyHelper;

final class PayrollAllocationSummaryBuilder
{
    /**
     * @param  array<int, PayrollResult>  $results
     * @return array<int, array<string, mixed>>
     */
    public function build(array $results, string $dimension): array
    {
        $summary = [];
        $normalizedDimension = strtolower(trim($dimension));

        foreach ($results as $result) {
            $value = $result->employee->allocation->valueFor($normalizedDimension) ?? 'unassigned';

            if (! isset($summary[$value])) {
                $summary[$value] = [
                    'dimension' => $normalizedDimension,
                    'value' => $value,
                    'employee_count' => 0,
                    'gross_pay' => MoneyHelper::zero($result->grossPay),
                    'taxable_income' => MoneyHelper::zero($result->taxableIncome),
                    'net_pay' => MoneyHelper::zero($result->netPay),
                    'take_home_pay' => MoneyHelper::zero($result->takeHomePay),
                ];
            }

            $summary[$value]['employee_count']++;
            $summary[$value]['gross_pay'] = $summary[$value]['gross_pay']->add($result->grossPay);
            $summary[$value]['taxable_income'] = $summary[$value]['taxable_income']->add($result->taxableIncome);
            $summary[$value]['net_pay'] = $summary[$value]['net_pay']->add($result->netPay);
            $summary[$value]['take_home_pay'] = $summary[$value]['take_home_pay']->add($result->takeHomePay);
        }

        return array_values(array_map(
            static fn (array $row) => [
                'dimension' => $row['dimension'],
                'value' => $row['value'],
                'employee_count' => $row['employee_count'],
                'gross_pay' => MoneyHelper::toFloat($row['gross_pay']),
                'taxable_income' => MoneyHelper::toFloat($row['taxable_income']),
                'net_pay' => MoneyHelper::toFloat($row['net_pay']),
                'take_home_pay' => MoneyHelper::toFloat($row['take_home_pay']),
            ],
            $summary,
        ));
    }
}
