<?php

namespace NickDeKruijk\BotNoise;

use Illuminate\Support\Facades\Log;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

/**
 * Refuses an array sent to a top-level Livewire property that has no use for one.
 *
 * It looks at the property itself instead of guessing from its name. A property
 * passes when it holds an array or object right now, or when its declared type
 * accepts one. What is left is a scalar or an untyped empty property, and a
 * bot's array would otherwise reach application code there.
 */
final class ArrayGuard
{
    private const ARRAY_TYPES = ['array', 'iterable', 'mixed', 'object'];

    /**
     * Runs on Livewire's update event, before the value is assigned.
     */
    public static function check(object $component, string $path, mixed $value): void
    {
        if (! is_array($value) || str_contains($path, '.') || ! property_exists($component, $path)) {
            return;
        }

        $property = new ReflectionProperty($component, $path);

        if (! $property->isPublic() || $property->isStatic() || self::acceptsArray($property, $component)) {
            return;
        }

        $name = method_exists($component, 'getName') ? $component->getName() : $component::class;

        if (in_array($name.'.'.$path, (array) config('bot-noise.array_guard_except', []), true)) {
            return;
        }

        Log::info('[bot-noise] Refused array for Livewire property', [
            'component' => $name,
            'property' => $path,
        ]);

        abort(403);
    }

    private static function acceptsArray(ReflectionProperty $property, object $component): bool
    {
        $type = $property->getType();

        if ($type !== null && self::typeAcceptsArray($type)) {
            return true;
        }

        if (! $property->isInitialized($component)) {
            return false;
        }

        $current = $property->getValue($component);

        return is_array($current) || is_object($current);
    }

    private static function typeAcceptsArray(ReflectionType $type): bool
    {
        if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            foreach ($type->getTypes() as $part) {
                if (self::typeAcceptsArray($part)) {
                    return true;
                }
            }

            return false;
        }

        // A class can be a Collection, a Form or a Wireable, Livewire hydrates those itself.
        return $type instanceof ReflectionNamedType
            && (! $type->isBuiltin() || in_array($type->getName(), self::ARRAY_TYPES, true));
    }
}
