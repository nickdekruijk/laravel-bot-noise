<?php

namespace NickDeKruijk\BotNoise;

use ErrorException;
use Throwable;
use TypeError;

/**
 * Decides which exceptions are bot noise. The Livewire classes are strings, so
 * the package also loads on a project without Livewire.
 */
final class NoiseFilter
{
    /**
     * Thrown for a tampered payload, never by a working page.
     *
     * @var array<int, class-string<Throwable>>
     */
    public const CLASSES = [
        'Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException',
        'Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException',
        'Livewire\Features\SupportFileUploads\MissingFileUploadsTraitException',
    ];

    private const METHOD_NOT_FOUND = 'Livewire\Exceptions\MethodNotFoundException';

    /**
     * A method name scanners call to see how the endpoint answers. Any other
     * missing method can be a real typo in a template and stays reported.
     */
    private const PROBE_METHOD = '[_xNonExist]';

    private const PROPERTY_NOT_FOUND = 'Livewire\Exceptions\PublicPropertyNotFoundException';

    /**
     * A property name scanners send to every component, looking for a file
     * manager. Any other missing property can be a real typo and stays reported.
     */
    private const PROBE_PROPERTY = '[$newFolderName]';

    private const HANDLE_COMPONENTS = 'livewire/livewire/src/Mechanisms/HandleComponents/HandleComponents.php';

    public static function isNoise(Throwable $exception): bool
    {
        foreach (self::CLASSES as $class) {
            if ($exception instanceof $class) {
                return true;
            }
        }

        return self::isNoiseByContent($exception);
    }

    /**
     * The noise that has no exception class of its own.
     */
    public static function isNoiseByContent(Throwable $exception): bool
    {
        return self::isProbeCall($exception)
            || self::isProbeProperty($exception)
            || self::isBrokenPayload($exception)
            || self::isNestedInput($exception)
            || self::isArrayAssignedToTypedProperty($exception);
    }

    private static function isProbeCall(Throwable $exception): bool
    {
        $class = self::METHOD_NOT_FOUND;

        return $exception instanceof $class
            && str_contains($exception->getMessage(), self::PROBE_METHOD);
    }

    private static function isProbeProperty(Throwable $exception): bool
    {
        $class = self::PROPERTY_NOT_FOUND;

        return $exception instanceof $class
            && str_contains($exception->getMessage(), self::PROBE_PROPERTY);
    }

    /**
     * A payload without a snapshot or memo. Only Livewire's own file counts:
     * the same warning in application code is a real bug.
     */
    private static function isBrokenPayload(Throwable $exception): bool
    {
        return $exception instanceof ErrorException
            && str_contains($exception->getMessage(), 'Trying to access array offset on null')
            && str_ends_with(str_replace('\\', '/', $exception->getFile()), self::HANDLE_COMPONENTS);
    }

    /**
     * PHP's warning for a URL like ?a[b][c][d]... that goes more than 64 levels deep.
     */
    private static function isNestedInput(Throwable $exception): bool
    {
        return $exception instanceof ErrorException
            && str_contains($exception->getMessage(), 'Input variable nesting level exceeded');
    }

    /**
     * Only while Livewire assigns a payload value, the same error in application
     * code is a real bug.
     */
    private static function isArrayAssignedToTypedProperty(Throwable $exception): bool
    {
        if (! $exception instanceof TypeError || ! str_contains($exception->getMessage(), 'Cannot assign array to property')) {
            return false;
        }

        foreach ($exception->getTrace() as $frame) {
            if (str_starts_with($frame['class'] ?? '', 'Livewire\\')
                || str_contains(str_replace('\\', '/', $frame['file'] ?? ''), '/vendor/livewire/livewire/')) {
                return true;
            }
        }

        return false;
    }
}
