<?php

namespace QuillBytes\PayrollEngine\Tests;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;
use QuillBytes\PayrollEngine\Calculators\PayrollCalculator;
use QuillBytes\PayrollEngine\PayrollEngine;
use QuillBytes\PayrollEngine\PayrollEngineFacade;
use QuillBytes\PayrollEngine\PayrollEngineServiceProvider;

function laravelIntegrationConfigRepository(array $items = []): object
{
    return new class($items)
    {
        /**
         * @param  array<string, mixed>  $items
         */
        public function __construct(private array $items = []) {}

        public function get(string $key, mixed $default = null): mixed
        {
            return $this->items[$key] ?? $default;
        }

        public function set(string $key, mixed $value): void
        {
            $this->items[$key] = $value;
        }
    };
}

function laravelIntegrationApplication(array $config = []): Container
{
    return new class(laravelIntegrationConfigRepository($config)) extends Container
    {
        public function __construct(private object $configRepository)
        {
            $this->instance('config', $this->configRepository);
        }

        public function runningInConsole(): bool
        {
            return true;
        }

        public function configPath(string $path = ''): string
        {
            $basePath = '/virtual/config';

            return $path === '' ? $basePath : $basePath.'/'.$path;
        }
    };
}

afterEach(function () {
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication(null);
    ServiceProvider::$publishes = [];
    ServiceProvider::$publishGroups = [];
});

it('registers the engine in Laravel container with recursively merged package config', function () {
    $app = laravelIntegrationApplication([
        PayrollEngineServiceProvider::CONFIG_KEY => [
            'defaults' => [
                'frequency' => 'weekly',
            ],
        ],
    ]);

    $provider = new PayrollEngineServiceProvider($app);
    $provider->register();

    $resolvedEngine = $app->make(PayrollEngine::class);
    $aliasedEngine = $app->make(PayrollEngineServiceProvider::SERVICE_KEY);
    /** @var object{get: callable} $config */
    $config = $app->make('config');
    $packageConfig = $config->get(PayrollEngineServiceProvider::CONFIG_KEY);

    expect($resolvedEngine)->toBeInstanceOf(PayrollEngine::class)
        ->and($aliasedEngine)->toBe($resolvedEngine)
        ->and($packageConfig['defaults']['frequency'])->toBe('weekly')
        ->and($packageConfig['defaults']['hours_per_day'])->toBe(8)
        ->and($packageConfig['strategies']['default']['workflow'])->toBe(PayrollCalculator::class)
        ->and($provider->provides())->toBe([
            PayrollEngine::class,
            PayrollEngineServiceProvider::SERVICE_KEY,
        ]);
});

it('publishes config and resolves the facade through the bound Laravel service', function () {
    $app = laravelIntegrationApplication();
    $provider = new PayrollEngineServiceProvider($app);
    $provider->register();
    $provider->boot();

    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($app);

    $resolvedEngine = $app->make(PayrollEngine::class);
    $facadeRoot = PayrollEngineFacade::getFacadeRoot();
    $register = PayrollEngineFacade::payrollRegister([]);
    $expectedDestination = '/virtual/config/'.PayrollEngineServiceProvider::CONFIG_KEY.'.php';
    $configPublishPaths = ServiceProvider::pathsToPublish(
        PayrollEngineServiceProvider::class,
        PayrollEngineServiceProvider::CONFIG_TAG,
    );
    $legacyConfigPublishPaths = ServiceProvider::pathsToPublish(
        PayrollEngineServiceProvider::class,
        'config',
    );
    $publishedSource = array_key_first($configPublishPaths);

    expect($facadeRoot)->toBe($resolvedEngine)
        ->and($register)->toBe([])
        ->and($publishedSource)->not->toBeNull()
        ->and(realpath((string) $publishedSource))->toBe(realpath(__DIR__.'/../config/config.php'))
        ->and($configPublishPaths[$publishedSource])->toBe($expectedDestination)
        ->and($legacyConfigPublishPaths)->toBe($configPublishPaths);
});
