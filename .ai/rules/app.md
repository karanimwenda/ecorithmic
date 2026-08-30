---
paths:
    - 'app/**'
    - 'app/**/*.php'
---

# App

## All dates are CarbonImmutable

`Date::use(CarbonImmutable::class)` is set in `AppServiceProvider`, so `now()`, `today()`, and all Eloquent date casts return `CarbonImmutable`. Type-hint dates as `CarbonImmutable`, not `Carbon`.

## Activate spatie-laravel-php skill for all PHP/Laravel code

Activate the `spatie-laravel-php` skill before creating or editing any `.php` or `.blade.php` file. Key rules: PSR-12; typed properties and explicit return types including `void`; short nullable syntax `?string`; constructor property promotion when all params can be promoted; one trait per `use` line; happy path last with early returns — never `else`; always curly braces; method chains that break across lines put every `->` on its own line; no docblocks on fully type-hinted methods; route URLs kebab-case, route names and params camelCase; tuple notation for route definitions `[Controller::class, 'method']`; plural controller class names (`PostsController`); validation rules in array notation; enum cases and class constants in PascalCase; `config()` everywhere, `env()` only inside config files.
