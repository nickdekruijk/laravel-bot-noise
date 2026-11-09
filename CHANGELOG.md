# Changelog

All notable changes to `nickdekruijk/laravel-bot-noise` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2026-11-09

### Added

- `PublicPropertyNotFoundException` for `$newFolderName` is not reported. Scanners send this property to every Livewire component, looking for a file manager. Other missing properties stay reported.

## [1.0.0] - 2026-11-04

### Added

- Exception filter for Livewire probe traffic, active in the configured environments.
- Array guard: a Livewire update that sends an array to a property that does not take one gets a 403.
- Middleware that replaces invalid UTF-8 in the query string.
