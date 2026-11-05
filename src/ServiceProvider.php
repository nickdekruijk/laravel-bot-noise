<?php

namespace NickDeKruijk\BotNoise;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use NickDeKruijk\BotNoise\Middleware\SanitizeQueryString;
use Throwable;

class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/bot-noise.php', 'bot-noise');

        $this->app->resolving(ExceptionHandler::class, function (object $handler): void {
            $this->configureExceptionHandler($handler);
        });

        if ($this->app->resolved(ExceptionHandler::class)) {
            $this->configureExceptionHandler($this->app->make(ExceptionHandler::class));
        }
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/bot-noise.php' => config_path('bot-noise.php'),
        ], 'bot-noise-config');

        if (! $this->enabled()) {
            return;
        }

        if (config('bot-noise.sanitize_query_string', true)) {
            $this->registerMiddleware();
        }

        if (config('bot-noise.array_guard', true)) {
            $this->registerArrayGuard();
        }
    }

    /**
     * dontReport() and dontReportWhen() are checked before any report callback, so
     * they also work on an old style app/Exceptions/Handler.php, where Sentry
     * registers first. reportable() is the fallback for a handler without them:
     * Laravel older than 12 for the rules without an own class, and Collision's
     * decorator in tests. That one has to stay a callback that only says no for
     * noise. Never chain ->stop() on it, that stops the reporting of everything.
     */
    private function configureExceptionHandler(object $handler): void
    {
        if (! $this->app->bound('config') || ! $this->enabled()
            || ! $this->app->environment((array) config('bot-noise.environments', ['production']))) {
            return;
        }

        if (method_exists($handler, 'dontReport')) {
            $handler->dontReport(NoiseFilter::CLASSES);
        }

        if (method_exists($handler, 'dontReportWhen')) {
            $handler->dontReportWhen(fn (Throwable $exception): bool => NoiseFilter::isNoiseByContent($exception));
        } elseif (method_exists($handler, 'reportable')) {
            $handler->reportable(fn (Throwable $exception): ?bool => NoiseFilter::isNoise($exception) ? false : null);
        }
    }

    private function registerMiddleware(): void
    {
        $kernel = $this->app->make(Kernel::class);

        if (method_exists($kernel, 'prependMiddleware')) {
            $kernel->prependMiddleware(SanitizeQueryString::class);
        }
    }

    private function registerArrayGuard(): void
    {
        if (function_exists('Livewire\on')) {
            \Livewire\on('update', function (object $component, string $path, mixed $value): void {
                ArrayGuard::check($component, $path, $value);
            });
        }
    }

    private function enabled(): bool
    {
        return (bool) config('bot-noise.enabled', true);
    }
}
