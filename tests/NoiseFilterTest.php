<?php

namespace NickDeKruijk\BotNoise\Tests;

use ErrorException;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Features\SupportFileUploads\MissingFileUploadsTraitException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;
use NickDeKruijk\BotNoise\NoiseFilter;
use NickDeKruijk\BotNoise\Tests\Fixtures\ArrayTargets;
use NickDeKruijk\BotNoise\Tests\Fixtures\Plain;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Throwable;
use TypeError;

class NoiseFilterTest extends TestCase
{
    private const HANDLE_COMPONENTS = '/app/vendor/livewire/livewire/src/Mechanisms/HandleComponents/HandleComponents.php';

    public function test_corrupt_payload_is_not_reported(): void
    {
        $this->assertNotReported(new CorruptComponentPayloadException);
    }

    public function test_locked_property_is_not_reported(): void
    {
        $this->assertNotReported(new CannotUpdateLockedPropertyException('toasts'));
    }

    public function test_missing_upload_trait_is_not_reported(): void
    {
        $this->assertNotReported(new MissingFileUploadsTraitException(new Plain));
    }

    public function test_the_probe_method_is_not_reported(): void
    {
        $this->assertNotReported(new MethodNotFoundException('_xNonExist'));
    }

    public function test_a_real_missing_method_is_reported(): void
    {
        $this->assertReported(new MethodNotFoundException('openTask'));
    }

    public function test_a_broken_payload_in_handle_components_is_not_reported(): void
    {
        $this->assertNotReported(new ErrorException('Trying to access array offset on null', 0, E_WARNING, self::HANDLE_COMPONENTS, 12));
    }

    public function test_the_same_warning_in_application_code_is_reported(): void
    {
        $this->assertReported(new ErrorException('Trying to access array offset on null', 0, E_WARNING, '/app/app/Livewire/Cart.php', 12));
    }

    public function test_input_nesting_is_not_reported(): void
    {
        $this->assertNotReported(new ErrorException('parse_str(): Input variable nesting level exceeded 64. To increase the limit change max_input_nesting_level in php.ini.'));
    }

    public function test_an_ordinary_exception_is_reported(): void
    {
        $this->assertReported(new RuntimeException('real bug'));
    }

    public function test_an_ordinary_error_exception_is_reported(): void
    {
        $this->assertReported(new ErrorException('Undefined variable $cart'));
    }

    #[DefineEnvironment('disableArrayGuard')]
    public function test_an_array_for_a_typed_property_is_noise_while_livewire_assigns_it(): void
    {
        try {
            $this->withoutExceptionHandling();
            Livewire::test(ArrayTargets::class)->set('typedScalar', ['x']);
            $this->fail('Expected a TypeError');
        } catch (TypeError $e) {
            $this->assertStringContainsString('Cannot assign array to property', $e->getMessage());
            $this->assertTrue(NoiseFilter::isNoise($e));
        }
    }

    public function test_the_same_type_error_in_application_code_is_reported(): void
    {
        $target = new class
        {
            public string $name = '';
        };

        try {
            $target->name = ['x'];
            $this->fail('Expected a TypeError');
        } catch (TypeError $e) {
            $this->assertReported($e);
        }
    }

    /**
     * The whole route: a forged checksum through Livewire's real endpoint.
     */
    public function test_a_forged_checksum_is_refused_but_not_reported(): void
    {
        $this->postForgedSnapshot()->assertStatus(500);

        $this->assertSame([], $this->reported);
    }

    #[DefineEnvironment('useStaging')]
    public function test_a_forged_checksum_is_reported_on_staging(): void
    {
        $this->postForgedSnapshot()->assertStatus(500);

        $this->assertCount(1, $this->reported);
        $this->assertInstanceOf(CorruptComponentPayloadException::class, $this->reported[0]);
    }

    #[DefineEnvironment('useStaging')]
    #[DataProvider('noise')]
    public function test_noise_is_reported_outside_the_configured_environments(Throwable $exception): void
    {
        $this->assertReported($exception);
    }

    #[DefineEnvironment('disableEverything')]
    #[DataProvider('noise')]
    public function test_noise_is_reported_when_disabled(Throwable $exception): void
    {
        $this->assertReported($exception);
    }

    public static function noise(): array
    {
        return [
            'class rule' => [new CorruptComponentPayloadException],
            'content rule' => [new MethodNotFoundException('_xNonExist')],
        ];
    }

    private function postForgedSnapshot()
    {
        // A real snapshot from a mounted component, so only the checksum is wrong.
        $snapshot = Livewire::test(Plain::class)->snapshot;
        $snapshot['checksum'] = 'forged';

        // Outside the testing environment the CSRF check is active, and it would stop
        // the request before Livewire verifies the checksum. The name differs per Laravel.
        $this->withoutMiddleware([
            'Illuminate\Foundation\Http\Middleware\ValidateCsrfToken',
            'Illuminate\Foundation\Http\Middleware\VerifyCsrfToken',
            'Illuminate\Foundation\Http\Middleware\PreventRequestForgery',
        ]);

        return $this->withHeaders(['X-Livewire' => '1'])->postJson(Livewire::getUpdateUri(), [
            'components' => [['snapshot' => json_encode($snapshot), 'updates' => [], 'calls' => []]],
        ]);
    }

    protected function useStaging($app): void
    {
        $this->environment = 'staging';
    }

    protected function disableEverything($app): void
    {
        $app['config']->set('bot-noise.enabled', false);
    }

    protected function disableArrayGuard($app): void
    {
        $app['config']->set('bot-noise.array_guard', false);
    }
}
