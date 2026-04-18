<?php

namespace Jdclzn\PayrollEngine;

use Jdclzn\PayrollEngine\Data\PayrollResult;
use Jdclzn\PayrollEngine\Data\PayrollRun;
use Jdclzn\PayrollEngine\Exceptions\InvalidPayrollData;
use Jdclzn\PayrollEngine\Normalizers\CompanyProfileNormalizer;
use Jdclzn\PayrollEngine\Normalizers\EmployeeProfileNormalizer;
use Jdclzn\PayrollEngine\Normalizers\PayrollInputNormalizer;
use Jdclzn\PayrollEngine\Normalizers\PayrollPeriodNormalizer;
use Jdclzn\PayrollEngine\Policies\ClientPolicyRegistry;
use Jdclzn\PayrollEngine\Policies\AttendanceDataPolicy;
use Jdclzn\PayrollEngine\Policies\DeductionOverlapPolicy;
use Jdclzn\PayrollEngine\Policies\NetPayResolutionPolicy;
use Jdclzn\PayrollEngine\Policies\PayrollEdgeCasePolicyPipeline;
use Jdclzn\PayrollEngine\Policies\RuleConflictPolicy;
use Jdclzn\PayrollEngine\Reports\PayrollAllocationSummaryBuilder;
use Jdclzn\PayrollEngine\Reports\PayrollRegisterBuilder;
use Jdclzn\PayrollEngine\Reports\PayslipBuilder;
use Jdclzn\PayrollEngine\Strategies\PayrollStrategyResolver;
use Jdclzn\PayrollEngine\Support\AttributeReader;
use Jdclzn\PayrollEngine\Support\EdgeCasePolicyConfig;
use Jdclzn\PayrollEngine\Support\RetroAdjustmentInputBuilder;
use Jdclzn\PayrollEngine\Validators\CompanyProfileValidator;
use Jdclzn\PayrollEngine\Validators\EmployeeProfileValidator;
use Jdclzn\PayrollEngine\Validators\PayrollInputValidator;

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
        $reader = new AttributeReader();
        $registry = new ClientPolicyRegistry();
        $edgeCaseConfig = new EdgeCasePolicyConfig();
        $this->companyNormalizer = new CompanyProfileNormalizer($reader, $registry, $config);
        $this->employeeNormalizer = new EmployeeProfileNormalizer($reader);
        $this->periodNormalizer = new PayrollPeriodNormalizer($reader);
        $this->inputNormalizer = new PayrollInputNormalizer($reader, $this->periodNormalizer);
        $this->strategyResolver = new PayrollStrategyResolver($config, $factory);
        $this->edgeCasePolicyPipeline = new PayrollEdgeCasePolicyPipeline([
            new RuleConflictPolicy($edgeCaseConfig),
            new AttendanceDataPolicy($edgeCaseConfig),
            new DeductionOverlapPolicy($edgeCaseConfig),
            new NetPayResolutionPolicy($edgeCaseConfig),
        ]);
        $this->payslipBuilder = new PayslipBuilder();
        $this->registerBuilder = new PayrollRegisterBuilder();
        $this->allocationSummaryBuilder = new PayrollAllocationSummaryBuilder();
        $this->retroAdjustmentInputBuilder = new RetroAdjustmentInputBuilder();
        $this->companyValidator = new CompanyProfileValidator();
        $this->employeeValidator = new EmployeeProfileValidator();
        $this->inputValidator = new PayrollInputValidator();
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

        return $this->edgeCasePolicyPipeline->finalize($companyProfile, $employeeProfile, $payrollInput, $result);
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
    ): \Jdclzn\PayrollEngine\Data\PayrollInput {
        $periodPayload = $releasePeriod;

        if (! $releasePeriod instanceof \Jdclzn\PayrollEngine\Data\PayrollPeriod && is_array($releasePeriod)) {
            $periodPayload = $releasePeriod + ['run_type' => 'adjustment'];
        }

        if (! $releasePeriod instanceof \Jdclzn\PayrollEngine\Data\PayrollPeriod && is_object($releasePeriod)) {
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
    public function generatePayslips(PayrollRun $run, ?\Carbon\CarbonImmutable $generatedAt = null): array
    {
        $run->assertCanGeneratePayslips($generatedAt);

        return array_map(fn (PayrollResult $result) => $this->payslip($result), $run->results);
    }
}
