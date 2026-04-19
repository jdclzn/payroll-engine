<?php

namespace QuillBytes\PayrollEngine;

use Carbon\CarbonImmutable;
use QuillBytes\PayrollEngine\Contracts\PayrollEdgeCasePolicy;
use QuillBytes\PayrollEngine\Data\PayrollInput;
use QuillBytes\PayrollEngine\Data\PayrollPeriod;
use QuillBytes\PayrollEngine\Data\PayrollResult;
use QuillBytes\PayrollEngine\Data\PayrollRun;
use QuillBytes\PayrollEngine\Exceptions\InvalidPayrollData;
use QuillBytes\PayrollEngine\Normalizers\CompanyProfileNormalizer;
use QuillBytes\PayrollEngine\Normalizers\EmployeeProfileNormalizer;
use QuillBytes\PayrollEngine\Normalizers\PayrollInputNormalizer;
use QuillBytes\PayrollEngine\Normalizers\PayrollPeriodNormalizer;
use QuillBytes\PayrollEngine\Policies\AttendanceDataPolicy;
use QuillBytes\PayrollEngine\Policies\ClientPolicyRegistry;
use QuillBytes\PayrollEngine\Policies\DeductionOverlapPolicy;
use QuillBytes\PayrollEngine\Policies\NetPayResolutionPolicy;
use QuillBytes\PayrollEngine\Policies\PayrollEdgeCasePolicyPipeline;
use QuillBytes\PayrollEngine\Policies\RuleConflictPolicy;
use QuillBytes\PayrollEngine\Reports\PayrollAllocationSummaryBuilder;
use QuillBytes\PayrollEngine\Reports\PayrollRegisterBuilder;
use QuillBytes\PayrollEngine\Reports\PayslipBuilder;
use QuillBytes\PayrollEngine\Strategies\PayrollStrategyResolver;
use QuillBytes\PayrollEngine\Support\AttributeReader;
use QuillBytes\PayrollEngine\Support\EdgeCasePolicyConfig;
use QuillBytes\PayrollEngine\Support\PayrollAuditTrailBuilder;
use QuillBytes\PayrollEngine\Support\PayrollResultTraceEnricher;
use QuillBytes\PayrollEngine\Support\RetroAdjustmentInputBuilder;
use QuillBytes\PayrollEngine\Validators\CompanyProfileValidator;
use QuillBytes\PayrollEngine\Validators\EmployeeProfileValidator;
use QuillBytes\PayrollEngine\Validators\PayrollInputValidator;

class PayrollEngine
{
    private CompanyProfileNormalizer $companyNormalizer;

    private EmployeeProfileNormalizer $employeeNormalizer;

    private PayrollInputNormalizer $inputNormalizer;

    private PayrollPeriodNormalizer $periodNormalizer;

    private PayslipBuilder $payslipBuilder;

    private PayrollRegisterBuilder $registerBuilder;

    private PayrollAllocationSummaryBuilder $allocationSummaryBuilder;

    private RetroAdjustmentInputBuilder $retroAdjustmentInputBuilder;

    private PayrollResultTraceEnricher $resultTraceEnricher;

    private PayrollAuditTrailBuilder $auditTrailBuilder;

    private PayrollEdgeCasePolicyPipeline $edgeCasePolicyPipeline;

    private PayrollStrategyResolver $strategyResolver;

    private CompanyProfileValidator $companyValidator;

    private EmployeeProfileValidator $employeeValidator;

    private PayrollInputValidator $inputValidator;

    /**
     * @param  array<string, mixed>  $config
     * @param  (callable(class-string):object)|null  $factory
     */
    public function __construct(array $config = [], ?callable $factory = null)
    {
        $reader = new AttributeReader;
        $registry = new ClientPolicyRegistry;
        $edgeCaseConfig = new EdgeCasePolicyConfig;
        $factoryResolver = $factory === null ? null : $factory(...);
        $this->companyNormalizer = new CompanyProfileNormalizer($reader, $registry, $config);
        $this->employeeNormalizer = new EmployeeProfileNormalizer($reader);
        $this->periodNormalizer = new PayrollPeriodNormalizer($reader);
        $this->inputNormalizer = new PayrollInputNormalizer($reader, $this->periodNormalizer);
        $this->strategyResolver = new PayrollStrategyResolver($config, $factory);
        $this->edgeCasePolicyPipeline = new PayrollEdgeCasePolicyPipeline(
            $this->edgeCasePolicies($config, $edgeCaseConfig, $factoryResolver)
        );
        $this->resultTraceEnricher = new PayrollResultTraceEnricher;
        $this->auditTrailBuilder = new PayrollAuditTrailBuilder;
        $this->payslipBuilder = new PayslipBuilder;
        $this->registerBuilder = new PayrollRegisterBuilder;
        $this->allocationSummaryBuilder = new PayrollAllocationSummaryBuilder;
        $this->retroAdjustmentInputBuilder = new RetroAdjustmentInputBuilder;
        $this->companyValidator = new CompanyProfileValidator;
        $this->employeeValidator = new EmployeeProfileValidator;
        $this->inputValidator = new PayrollInputValidator;
    }

