<!--
Sync Impact Report
==================
Version change: 1.4.0 → 1.5.0
Rationale for bump: MINOR — new principle added (XI. Type Safety: Laravel ↔ Inertia ↔ TypeScript).
  Quality Gates and Tooling Conventions sections extended to cover the type-generation pipeline.

Modified principles: none

Added principles:
  - XI. Type Safety: Laravel ↔ Inertia ↔ TypeScript

Added sections: none

Modified sections:
  - Quality Gates: added typescript:transform gate
  - Tooling Conventions: added type-generation pipeline entry

Removed sections: none

Deferred / follow-up TODOs: none.
-->

# Ecorithmic Constitution

## Core Principles

### I. Conventional Branch Naming
All branches MUST be named according to the Conventional Branch specification
(https://conventionalbranch.org): a type prefix (e.g. `feature/`, `bugfix/`, `hotfix/`,
`release/`, `chore/`) followed by a short, slash-separated description. Branches that do not
match an approved type MUST be renamed before a PR is opened.

Rationale: predictable branch names make intent, review routing, and changelog generation
mechanical instead of guessed.

### II. Conventional Commits
Every commit message MUST follow Conventional Commits v1.0.0
(https://www.conventionalcommits.org/en/v1.0.0/): `type(optional-scope): description`, with
`BREAKING CHANGE:` called out explicitly when applicable. Squash-merges MUST preserve a
conventional summary line as the resulting commit message.

Rationale: commit history is the source of truth for changelogs and semantic-versioning
decisions; free-text commits break that automation.

### III. Static Analysis Must Always Pass (NON-NEGOTIABLE)
`composer run lint:check` MUST pass with zero errors before any change is merged. This command
covers PHPStan, Rector (dry-run), Pint formatting check, and the frontend `check`/`types:check`
scripts. A failing `lint:check` blocks merge — no exceptions.

Rationale: static analysis catches an entire class of defects before a human reviewer has to.

### IV. Zero Regressions
`composer run test` MUST pass in full before any change is merged. A change that breaks an
existing passing test is not acceptable to merge as-is — either the change is wrong, or the
test's expectation is being deliberately and explicitly updated as part of the same PR.

Rationale: a green suite is the only cheap, repeatable evidence that existing behavior still
works.

### V. 100% Test Coverage (NON-NEGOTIABLE)
`php artisan test --parallel --coverage --min=100` MUST pass. Every line and branch introduced
or modified MUST be covered by a test as part of the same change; coverage is not backfilled
later.

Rationale: coverage thresholds only hold discipline if enforced at the same commit that would
otherwise drop them, not retroactively.

### VI. Shadcn-Vue UI & MCP-First, pnpm Only
All UI MUST be built from or composed with shadcn-vue (https://shadcn-vue.com) primitives.
Raw Tailwind CSS utilities MUST NOT be used to hand-roll a component that shadcn-vue already
provides or can reasonably provide. Before writing any new UI component or layout element,
the developer (or agent) MUST:

1. Activate the `shadcn-vue` skill to load its instructions.
2. Use the shadcn-vue MCP tools (`search_items_in_registries`, `view_items_in_registries`,
   `get_item_examples_from_registries`) to check whether the required component already
   exists in the registry.
3. Only fall back to a custom Tailwind-only implementation when a shadcn-vue primitive
   genuinely does not cover the use-case **and** that justification is explicitly stated
   in the PR description or code comment.

No competing component library may be introduced alongside shadcn-vue. All JavaScript
dependency management MUST use `pnpm`; `npm`/`yarn` lockfiles or commands MUST NOT be
introduced or committed.

Rationale: one component system and one package manager eliminate an entire category of
"works on my machine" issues and inconsistent-UI drift. Requiring a registry check before
hand-rolling prevents duplicated, subtly inconsistent copies of components that already
exist in the design system.

### VII. Standardized Test Structure
Every test MUST be organized into four labeled sections, in this exact order and comment-block
format (a section may be left empty if genuinely not applicable, but the header stays):

```
// ===========================================================================
// Initialize data
// ===========================================================================

// ===========================================================================
// Setup the environment
// ===========================================================================

// ===========================================================================
// Run the block of code in question
// ===========================================================================

// ===========================================================================
// Make assertions
// ===========================================================================
```

Rationale: a fixed shape makes tests fast to scan and review, and makes "what is this test
actually exercising" a non-question.

### VIII. Do Things the Laravel Way
Code MUST follow Laravel's own conventions rather than inventing parallel patterns: use
`php artisan make:*` generators instead of hand-rolled files, Eloquent over raw queries where
reasonable, named routes with `route()` over hardcoded URLs, form requests for validation, and
existing framework primitives (queues, cache, notifications, policies) before introducing custom
infrastructure. **Laravel Boost MUST be used to guide this principle**: use its `search-docs`
tool to confirm version-specific APIs before relying on them, `application-info` to check
installed package versions, and `database-schema`/`database-query` instead of ad hoc raw SQL or
guesswork, so decisions are grounded in this project's actual Laravel/package versions rather
than assumed or outdated conventions.

Rationale: fighting the framework produces code that's harder for any Laravel developer —
human or AI — to predict, maintain, or upgrade; Boost exists specifically to keep guidance
accurate to the versions actually installed here.

### IX. Cruddy by Design (Controller Shape)
Every controller MUST expose only the seven standard RESTful actions: `index`, `show`,
`create`, `store`, `edit`, `update`, `destroy`. Custom, non-resourceful action names MUST
NOT be added to any controller.

**When you feel the urge to add a custom action** (`subscribe`, `publish`, `approve`,
`archive`, `reorder`, etc.), stop and ask: *"What resource is being created, updated, or
destroyed here?"* Then model that as its own controller using only the seven standard actions.

**Decision heuristic** — ask: *"What do I have now that I didn't have before?"*
- A new record or relationship → `store` on a new (possibly new) resource controller.
- A record going away → `destroy` on its controller.
- A state change (`published`, `archived`, `approved`) → that state is a resource; create a
  dedicated controller for it (e.g. `PublishedPostController@store` / `@destroy`).

**Invokable controllers**: when a controller genuinely needs only one of the seven actions,
make it invokable (`__invoke`) and route to the class directly. Do not name a single method
on a class if that name would be arbitrarily chosen. Do not make a multi-action controller
invokable.

**Rules in summary**:
- One controller = one resource = one set of RESTful operations.
- Never add a method that is not one of the seven standard actions.
- More controllers are fine — prefer many small, single-purpose controllers over fewer
  large, special-cased ones.
- State changes, pivot/join records, and relationship toggles are resources — name them and
  give them their own controller.
- Controllers orchestrate only; real work is delegated to models, Eloquent, or Action classes.

Rationale (Adam Wathan, "Cruddy by Design", Laracon 2017): sticking to CRUD forces you to
name and surface real domain concepts (subscriptions, publications, memberships) instead of
hiding them inside verbs. Every controller then looks and behaves the same way — any developer
can jump in and know what to expect, controllers stay thin and testable, and domain concepts
are first-class citizens.

### X. Spatie Laravel & PHP Coding Standards
All PHP and Laravel code MUST comply with the Spatie Laravel & PHP guidelines
(https://spatie.be/guidelines). The `spatie-laravel-php` skill MUST be activated whenever
any `.php` or `.blade.php` file is created or modified.

The non-negotiable rules from this standard are:

**PHP style:**
- Follow PSR-1, PSR-2, and PSR-12.
- Use typed properties and explicit return types (including `void`) on all methods. Use the
  short nullable syntax: `?string`, not `string|null`.
- Use constructor property promotion when all constructor parameters can be promoted.
- Do not use `final` or `readonly` by default.
- Declare one trait per `use` statement (one trait per line).

**Control flow:**
- Happy path LAST: handle error/guard conditions first with early returns; the success path
  is the final block.
- NEVER use `else` when an early return is possible.
- ALWAYS use curly braces for control structures, even single-statement bodies.

**Method chaining:**
- Once a method chain breaks across lines, EVERY subsequent `->` MUST be on its own line.
  Never mix single-line and multi-line chaining in the same expression.

**Docblocks:**
- Do NOT add docblocks to fully type-hinted methods unless a description adds context
  beyond what the signature already communicates.
- Never use fully qualified class names in docblocks; always import and use the short name.
- Document iterables with generics: `/** @return Collection<int, User> */`.
- Use array shape notation for fixed-key arrays: `/** @return array{first: Foo, second: Bar} */`.

**Laravel conventions:**
- Route URLs MUST be kebab-case (`/open-source`).
- Route names MUST be camelCase (`->name('openSource')`).
- Route parameters MUST be camelCase (`{userId}`).
- Route definitions MUST use tuple notation: `[Controller::class, 'method']`.
- Controller class names MUST use the plural resource name + `Controller` suffix
  (`PostsController`, not `PostController`).
- Use `config()` helper everywhere; `env()` is only permitted inside `config/*.php` files.
- Service third-party configs MUST go into `config/services.php`, not new config files.
- Use `__()` for all translation strings; never use `@lang` in Blade.
- Validation rules MUST use array notation: `['required', 'email']`, not pipe strings.
- Enum cases and class constants MUST use PascalCase (`case Published`, `const SessionToken`).

**Naming (quick reference):**
- Config files: kebab-case (`pdf-generator.php`); config keys: snake_case.
- Artisan commands: kebab-case (`delete-old-records`).
- Jobs: action-based (`CreateUser`); Events: tense-based (`UserRegistered`);
  Listeners: `SendInvitationMailListener`; Mailables: `AccountActivatedMail`.
- API resource URLs: plural and kebab-case (`/error-occurrences`).

Rationale: a single, well-known style guide (Spatie's) applied universally means any
developer — or agent — can read, write, and review PHP/Laravel code with zero style
ambiguity. It also aligns tightly with Principle VIII (Do Things the Laravel Way), since
Spatie's guidelines are built on top of Laravel conventions.

### XI. Type Safety: Laravel ↔ Inertia ↔ TypeScript
Every Inertia page MUST receive a typed data object; no ad-hoc arrays or manually
hand-written frontend types are permitted for page props.

**Backend — `spatie/laravel-data` Data classes:**
- Define one `Spatie\LaravelData\Data` subclass under `app/Data/` per Inertia view.
  Annotate it `#[TypeScript]` so the TypeScript transformer picks it up.
- Data classes are the **sole** mechanism for shaping response payloads sent to Inertia views.
  Do NOT create separate Laravel API Resource classes (`JsonResource`) for this purpose;
  Data classes replace them. Form Requests remain a separate concern and MUST still be used
  for all input validation.
- Controllers MUST pass a single typed Data object to `Inertia::render()`, never a raw array:
  ```php
  return Inertia::render('Products/Index', ProductsData::from($products));
  ```

**Deferred props:**
- Deferred props MUST be typed as `DeferProp|X` in the Data class.
- `Inertia\DeferProp::class` MUST be registered in `default_type_replacements`
  inside `config/typescript-transformer.php` so the transformer emits the correct TS type.

**Shared data:**
- Shared props exposed via `HandleInertiaRequests::share()` MUST go through a dedicated
  Data class (e.g. `SharedData`). The generated `SharedData` TypeScript type MUST be merged
  into the frontend page props type so shared fields are statically known on every page.

**TypeScript generation — NEVER hand-author TS types:**
- TypeScript types for all Data classes and Enums MUST be generated exclusively via
  `php artisan typescript:transform` (aliased as `composer run transform-types`).
- Output MUST land in `resources/js/types/generated.d.ts` (or equivalent single generated file).
- Hand-authored types that duplicate or shadow generated types are a constitution violation.

**Automation requirements:**
- In development, type generation MUST run automatically when `app/Data/` or `app/Enums/`
  changes. Use `vite-plugin-watch` (already installed) or an equivalent file-watcher.
- In CI, `php artisan typescript:transform` MUST run before `pnpm run build`. A stale or
  missing generated file MUST fail the build — it MUST NOT ship silently.

Rationale: renaming or adding a property on a backend Data object surfaces immediately as a
compile-time error on both the PHP side (named-argument mismatch caught by PHPStan) and the
TypeScript side (type mismatch caught by `vue-tsc`). This eliminates an entire class of
runtime bugs where backend and frontend drift out of sync silently.

## Quality Gates

The following commands are the canonical, non-negotiable verification gates. They MUST be run
— and pass — before a PR is opened for review, and are re-verified in CI before merge:

- `composer run lint:check` — static analysis (Principle III)
- `composer run test` — regression check (Principle IV)
- `php artisan test --parallel --coverage --min=100` — coverage gate (Principle V)
- `php artisan typescript:transform` — type generation MUST run before `pnpm run build`
  (Principle XI); a missing or stale `generated.d.ts` fails the build

`composer run lint` (without `:check`) may be used locally to auto-fix formatting issues before
running the `:check` variant.

## Tooling Conventions

- **Package manager**: `pnpm` exclusively for all JavaScript/TypeScript dependency work
  (install, add, remove, scripts). Any `package-lock.json` or `yarn.lock` found in the repo is
  stale and MUST be removed the next time it is touched.
- **UI components**: `shadcn-vue` is the only sanctioned component source. Before adding a
  component, run the shadcn-vue MCP registry check (Principle VI). New components are added
  via the shadcn-vue CLI/pattern; hand-rolled duplicates of existing primitives are not
  permitted.
- **PHP/Laravel code style**: the `spatie-laravel-php` skill is the active style authority
  for all `.php` and `.blade.php` files (Principle X). It MUST be activated before writing
  or reviewing PHP code.
- **TypeScript type generation**: `spatie/laravel-data` + `spatie/laravel-typescript-transformer`
  own all frontend type definitions (Principle XI). Run `php artisan typescript:transform`
  (aliased `composer run transform-types`) to regenerate `resources/js/types/generated.d.ts`.
  `vite-plugin-watch` MUST be configured to trigger this automatically in dev when
  `app/Data/` or `app/Enums/` changes.

## Governance

- **Amendment procedure**: propose the change, state the rationale, bump the version per the
  policy below, update `Last Amended`, and record the change in a Sync Impact Report prepended
  to this file.
- **Versioning policy**: MAJOR = removal or backward-incompatible redefinition of a principle;
  MINOR = a new principle or materially expanded guidance; PATCH = wording, typo, or
  clarification only.
- **Compliance review**: every PR MUST demonstrate all three Quality Gates passing (lint:check,
  test, coverage `--min=100`) and MUST use conventional branch and commit naming before it is
  eligible for merge. Any exception requires an explicit, recorded justification in the PR
  description — silent exceptions are a constitution violation.

**Version**: 1.5.0 | **Ratified**: 2026-08-29 | **Last Amended**: 2025-07-17
