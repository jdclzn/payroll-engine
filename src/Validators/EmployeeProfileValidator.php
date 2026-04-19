<?php

namespace QuillBytes\PayrollEngine\Validators;

use QuillBytes\PayrollEngine\Data\EmployeeProfile;
use QuillBytes\PayrollEngine\Exceptions\InvalidPayrollData;
use QuillBytes\PayrollEngine\Support\MoneyHelper;

final class EmployeeProfileValidator
{
    public function validate(EmployeeProfile $employee): void
    {
        if (trim($employee->employeeNumber) === '' || $employee->employeeNumber === 'EMP-UNKNOWN') {
            throw new InvalidPayrollData('Employee number is required.');
        }

        if (trim($employee->fullName) === '' || $employee->fullName === 'Unknown Employee') {
            throw new InvalidPayrollData('Employee full name is required.');
        }

        if ($employee->employment->dateHired === null) {
            throw new InvalidPayrollData('Employee date hired is required.');
        }

        if ($employee->employment->dateRegularized !== null && $employee->employment->dateRegularized->lessThan($employee->employment->dateHired)) {
            throw new InvalidPayrollData('Employee date regularized cannot be earlier than date hired.');
        }

        if ($employee->employment->dateResigned !== null && $employee->employment->dateResigned->lessThan($employee->employment->dateHired)) {
            throw new InvalidPayrollData('Employee date resigned cannot be earlier than date hired.');
        }

        if (trim($employee->employment->employmentStatus) === '') {
            throw new InvalidPayrollData('Employee employment status is required.');
        }

        if (($employee->employment->department === null) || trim($employee->employment->department) === '') {
            throw new InvalidPayrollData('Employee department is required.');
        }

        if ($employee->email === null || filter_var($employee->email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidPayrollData('Employee email address is required and must be valid.');
        }

        if (MoneyHelper::minorAmount($employee->compensation->monthlyBasicSalary) <= 0) {
            throw new InvalidPayrollData('Employee monthly basic salary must be greater than zero.');
        }

        if ($employee->statutory->tin === null || trim($employee->statutory->tin) === '') {
            throw new InvalidPayrollData('Employee TIN is required.');
        }

        if ($employee->payrollDetails->accountNumber === null || trim($employee->payrollDetails->accountNumber) === '') {
            throw new InvalidPayrollData('Employee payroll account number is required.');
        }

        if ($employee->payrollDetails->bank === null || trim($employee->payrollDetails->bank) === '') {
            throw new InvalidPayrollData('Employee payroll bank is required.');
        }

        if ($employee->payrollDetails->branch === null || trim($employee->payrollDetails->branch) === '') {
            throw new InvalidPayrollData('Employee payroll branch is required.');
        }

        if ($employee->bonusTaxShieldAmount->isNegative()) {
            throw new InvalidPayrollData('Employee bonus tax shield amount cannot be negative.');
        }
    }
}
