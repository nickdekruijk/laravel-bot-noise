<?php

namespace NickDeKruijk\BotNoise\Tests;

use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use NickDeKruijk\BotNoise\Tests\Fixtures\ArrayTargets;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\DataProvider;

class ArrayGuardTest extends TestCase
{
    #[DataProvider('refused')]
    public function test_an_array_is_refused(string $property): void
    {
        Livewire::test(ArrayTargets::class)->set($property, ['x'])->assertForbidden();
    }

    public static function refused(): array
    {
        return [
            'untyped, no default' => ['untypedEmpty'],
            'untyped, scalar default' => ['untypedScalar'],
            'typed scalar' => ['typedScalar'],
            'nullable scalar' => ['nullableScalar'],
        ];
    }

    #[DataProvider('allowed')]
    public function test_an_array_is_allowed(string $property): void
    {
        Livewire::test(ArrayTargets::class)->set($property, ['x'])->assertOk()->assertSet($property, ['x']);
    }

    public static function allowed(): array
    {
        return [
            'typed array' => ['typedArray'],
            'nullable array' => ['nullableArray'],
            'untyped, array default' => ['untypedArray'],
            'filled in mount' => ['filledInMount'],
            'union with array' => ['union'],
            'mixed' => ['anything'],
        ];
    }

    public function test_a_nested_path_is_left_alone(): void
    {
        Livewire::test(ArrayTargets::class)->set('form.tags', ['x'])->assertOk()->assertSet('form.tags', ['x']);
    }

    public function test_a_scalar_is_left_alone(): void
    {
        Livewire::test(ArrayTargets::class)->set('untypedEmpty', 'AB-12-CD')->assertOk()->assertSet('untypedEmpty', 'AB-12-CD');
    }

    public function test_a_listed_property_is_left_alone(): void
    {
        $component = Livewire::test(ArrayTargets::class);

        config(['bot-noise.array_guard_except' => [$component->instance()->getName().'.untypedEmpty']]);

        $component->set('untypedEmpty', ['x'])->assertOk()->assertSet('untypedEmpty', ['x']);
    }

    public function test_a_refusal_logs_the_property_but_not_the_value(): void
    {
        Log::spy();

        Livewire::test(ArrayTargets::class)->set('untypedEmpty', ['secret']);

        Log::shouldHaveReceived('info')->once()->withArgs(function (string $message, array $context): bool {
            return $context['property'] === 'untypedEmpty' && ! str_contains(json_encode($context), 'secret');
        });
    }

    public function test_a_refusal_is_not_reported(): void
    {
        Livewire::test(ArrayTargets::class)->set('untypedEmpty', ['x']);

        $this->assertSame([], $this->reported);
    }

    #[DefineEnvironment('useStaging')]
    public function test_it_runs_outside_the_filter_environments(): void
    {
        Livewire::test(ArrayTargets::class)->set('untypedEmpty', ['x'])->assertForbidden();
    }

    #[DefineEnvironment('disableGuard')]
    public function test_it_can_be_disabled(): void
    {
        Livewire::test(ArrayTargets::class)->set('untypedEmpty', ['x'])->assertOk();
    }

    protected function useStaging($app): void
    {
        $this->environment = 'staging';
    }

    protected function disableGuard($app): void
    {
        $app['config']->set('bot-noise.array_guard', false);
    }
}