    public function compute(mixed $company, mixed $employee, mixed $input): PayrollResult
    {
        $companyProfile = $this->companyNormalizer->normalize($company);
        $employeeProfile = $this->employeeNormalizer->normalize($employee);
        $payrollInput = $this->inputNormalizer->normalize($input, $companyProfile);
        $this->companyValidator->validate($companyProfile);
        $this->employeeValidator->validate($employeeProfile);
        $this->inputValidator->validate($payrollInput);
        $payrollInput = $this->edgeCasePolicyPipeline->prepare($companyProfile, $employeeProfile, $payrollInput);

        $result = $this->strategyResolver
            ->workflowFor($companyProfile->clientCode)
            ->calculate($companyProfile, $employeeProfile, $payrollInput);

        $result = $this->edgeCasePolicyPipeline->finalize($companyProfile, $employeeProfile, $payrollInput, $result);
        $result = $this->resultTraceEnricher->enrich($result);

        return $result->with([
            'audit' => $this->auditTrailBuilder->build(
                $companyProfile,
                $employeeProfile,
                $payrollInput,
                $result,
                $this->strategyResolver->describeFor($companyProfile->clientCode),
                $this->edgeCasePolicyPipeline->policyNames(),
            ),
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  (callable(class-string):object)|null  $factory
     * @return array<int, PayrollEdgeCasePolicy>
     */
    private function edgeCasePolicies(array $config, EdgeCasePolicyConfig $edgeCaseConfig, ?callable $factory): array
    {
        $configured = $config['edge_case_policies'] ?? null;

        if (! is_array($configured) || $configured === []) {
            return [
                new RuleConflictPolicy($edgeCaseConfig),
                new AttendanceDataPolicy($edgeCaseConfig),
                new DeductionOverlapPolicy($edgeCaseConfig),
                new NetPayResolutionPolicy($edgeCaseConfig),
            ];
        }

        $policies = [];

        foreach ($configured as $definition) {
            if ($definition instanceof PayrollEdgeCasePolicy) {
                $policies[] = $definition;

                continue;
            }

            if (! is_string($definition) || $definition === '') {
                throw new InvalidPayrollData('Configured edge case policies must be policy instances or class strings.');
            }

            $instance = $factory !== null
                ? $factory($definition)
                : new $definition;

            if (! $instance instanceof PayrollEdgeCasePolicy) {
                throw new InvalidPayrollData(sprintf(
                    'Configured edge case policy [%s] must implement %s.',
                    $definition,
                    PayrollEdgeCasePolicy::class,
                ));
            }

            $policies[] = $instance;
        }

        return $policies;
    }

    /**
     * @param  iterable<int, mixed>  $items
     */
    public function run(mixed $company, mixed $period, iterable $items): PayrollRun
    {
        $companyProfile = $this->companyNormalizer->normalize($company);
        $periodProfile = $this->periodNormalizer->normalize($period, $companyProfile);
        $results = [];

        foreach ($items as $item) {
            $employee = is_array($item) && array_key_exists('employee', $item) ? $item['employee'] : $item;
            $input = is_array($item) && array_key_exists('input', $item) ? $item['input'] : [];
            $employeeProfile = $this->employeeNormalizer->normalize($employee);

            if (! $employeeProfile->isActiveDuring($periodProfile)) {
                continue;
            }

            $results[] = $this->compute(
                $companyProfile,
                $employeeProfile,
                array_merge(is_array($input) ? $input : [], ['period' => $periodProfile]),
            );
        }

        if ($results === []) {
            throw new InvalidPayrollData('No active payroll entries were generated for the supplied period.');
        }

        return new PayrollRun($companyProfile, $periodProfile, $results);
    }

    /**
     * @return array<string, mixed>
     */
    public function payslip(PayrollResult $result): array
    {
        return $this->payslipBuilder->build($result);
    }

    /**
     * @param  array<int, PayrollResult>  $results
     * @return array<int, array<string, mixed>>
     */
    public function payrollRegister(array $results): array
    {
        return $this->registerBuilder->build($results);
    }

    /**
     * Backward-compatible alias for payroll register generation.
     *
     * @param  array<int, PayrollResult>  $results
     * @return array<int, array<string, mixed>>
     */
    public function register(array $results): array
    {
        return $this->payrollRegister($results);
    }

    /**
     * @param  array<int, PayrollResult>  $results
     * @return array<int, array<string, mixed>>
     */
    public function allocationSummary(array $results, string $dimension): array
    {
        return $this->allocationSummaryBuilder->build($results, $dimension);
    }

    public function retroAdjustmentInput(
        PayrollResult $original,
        PayrollResult $recomputed,
        mixed $releasePeriod,
    ): PayrollInput {
        $periodPayload = $releasePeriod;

        if (! $releasePeriod instanceof PayrollPeriod && is_array($releasePeriod)) {
            $periodPayload = $releasePeriod + ['run_type' => 'adjustment'];
        }

        if (! $releasePeriod instanceof PayrollPeriod && is_object($releasePeriod)) {
            $periodPayload = (array) $releasePeriod;
            $periodPayload['run_type'] = $periodPayload['run_type'] ?? 'adjustment';
        }

        $periodProfile = $this->periodNormalizer->normalize($periodPayload, $recomputed->company);

        return $this->retroAdjustmentInputBuilder->build($original, $recomputed, $periodProfile);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function generatePayrollFiles(PayrollRun $run): array
    {
        $run->assertCanGeneratePayrollFiles();

        return $this->payrollRegister($run->results);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function generatePayslips(PayrollRun $run, ?CarbonImmutable $generatedAt = null): array
    {
        $run->assertCanGeneratePayslips($generatedAt);

        return array_map(fn (PayrollResult $result) => $this->payslip($result), $run->results);
    }
}
