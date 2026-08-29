# Implementation Plan: AI-Powered Product Content Enrichment

**Branch**: `feature/001-ai-product-enrichment` | **Date**: 2026-08-29 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/001-ai-product-enrichment/spec.md`

**Note**: This template is filled in by the `/speckit-plan` command; its definition describes the execution workflow.

## Summary

Catalog managers import product spreadsheets (XLSX/CSV) plus ZIP photo archives into eCorithmic's
catalog, in one go or over multiple uploads. Each imported product is automatically researched
against web sources (OpenRouter `perplexity/sonar-pro-search`), has descriptive copy drafted
(OpenRouter `ibm-granite/granite-4.1-8b`) using only established facts, and gets platform-ready
image variants generated (`spatie/laravel-medialibrary`). Every AI-derived value carries a
confidence tier and verbatim, programmatically-verified evidence; nothing reaches export until a
human reviewer approves it field by field. The technical approach adapts the Bagisto/Akeneo-style
EAV schema already used elsewhere in this organization's stores (`bagisto_store`, `db_naivas`,
`naivas_v3`) — trimmed to what this feature actually needs — as the persistent catalog model, and
extends its `product_attribute_values` shape with confidence, source, and review-state columns.

## Technical Context

**Language/Version**: PHP 8.5, Laravel 13.29

**Primary Dependencies**: Inertia v3 + Vue 3, shadcn-vue, Tailwind v4, Laravel Wayfinder, Pest
(existing stack); `rap2hpoutre/fast-excel` for XLSX/CSV parsing; native `ext-zip` (`ZipArchive`)
for archive handling; `spatie/laravel-medialibrary` (^11.23, pulls in `spatie/image` ^3.3
transitively) for image normalization and declarative variant conversions; `laravel/horizon`
(^5.48) managing a Redis-backed queue (`QUEUE_CONNECTION=redis`, `REDIS_CLIENT=phpredis` — Redis
is already reachable per `.env`) with dedicated supervisors per background job type (`research`,
`copy-generation`, `image-variants`); OpenRouter HTTP API
(via Laravel's HTTP Client, `Illuminate\Support\Facades\Http` — never raw Guzzle directly) for
`perplexity/sonar-pro-search` (grounded web
research with citations) and `ibm-granite/granite-4.1-8b` (descriptive copy generation) — chosen
per this being an IBM-hackathon-derived feature; plus `google/gemini-2.5-flash-lite` (also via
OpenRouter/Laravel's HTTP Client) specifically for FR-014's vision-based attribute inference,
since neither available IBM Granite model accepts image input (research.md §8).

**Storage**: MySQL — a trimmed, Bagisto/Akeneo-style EAV schema (`attributes`,
`attribute_options`, `products`, `product_attribute_values` extended with confidence/source/review
columns) plus feature-specific tables (`imports`, `sources`, `review_events`) and
`spatie/laravel-medialibrary`'s `media` table (extended with `review_status`, `quality_flags`,
`is_primary`) for image assets. `attribute_families`/`attribute_groups`/category-hierarchy tables
from the Bagisto reference are intentionally omitted (no admin-configurable attribute UI or
category tree is in scope for this feature).

**Testing**: Pest (feature-first, per Constitution VII's four-section test structure); 100% line
and branch coverage on new/changed code (Constitution V); all external HTTP calls (OpenRouter,
source-page fetches) faked via Laravel's HTTP fake in tests — no live network calls in the suite.

**Target Platform**: Web — Inertia/Vue SPA served by Laravel Herd, single Laravel monolith
(backend + frontend in one repo).

**Project Type**: Web application (Inertia-driven single Laravel project; no separate
frontend/backend repos).

**Performance Goals**: Per SC-004 — the first product in a 30-product import is ready for review
within about one minute of the import starting, and the full 30-product set completes within
about five minutes.

**Constraints**: Per FR-005 — max 5,000 rows per spreadsheet, max 5,000 images per archive, max
20MB per image, max 1GB per archive; ZIP-only archives; only XLSX/CSV spreadsheets. Import cost
cap tracked and enforced in USD (FR-025). Every AI-attributed fact requires a programmatically
verified verbatim quote (FR-009) before it may reach review.

**Scale/Scope**: Catalog-wide, unbounded total size — the reference EAV deployments on the same
database server (`naivas_v3`, `db_naivas`) already run comparable schemas at ~22,000 products, so
the chosen schema shape is known to scale well past this feature's MVP needs.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Notes |
|---|---|---|
| I. Conventional Branch Naming | PASS | Work happens on `feature/001-ai-product-enrichment` (renamed from the spec-kit-default `001-ai-product-enrichment` during `/speckit.analyze` remediation to carry a Conventional-Branch type prefix). |
| II. Conventional Commits | PASS | Applies at commit time during implementation; no design impact. |
| III. Static Analysis Must Always Pass | PASS | `composer run lint:check` (PHPStan, Rector dry-run, Pint, frontend checks) gates every change; no exceptions needed for this design. |
| IV. Zero Regressions | PASS | `composer run test` gates merge; new EAV tables are additive migrations, no existing table is altered destructively. |
| V. 100% Test Coverage | PASS (with design commitment) | All external calls (OpenRouter, source-page fetches) are wrapped behind fakeable HTTP/service boundaries so tests never hit the network — required to reach 100% coverage deterministically. |
| VI. Shadcn-Vue UI, pnpm Only | PASS | Upload wizard, review screen, and export UI are built from shadcn-vue primitives; `pnpm-lock.yaml` confirms pnpm is already the sole JS package manager. |
| VII. Standardized Test Structure | PASS | All new Pest tests use the four-section comment-block structure; enforced during implementation/tasks phase. |
| VIII. Do Things the Laravel Way | PASS | Uses `php artisan make:*` generators, Eloquent, named routes, form requests, `laravel/horizon` for queue management (a framework-idiomatic primitive, dashboard explicitly gated in `HorizonServiceProvider::gate()` rather than left on defaults), and `spatie/laravel-medialibrary` instead of a hand-rolled image pipeline. |

No unjustified violations. One design deviation is recorded under Complexity Tracking below
(EAV schema vs. a flat `products` table) because it is a deliberate departure from the simplest
possible schema, not because it violates a principle.

**Post-Phase 1 re-check**: `research.md` and `data-model.md` introduce exactly the three
dependencies approved above (`rap2hpoutre/fast-excel`, `spatie/laravel-medialibrary`,
`laravel/horizon`) and
no others — AI calls use Laravel's HTTP Client, never the raw Guzzle client directly (Constitution
VIII: use the framework primitive, not what it wraps); archive safety uses native `ext-zip`. The
EAV extension (`origin`, `source_id`, `evidence_quote`, `confidence_tier`, `review_status`,
`conflict_group_id`, version-chain columns) and the per-collection media-asset approach
(`research.md` §4 implementation note) both still route through Eloquent/queues/the media
library's own migration-customization pattern — no custom infrastructure was introduced that
Laravel or an already-approved package doesn't already provide. Gate re-confirmed: **PASS**.

## Project Structure

### Documentation (this feature)

```text
specs/001-ai-product-enrichment/
├── plan.md              # This file (/speckit-plan command output)
├── research.md          # Phase 0 output (/speckit-plan command)
├── data-model.md        # Phase 1 output (/speckit-plan command)
├── quickstart.md        # Phase 1 output (/speckit-plan command)
├── contracts/           # Phase 1 output (/speckit-plan command)
└── tasks.md             # Phase 2 output (/speckit-tasks command - NOT created by /speckit-plan)
```

### Source Code (repository root)

This is a single Laravel application (Inertia + Vue frontend in the same repo) — the existing
project layout is extended in place, no new top-level directories:

```text
app/
├── Http/
│   ├── Controllers/
│   │   └── ProductEnrichment/    # NEW: 12 resourceful controllers, each exposing only standard
│                                  #      CRUD actions (Cruddy by Design — see contracts/):
│                                  #      ImportController (store, show)
│                                  #      ImportConfirmationController (store)
│                                  #      ProductController (show)
│                                  #      ProductAttributeValueController (update)
│                                  #      ProductAssetController (update — toggles is_primary,
│                                  #      un-setting it on sibling assets, FR-002)
│                                  #      ProductAttributeValueApprovalController (store)
│                                  #      ProductAttributeValueRejectionController (store)
│                                  #      ProductAttributeValueRegenerationController (store)
│                                  #      ProductApprovalController (store)
│                                  #      BulkApprovalController (create, store)
│                                  #      ExportController (store, show)
│                                  #      ExportArtifactController (show)
│   └── Requests/
│       └── ProductEnrichment/    # NEW: form requests for upload, review actions, bulk approval
├── Jobs/
│   └── ProductEnrichment/        # NEW: ResearchProduct, AnalyzeProductPhoto, GenerateProductCopy,
│                                  #      GenerateImageVariants, GenerateCatalogExport
├── Models/
│   └── ProductEnrichment/        # NEW: Product, Attribute, AttributeOption, ProductAttributeValue,
│                                  #      Source, Import, ReviewEvent (+ a Media subclass for assets)
└── Services/
    └── ProductEnrichment/        # NEW: OpenRouterClient, QuoteVerifier, SpreadsheetImporter,
                                    #      ZipInspector, ResearchResultsCache, BulkApprovalRuleMatcher

