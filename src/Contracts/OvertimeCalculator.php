<?php

namespace QuillBytes\PayrollEngine\Contracts;

use QuillBytes\PayrollEngine\Data\CompanyProfile;
use QuillBytes\PayrollEngine\Data\PayrollInput;
use QuillBytes\PayrollEngine\Data\PayrollLine;
use QuillBytes\PayrollEngine\Data\RateSnapshot;

interface OvertimeCalculator
{
    /**
     * @return array<int, PayrollLine>
     */
    public function calculate(CompanyProfile $company, PayrollInput $input, RateSnapshot $rates): array;
}
