# Laravel Bot Noise

Scanners probe every public Livewire endpoint. Each probe ends up as an exception in your error tracker, and on a small plan they eat your quota. This package keeps that traffic out, in one place instead of per project.

It is auto-discovered, so installing it is all you need to do:

```
composer require nickdekruijk/laravel-bot-noise
```

It needs no Sentry and no Livewire. It works through Laravel's exception handler, so `laravel.log` stays clean too and any error tracker benefits.

## What it does

### Filters exceptions

Only in the environments from the config, by default `production`. A real bug still shows up on staging.

| Exception | Reason |
|---|---|
| `CorruptComponentPayloadException` | a tampered snapshot |
| `CannotUpdateLockedPropertyException` | an attempt to overwrite a locked property |
| `MissingFileUploadsTraitException` | an upload call to a component without uploads |
| `MethodNotFoundException` for `_xNonExist` | a method name scanners try. Other missing methods stay reported on Livewire versions that report them |
| `ErrorException` "array offset on null" from Livewire's `HandleComponents` | a payload without a snapshot |
| `ErrorException` "Input variable nesting level exceeded" | a query string nested too deep |
| `TypeError` "Cannot assign array to property" raised inside Livewire | an array sent to a typed property |

The rules on the content of an exception look at where it came from. The same warning in your own code is a bug and stays reported.

Recent Livewire versions stay silent on some of these by themselves when `app.debug` is off. This package covers the older versions and the exceptions Livewire still reports.

### Refuses arrays for properties that don't take one

A Livewire update that sends an array to a top-level property gets a 403, unless the property holds an array or object right now, or its type accepts one (`array`, `iterable`, `mixed`, `object` or a class). Nested paths like `form.tags` are left alone.

This matters most for untyped properties. `public $license;` has no protection of its own, and the array ends up in your code. A typed scalar like `public string $license` already refuses it with a `TypeError`, that now becomes a 403.

A refusal is logged at `info` level with the component and property, never the value. The guard runs in every environment, so a conflict shows up during development.

If a property really needs to receive an array while it is empty, give it the type `array`, or a default of `[]`, or add it to `array_guard_except`.

### Cleans the query string

Invalid UTF-8 like `?q=%E9` is replaced, in nested values too. Otherwise the bytes can reach `json_encode`, for example in a Livewire snapshot, and the page crashes with a 500. This also runs in every environment, because it prevents an error instead of hiding one.

## Configuration

```
php artisan vendor:publish --tag=bot-noise-config
```

| Key | Default | |
|---|---|---|
| `enabled` | `env('BOT_NOISE_ENABLED', true)` | master switch, use it to see everything again during an incident |
| `environments` | `['production']` | where the exception filter runs |
| `sanitize_query_string` | `true` | |
| `array_guard` | `true` | |
| `array_guard_except` | `[]` | properties as `'component.property'` |

## What it does not do

It blocks nothing at the door and bans no one, and a filter is no protection. Keep Livewire up to date, and look at your access logs when you want to know what scanners are doing.

## Tests

```
composer install
vendor/bin/phpunit
```

## License

MIT, see [LICENSE.md](LICENSE.md).
