<?php

namespace Jdclzn\PayrollEngine\Data;

use Carbon\CarbonImmutable;
use Jdclzn\PayrollEngine\Enums\PayrollRunStatus;
use Jdclzn\PayrollEngine\Exceptions\InvalidPayrollData;
use Jdclzn\PayrollEngine\Support\MoneyHelper;
use Money\Money;

final class PayrollRun
{
    /**
     * @param  array<int, PayrollResult>  $results
     * @param  array<int, AuditEntry>  $auditTrail
     */
    public function __construct(
        public readonly CompanyProfile $company,
        public readonly PayrollPeriod $period,
        public readonly array $results,
        public PayrollRunStatus $status = PayrollRunStatus::Draft,
        public array $auditTrail = [],
        public ?CarbonImmutable $preparedAt = null,
        public ?CarbonImmutable $approvedAt = null,
        public ?CarbonImmutable $processedAt = null,
        public ?CarbonImmutable $releasedAt = null,
    ) {}

    public function prepare(string $actor, ?CarbonImmutable $preparedAt = null, ?string $notes = null): self
    {
        if ($this->status !== PayrollRunStatus::Draft) {
            throw new InvalidPayrollData('Payroll run can only be prepared from draft status.');
        }

        if (! $this->company->allowsPreparer($actor)) {
            throw new InvalidPayrollData('The provided actor is not an allowed payroll preparer for this company.');
        }

        $preparedAt ??= CarbonImmutable::now();
        $this->status = PayrollRunStatus::Prepared;
        $this->preparedAt = $preparedAt;
        $this->auditTrail[] = new AuditEntry($actor, 'prepared', $preparedAt, $notes);

        return $this;
    }

    public function approve(string $actor, ?CarbonImmutable $approvedAt = null, ?string $notes = null): self
    {
        if ($this->status !== PayrollRunStatus::Prepared) {
            throw new InvalidPayrollData('Payroll run must be prepared before approval.');
        }

        if (! $this->company->allowsApprover($actor)) {
            throw new InvalidPayrollData('The provided actor is not an allowed payroll approver for this company.');
        }

        $approvedAt ??= CarbonImmutable::now();
        $this->status = PayrollRunStatus::Approved;
        $this->approvedAt = $approvedAt;
        $this->auditTrail[] = new AuditEntry($actor, 'approved', $approvedAt, $notes);

        return $this;
    }

    public function process(string $actor, ?CarbonImmutable $processedAt = null, ?string $notes = null): self
    {
        if ($this->status !== PayrollRunStatus::Approved) {
            throw new InvalidPayrollData('Payroll run must be approved before processing.');
        }

        $processedAt ??= CarbonImmutable::now();

        if ($processedAt->greaterThanOrEqualTo($this->period->releaseDate)) {
            throw new InvalidPayrollData('Payroll run must be processed before the configured payroll schedule date.');
        }

        $this->status = PayrollRunStatus::Processed;
        $this->processedAt = $processedAt;
        $this->auditTrail[] = new AuditEntry($actor, 'processed', $processedAt, $notes);

        return $this;
    }

    public function reopen(string $actor, ?CarbonImmutable $reopenedAt = null, ?string $notes = null): self
    {
        if ($this->status !== PayrollRunStatus::Processed) {
            throw new InvalidPayrollData('Only processed payroll runs can be reopened.');
        }

        if (! $this->company->allowsAdministrator($actor)) {
            throw new InvalidPayrollData('The provided actor is not an allowed payroll administrator for this company.');
        }

        $reopenedAt ??= CarbonImmutable::now();

        if ($reopenedAt->greaterThanOrEqualTo($this->period->releaseDate)) {
            throw new InvalidPayrollData('Processed payroll runs cannot be reopened on or after the current payroll schedule date.');
        }

        $this->status = PayrollRunStatus::Draft;
        $this->processedAt = null;
        $this->auditTrail[] = new AuditEntry($actor, 'reopened', $reopenedAt, $notes);

        return $this;
    }

    public function release(string $actor, ?CarbonImmutable $releasedAt = null, ?string $notes = null): self
    {
        $releasedAt ??= CarbonImmutable::now();

        if ($this->status !== PayrollRunStatus::Processed) {
            throw new InvalidPayrollData('Payroll run must be processed before release.');
        }

        if ($releasedAt->lessThan($this->period->releaseDate)) {
            throw new InvalidPayrollData('Payroll run cannot be released earlier than the configured release date.');
        }

        $this->status = PayrollRunStatus::Released;
        $this->releasedAt = $releasedAt;
        $this->auditTrail[] = new AuditEntry($actor, 'released', $releasedAt, $notes);

        return $this;
    }

    public function assertEditable(): void
    {
        if (in_array($this->status, [PayrollRunStatus::Processed, PayrollRunStatus::Released], true)) {
            throw new InvalidPayrollData('Processed or released payroll runs are locked and cannot be edited.');
        }
    }

    public function assertCanGeneratePayrollFiles(): void
    {
        if ($this->status !== PayrollRunStatus::Processed && $this->status !== PayrollRunStatus::Released) {
            throw new InvalidPayrollData('Payroll files can only be generated once the payroll run is processed.');
        }
    }

    public function assertCanGeneratePayslips(?CarbonImmutable $generatedAt = null): void
    {
        $generatedAt ??= CarbonImmutable::now();

        if ($this->status !== PayrollRunStatus::Processed && $this->status !== PayrollRunStatus::Released) {
            throw new InvalidPayrollData('Payslips can only be generated once the payroll run is processed.');
        }

        if ($generatedAt->lessThan($this->period->releaseDate)) {
            throw new InvalidPayrollData('Payslips can only be generated on or after the configured payroll schedule date.');
        }
    }

    public function totalNetPay(): Money
    {
        return MoneyHelper::sum(array_map(static fn (PayrollResult $result) => $result->netPay, $this->results));
    }

    public function totalTakeHomePay(): Money
    {
        return MoneyHelper::sum(array_map(static fn (PayrollResult $result) => $result->takeHomePay, $this->results));
    }
}
