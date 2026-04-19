<?php

namespace QuillBytes\PayrollEngine\Policies;

use QuillBytes\PayrollEngine\Contracts\PayrollEdgeCasePolicy;
use QuillBytes\PayrollEngine\Data\CompanyProfile;
use QuillBytes\PayrollEngine\Data\EmployeeProfile;
use QuillBytes\PayrollEngine\Data\PayrollInput;
use QuillBytes\PayrollEngine\Data\PayrollIssue;
use QuillBytes\PayrollEngine\Data\PayrollResult;
use QuillBytes\PayrollEngine\Exceptions\InvalidPayrollData;
use QuillBytes\PayrollEngine\Support\EdgeCasePolicyConfig;

final readonly class AttendanceDataPolicy implements PayrollEdgeCasePolicy
{
    public function __construct(
        private EdgeCasePolicyConfig $config,
    ) {}

    public function prepare(
        CompanyProfile $company,
        EmployeeProfile $employee,
        PayrollInput $input,
    ): PayrollInput {
        if ($this->mode($company, $employee, $input) === 'error' && $this->attendanceRequired($company, $employee, $input) && ! $this->hasAttendanceData($input)) {
            throw new InvalidPayrollData('Attendance data is required by policy but was not provided.');
        }

        return $input;
    }

    public function finalize(
        CompanyProfile $company,
        EmployeeProfile $employee,
        PayrollInput $input,
        PayrollResult $result,
    ): PayrollResult {
        if (! $this->attendanceRequired($company, $employee, $input) || $this->hasAttendanceData($input) || $this->mode($company, $employee, $input) !== 'warn') {
            return $result;
        }

        $issues = [
            ...$result->issues,
            new PayrollIssue(
                code: 'no_attendance_data',
                message: 'Attendance data was expected for this payroll but no attendance payload was provided, so scheduled payroll values were used.',
            ),
        ];

        return $result->with(['issues' => $issues]);
    }

    private function attendanceRequired(
        CompanyProfile $company,
        EmployeeProfile $employee,
        PayrollInput $input,
    ): bool {
        return (bool) (
            $input->metadata['attendance_required']
            ?? $employee->metadata['attendance_required']
            ?? $company->metadata['attendance_required']
            ?? $this->config->value($company, $employee, $input, 'attendance_required', false)
        );
    }

    private function hasAttendanceData(PayrollInput $input): bool
    {
        $metadata = $input->metadata;

        foreach (['attendance', 'attendance_data', 'attendanceData'] as $key) {
            $value = $metadata[$key] ?? null;

            if ((is_array($value) && $value !== []) || (is_string($value) && trim($value) !== '')) {
                return true;
            }
        }

        foreach (['worked_days', 'workedDays', 'worked_hours', 'workedHours', 'attendance_provided', 'attendanceProvided', 'approved_overtime', 'approvedOvertime'] as $key) {
            if (($metadata[$key] ?? null) !== null && $metadata[$key] !== '') {
                return true;
            }
        }

        return false;
    }

    private function mode(CompanyProfile $company, EmployeeProfile $employee, PayrollInput $input): string
    {
        return strtolower((string) $this->config->value($company, $employee, $input, 'no_attendance_data', 'allow'));
    }
}
