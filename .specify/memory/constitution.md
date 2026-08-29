<!--
Sync Impact Report
==================
Version change: 1.0.0 → 1.1.0
Rationale for bump: MINOR — new principle added (Do Things the Laravel Way), no existing
  principle redefined or removed.

Modified principles: none

Added principles:
  - VIII. Do Things the Laravel Way

Added sections: none (Core Principles list extended only)

Removed sections: none

Deferred / follow-up TODOs: none.

Templates requiring follow-up: none reviewed/modified by this command (out of scope per
  the constitution command's scope guard).
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

### VI. Shadcn-Vue UI, pnpm Only
All UI components MUST be built from or composed with shadcn-vue (https://shadcn-vue.com)
primitives — no competing component library may be introduced. All JavaScript dependency
management MUST use `pnpm`; `npm`/`yarn` lockfiles or commands MUST NOT be introduced or
committed.

Rationale: one component system and one package manager eliminate an entire category of
"works on my machine" issues and inconsistent-UI drift.

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

## Quality Gates

The following commands are the canonical, non-negotiable verification gates. They MUST be run
— and pass — before a PR is opened for review, and are re-verified in CI before merge:

- `composer run lint:check` — static analysis (Principle III)
- `composer run test` — regression check (Principle IV)
- `php artisan test --parallel --coverage --min=100` — coverage gate (Principle V)

`composer run lint` (without `:check`) may be used locally to auto-fix formatting issues before
running the `:check` variant.

## Tooling Conventions

- **Package manager**: `pnpm` exclusively for all JavaScript/TypeScript dependency work
  (install, add, remove, scripts). Any `package-lock.json` or `yarn.lock` found in the repo is
  stale and MUST be removed the next time it is touched.
- **UI components**: `shadcn-vue` is the only sanctioned component source; new components are
  added via its CLI/pattern, not hand-rolled duplicates of what it already provides.

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

**Version**: 1.1.0 | **Ratified**: 2026-08-29 | **Last Amended**: 2026-08-29
