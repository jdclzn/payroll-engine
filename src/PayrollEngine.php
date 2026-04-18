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
use Jdclzn\PayrollEngine\Reports\PayrollRegisterBuilder;
use Jdclzn\PayrollEngine\Reports\PayslipBuilder;
use Jdclzn\PayrollEngine\Strategies\PayrollStrategyResolver;
use Jdclzn\PayrollEngine\Support\AttributeReader;
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
        $this->companyNormalizer = new CompanyProfileNormalizer($reader, $registry, $config);
        $this->employeeNormalizer = new EmployeeProfileNormalizer($reader);
        $this->periodNormalizer = new PayrollPeriodNormalizer($reader);
        $this->inputNormalizer = new PayrollInputNormalizer($reader, $this->periodNormalizer);
        $this->strategyResolver = new PayrollStrategyResolver($config, $factory);
        $this->payslipBuilder = new PayslipBuilder();
        $this->registerBuilder = new PayrollRegisterBuilder();
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

        return $this->strategyResolver
            ->workflowFor($companyProfile->clientCode)
            ->calculate($companyProfile, $employeeProfile, $payrollInput);
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
