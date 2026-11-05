<?php

namespace NickDeKruijk\BotNoise\Tests;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Livewire\LivewireServiceProvider;
use NickDeKruijk\BotNoise\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Throwable;

abstract class TestCase extends Orchestra
{
    /**
     * What reached the fake Sentry callback.
     *
     * @var array<int, Throwable>
     */
    protected array $reported = [];

    /**
     * Testbench runs #[DefineEnvironment] before defineEnvironment(), so a test sets this.
     */
    protected string $environment = 'production';

    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            ServiceProvider::class,
        ];
    }

    /**
     * Registers like Integration::handles() inside withExceptions(): after the
     * handler has resolved, so after the package.
     *
     * Recent Livewire versions stay silent on some of these exceptions by themselves,
     * when app.debug is off. With debug on the tests hit this package on every version.
     */
    protected function defineEnvironment($app): void
    {
        $app['env'] = $this->environment;
        $app['config']->set('app.debug', true);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

        $sentry = function (ExceptionHandler $handler): void {
            $handler->reportable(function (Throwable $e): bool {
                $this->reported[] = $e;

                return false;
            });
        };

        $app->afterResolving(ExceptionHandler::class, $sentry);
    }

    protected function assertNotReported(Throwable $exception): void
    {
        report($exception);

        $this->assertSame([], $this->reported, $exception::class.' reached the reporter');
    }

    protected function assertReported(Throwable $exception): void
    {
        report($exception);

        $this->assertSame([$exception], $this->reported, $exception::class.' did not reach the reporter');
    }
}
