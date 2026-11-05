<?php

namespace NickDeKruijk\BotNoise\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\Attributes\DefineEnvironment;

class SanitizeQueryStringTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        Route::get('/echo', fn (Request $request) => response()->json($request->query()));
    }

    public function test_invalid_bytes_are_replaced(): void
    {
        $this->get('/echo?q=caf%E9')->assertOk()->assertExactJson(['q' => 'caf?']);
    }

    public function test_nested_values_are_replaced(): void
    {
        $this->get('/echo?a[x]=b%E9')->assertOk()->assertExactJson(['a' => ['x' => 'b?']]);
    }

    public function test_valid_utf8_is_left_alone(): void
    {
        $this->get('/echo?q=caf%C3%A9')->assertOk()->assertExactJson(['q' => 'café']);
    }

    #[DefineEnvironment('useStaging')]
    public function test_it_runs_outside_the_filter_environments(): void
    {
        $this->get('/echo?q=caf%E9')->assertOk()->assertExactJson(['q' => 'caf?']);
    }

    #[DefineEnvironment('disableSanitizing')]
    public function test_it_can_be_disabled(): void
    {
        $this->assertFalse(app(\Illuminate\Contracts\Http\Kernel::class)->hasMiddleware(\NickDeKruijk\BotNoise\Middleware\SanitizeQueryString::class));
    }

    protected function useStaging($app): void
    {
        $this->environment = 'staging';
    }

    protected function disableSanitizing($app): void
    {
        $app['config']->set('bot-noise.sanitize_query_string', false);
    }
}