config/
└── product-enrichment.php         # NEW: configurable defaults — unmatched-row rejection threshold
                                    #      (FR-004, default 0.5), research-results cache TTL (FR-026)

database/
├── migrations/                    # NEW: attributes, attribute_options, products, product_attribute_values,
│                                  #      imports, sources, review_events, media-table customization
└── seeders/                       # NEW: seed the fixed attribute set from the spreadsheet template

resources/js/
├── pages/ProductEnrichment/       # NEW: Import wizard, validation report, review screen, export screen
└── components/product-enrichment/ # NEW: shadcn-vue-based field review card, confidence badge, diff view

routes/
└── product-enrichment.php         # NEW: named routes for import/review/export, required by plan.md's
                                    #      Wayfinder-driven frontend action calls

tests/
├── Feature/ProductEnrichment/     # NEW: import, research/generation, vision inference, review,
│                                  #      export, conflict, bulk-approval, primary-asset-change
└── Unit/ProductEnrichment/        # NEW: QuoteVerifier, ZipInspector, confidence-tier derivation
```

**Structure Decision**: Single Laravel monolith, extended in place under a `ProductEnrichment`
sub-namespace/sub-directory within the existing `app/`, `resources/js/`, and `tests/` trees — no
new top-level directories, matching every other feature already in this codebase. Web application
structure (backend + Inertia frontend) applies; there is no separate mobile or CLI target.

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|---------------------------------------|
| EAV schema (`attributes` + `product_attribute_values`) instead of a flat `products` table with fixed columns | eCorithmic positions itself as a PIM (Plytix/Akeneo-like); the organization's other product databases on this same server (`bagisto_store`, `db_naivas`, `naivas_v3`) already use this exact EAV shape at real scale (~22k products), and per-attribute confidence/source/review metadata (this feature's core requirement) attaches far more naturally to a per-value row than to per-column metadata on a flat table | A flat `products` table with one column per field would need a parallel shadow table for confidence/source/review-state per field anyway (to satisfy FR-012/018/019), which is strictly more tables and less consistent with the rest of the organization's data than adopting the EAV shape directly |
