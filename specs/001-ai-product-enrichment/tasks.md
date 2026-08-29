# Tasks: AI-Powered Product Content Enrichment

**Input**: Design documents from `/specs/001-ai-product-enrichment/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md (all present)

**Revision note**: This revision incorporates the remediation from `/speckit.analyze` (2026-08-29):
a vision-inference job (G1, FR-014), a distinct research-results cache (G2, FR-026), a
primary-asset-change controller (G3, FR-002), a configurable cost-cap input (G4, FR-025), a
`ProductAsset` factory (G5), config-driven thresholds (U3, FR-004), Wayfinder generation/usage
notes (U2), and expanded test coverage for source-identity verification (U1), the SC-003
end-to-end discard behavior (U4), and the FR-017-vs-SC-004 timing distinction (I1). A second
`/speckit.analyze` pass then added: `UpdateProductAssetRequest` (G6), automatic primary-photo
assignment at import time (G7), a corrected Services list, removal of the never-populated
`Actions/` directory, and a medialibrary config-publish step. All task IDs below are renumbered
from the original revision (101 tasks total).

**Tests**: Included and REQUIRED — Constitution IV (Zero Regressions), V (100% Test Coverage,
NON-NEGOTIABLE), and VII (Standardized four-section Test Structure) make testing non-optional for
this project. Every Pest test file created below MUST use Constitution VII's four-section
comment-block structure (Initialize data / Setup the environment / Run the block of code in
question / Make assertions).

**Organization**: Tasks are grouped by user story (priority order: US1, US2, US3, US7 are P1;
US4, US5, US6 are P2) to enable independent implementation and testing of each story, per
`plan.md`'s Cruddy-by-Design controller list and `data-model.md`'s trimmed EAV schema.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: Which user story this task belongs to (US1–US7)
- Every task includes an exact file path

## Path Conventions

Single Laravel monolith (Inertia + Vue in the same repo), extended in place per `plan.md`'s
Project Structure:
- `app/{Models,Http/Controllers,Http/Requests,Jobs,Services}/ProductEnrichment/`
- `config/product-enrichment.php`
- `database/{migrations,factories,seeders}/`
- `resources/js/{pages,components}/product-enrichment/` (Vue components) and
  `resources/js/pages/ProductEnrichment/` (Inertia pages)
- `routes/product-enrichment.php`
- `tests/{Feature,Unit}/ProductEnrichment/`

**Wayfinder note (applies throughout)**: this project uses `laravel/wayfinder`. Every task that
registers a route MUST be followed by `php artisan wayfinder:generate` before the Vue pages/
components that call that route are written, and every Vue page/component task that calls a
Laravel route MUST import the generated action from `@/actions/...`/`@/routes/...` rather than
hardcoding a URL string.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Install and configure the three new dependencies approved in `research.md`, and the
feature's configurable defaults

- [ ] T001 Install new Composer dependencies: `composer require rap2hpoutre/fast-excel spatie/laravel-medialibrary laravel/horizon`
- [ ] T002 [P] Publish `spatie/laravel-medialibrary`'s migration and config: `php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"` and `--tag="media-library-config"` (so T023 has `config/media-library.php` to edit)
- [ ] T003 [P] Install Horizon: `php artisan horizon:install` (publishes `config/horizon.php` and dashboard assets)
- [ ] T004 Set `QUEUE_CONNECTION=redis` in `.env` and `.env.example` (Redis already reachable, `REDIS_CLIENT=phpredis`)
- [ ] T005 [P] Create `routes/product-enrichment.php` (route group shell under `auth`+`verified` middleware) and add `require __DIR__.'/product-enrichment.php';` to `routes/web.php`
- [ ] T006 [P] Create `config/product-enrichment.php` with `unmatched_row_threshold` (default `0.5`, FR-004) and `research_cache_ttl_days` (default `7`, FR-026) — both env-overridable, so neither is a hardcoded literal in application code
- [ ] T007 Run `composer run lint:check` to confirm a clean baseline before feature work begins

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core schema, models, and shared services that every user story depends on

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

Migrations (sequential — later ones have foreign keys into earlier ones, so file-creation order
matters for the timestamp-based execution order):

- [ ] T008 Create migration `create_attributes_table` (`code` unique, `name`, `type` enum, `is_required`, `is_unique`, `is_ai_enrichable`, `validation` nullable, `position`, timestamps) in `database/migrations/`
- [ ] T009 Create migration `create_attribute_options_table` (FK `attribute_id`, `admin_name`, `sort_order`, timestamps) in `database/migrations/`
- [ ] T010 Create migration `create_imports_table` (all columns per `data-model.md` § Import: counts, `estimated_cost_usd`, `processing_cost_usd`, `cost_cap_usd`, `status` enum, `validation_report` json, `confirmed_by`/`confirmed_at`) in `database/migrations/`
- [ ] T011 Create migration `create_products_table` (`sku` unique, nullable FKs `first_import_id`/`last_import_id` → `imports.id`, timestamps) in `database/migrations/`
- [ ] T012 Create migration `create_sources_table` (FK `product_id`, `url`, `domain`, `trust_classification` enum, `is_usable`, `fetched_at`, timestamps) in `database/migrations/`
- [ ] T013 Create migration `create_product_attribute_values_table` (FKs `product_id`/`attribute_id`/nullable `source_id`, all value columns, `origin`/`confidence_tier`/`review_status` enums, `conflict_group_id` uuid nullable, `is_current`, self-referencing `previous_value_id`, `regeneration_feedback`, `approved_by`/`approved_at`, timestamps) in `database/migrations/`
- [ ] T014 Create migration `create_review_events_table` (FK `product_id`, nullable FK `product_attribute_value_id`, `action` enum, FK `actor_id`, `feedback_text` nullable, `bulk_rule` json nullable, `created_at`) in `database/migrations/`
- [ ] T015 Create migration adding `review_status` enum, `quality_flags` json nullable, `is_primary` boolean (default false) columns to the `media` table in `database/migrations/`

Models, factories (parallel — distinct files):

- [ ] T016 [P] Create `Attribute` model with `attributeOptions()`/`productAttributeValues()` relations in `app/Models/ProductEnrichment/Attribute.php`
- [ ] T017 [P] Create `AttributeOption` model with `attribute()` relation in `app/Models/ProductEnrichment/AttributeOption.php`
- [ ] T018 [P] Create `Import` model with `products()` relation and cost-accumulation helper (used by T058) in `app/Models/ProductEnrichment/Import.php`
- [ ] T019 [P] Create `Product` model (`HasMedia` trait; `registerMediaCollections()` defining `original`/`primary-ecommerce`/`social-square`; relations to `ProductAttributeValue`, `Source`, `ReviewEvent`) in `app/Models/ProductEnrichment/Product.php`
- [ ] T020 [P] Create `Source` model with `product()` relation in `app/Models/ProductEnrichment/Source.php`
- [ ] T021 [P] Create `ProductAttributeValue` model (casts for `json_value`, `origin`/`confidence_tier`/`review_status` enums, `previousValue()` self-relation, `source()` relation) in `app/Models/ProductEnrichment/ProductAttributeValue.php`
- [ ] T022 [P] Create `ReviewEvent` model in `app/Models/ProductEnrichment/ReviewEvent.php`
- [ ] T023 Create a custom `ProductAsset` model extending `Spatie\MediaLibrary\MediaCollections\Models\Media` (with the `HasFactory` trait, so T031's factory resolves) exposing `quality_flags`/`review_status`/`is_primary` casts, and set `media-library.media_model` to it in `config/media-library.php`
- [ ] T024 [P] Create `AttributeFactory` in `database/factories/ProductEnrichment/AttributeFactory.php`
- [ ] T025 [P] Create `AttributeOptionFactory` in `database/factories/ProductEnrichment/AttributeOptionFactory.php`
- [ ] T026 [P] Create `ImportFactory` in `database/factories/ProductEnrichment/ImportFactory.php`
- [ ] T027 [P] Create `ProductFactory` in `database/factories/ProductEnrichment/ProductFactory.php`
- [ ] T028 [P] Create `SourceFactory` in `database/factories/ProductEnrichment/SourceFactory.php`
- [ ] T029 [P] Create `ProductAttributeValueFactory` in `database/factories/ProductEnrichment/ProductAttributeValueFactory.php`
- [ ] T030 [P] Create `ReviewEventFactory` in `database/factories/ProductEnrichment/ReviewEventFactory.php`
- [ ] T031 [P] Create `ProductAssetFactory` in `database/factories/ProductEnrichment/ProductAssetFactory.php` (fixture media rows for tests, e.g. T049/T060, without needing the full upload pipeline)

Seed data and shared services:

- [ ] T032 Create `AttributeSeeder` seeding the fixed attribute set (`sku`, `name`, `brand`, `gtin`, `mpn`, `short_description`, `description`, `bullet_points`, `seo_title`, `seo_summary`, `price`, `color`, `category`) plus `AttributeOption` rows for `color`/`category`/`brand`, registered in `database/seeders/DatabaseSeeder.php` — file: `database/seeders/AttributeSeeder.php`
- [ ] T033 [P] Create `OpenRouterClient` service (Laravel HTTP Client — `Illuminate\Support\Facades\Http`, never raw Guzzle — wrapping OpenRouter's `/chat/completions`; computes USD cost from each response's `usage` per `research.md` §1 pricing table; supports both text-only and image-attachment requests for T055's vision call) in `app/Services/ProductEnrichment/OpenRouterClient.php`
- [ ] T034 [P] Define the `research`, `copy-generation`, and `image-variants` Horizon supervisors in `config/horizon.php`
- [ ] T035 [P] Define the `viewHorizon` gate (restricted to authorized users, per `research.md` §3 dashboard-access note) in `app/Providers/HorizonServiceProvider.php`

**Checkpoint**: Foundation ready — user story implementation can now begin

---

## Phase 3: User Story 1 - Import Products Into the Catalog (Priority: P1) 🎯 MVP step 1

**Goal**: Upload a spreadsheet + ZIP photo archive, see a validation report before anything is
committed, confirm to create/update products.

**Independent Test**: Upload a spreadsheet + photo archive and confirm: (a) a validation report
is shown before anything is committed, (b) new products appear after confirmation, (c)
re-uploading a spreadsheet referencing an already-catalogued SKU updates that product.

### Tests for User Story 1

- [ ] T036 [US1] Write feature tests covering all 5 acceptance scenarios (full match creates products; partial-unmatched rows shown in validation report before creation; >50% unmatched auto-rejects per FR-004, reading the threshold from `config/product-enrichment.php` rather than a hardcoded value; re-uploaded existing SKU updates rather than duplicates per FR-006; unsafe archive content rejected without extraction per FR-005) in `tests/Feature/ProductEnrichment/ImportTest.php`
- [ ] T037 [P] [US1] Write unit tests for `ZipInspector` safety checks (path-traversal entries, encrypted archives, decompression-bomb ratio) in `tests/Unit/ProductEnrichment/ZipInspectorTest.php`
- [ ] T038 [P] [US1] Write unit tests for `SpreadsheetImporter` parsing and row/column validation against the seeded `Attribute` set in `tests/Unit/ProductEnrichment/SpreadsheetImporterTest.php`

### Implementation for User Story 1

- [ ] T039 [P] [US1] Create `ZipInspector` service (native `ZipArchive` safety checks per `research.md` §6: encryption, path traversal, decompression bomb) in `app/Services/ProductEnrichment/ZipInspector.php`
- [ ] T040 [P] [US1] Create `SpreadsheetImporter` service (`rap2hpoutre/fast-excel` wrapper; row/column validation against the seeded `Attribute` template; SKU-to-photo-filename exact-stem matching per FR-002) in `app/Services/ProductEnrichment/SpreadsheetImporter.php`
- [ ] T041 [P] [US1] Create `StoreImportRequest` form request (`spreadsheet`: required, mimes:xlsx,csv; `archive`: required, mimes:zip, max 1,048,576 KB per FR-005; `cost_cap_usd`: nullable, numeric, min:0 per FR-025 — the manager's configured spend cap, omittable for an uncapped import) in `app/Http/Requests/ProductEnrichment/StoreImportRequest.php`
- [ ] T042 [US1] Implement `ImportController@store` (builds `validation_report` via T039/T040, FR-004 auto-rejection using `config('product-enrichment.unmatched_row_threshold')`, `estimated_cost_usd`, persists `cost_cap_usd` from the request) and `@show` (validation-report props) in `app/Http/Controllers/ProductEnrichment/ImportController.php`
- [ ] T043 [US1] Implement `ImportConfirmationController@store` (creates/updates `Product` + seed `ProductAttributeValue` rows with `origin=manager`/`confidence_tier=high`; FR-006 update-on-existing-SKU; sets `is_primary=true` on the matched photo's `original` media item when it's the first/only such asset for that product per FR-002; dispatches one `ResearchProduct` and one `AnalyzeProductPhoto` job per product onto the `research` queue) in `app/Http/Controllers/ProductEnrichment/ImportConfirmationController.php`
- [ ] T044 [P] [US1] Register `POST /imports`, `GET /imports/{import}`, `POST /imports/{import}/confirmation` in `routes/product-enrichment.php`, then run `php artisan wayfinder:generate`
- [ ] T045 [P] [US1] Create the upload Inertia page (shadcn-vue form, spreadsheet + archive file inputs, optional `cost_cap_usd` input; calls the generated Wayfinder action, not a hardcoded URL) in `resources/js/pages/ProductEnrichment/Imports/Upload.vue`
- [ ] T046 [P] [US1] Create the validation-report Inertia page (counts display, confirm action via the generated Wayfinder action, Inertia v3 `poll` for post-confirmation progress) in `resources/js/pages/ProductEnrichment/Imports/ValidationReport.vue`

**Checkpoint**: User Story 1 fully functional and independently testable

---

## Phase 4: User Story 2 - Automatic Grounded Content & Image Generation (Priority: P1) 🎯 MVP step 2

**Goal**: Every imported product is automatically researched (text and vision), given descriptive
copy, and given platform-ready image variants in the background, without manual steps.

**Independent Test**: Import a product with fixture-known facts and confirm imagery variants and
grounded field values with confidence tiers and evidence appear without any manual step.

### Tests for User Story 2

- [ ] T047 [US2] Write feature tests covering all 5 acceptance scenarios (grounded fields carry quote+confidence; unfindable-source product still completes, ungrounded, capped at lowest tier, using photo-and-name-only processing via `AnalyzeProductPhoto` per FR-010; import-level processing progress/timing — FR-017's "visible within a few seconds of it changing" progress-visibility check tested distinctly from SC-004's ~1min/~5min overall-completion target; one product's unrecoverable error never blocks others; image variants pad rather than crop and quality concerns are flagged, never silently fixed or blocked), plus: a fact whose quote fails verification is asserted to never appear as a `ProductAttributeValue` row at all (SC-003's end-to-end discard behavior, not just the isolated `QuoteVerifier` unit check), and a source page that no longer matches the product it was found for is excluded from fact extraction (FR-008 edge case) in `tests/Feature/ProductEnrichment/EnrichmentTest.php`
- [ ] T048 [P] [US2] Write unit tests for `QuoteVerifier` (exact case-insensitive substring match against normalized page text; rejects unverifiable quotes) in `tests/Unit/ProductEnrichment/QuoteVerifierTest.php`
- [ ] T049 [P] [US2] Write unit tests for the image quality-flag heuristics (low-resolution arithmetic vs. each conversion's target dimensions; busy-background luminance-stddev threshold) against fixture images (using T031's `ProductAssetFactory` for fixture rows) in `tests/Unit/ProductEnrichment/ImageQualityFlagsTest.php`
- [ ] T050 [P] [US2] Write unit tests for confidence-tier derivation rules (FR-012's manager/authoritative→high, verified-quote-from-allowed-source→medium, vision/ungrounded/conflicted→low buckets — including the explicit assertion that `ai_vision`-origin rows are *always* `low`, never derived from the vision model's own confidence, per FR-014) in `tests/Unit/ProductEnrichment/ConfidenceTierTest.php`
- [ ] T051 [P] [US2] Write unit tests for `ResearchResultsCache` (cache hit skips a repeat `sonar-pro-search` call for the same product identifiers within the TTL; cache miss/expiry triggers a fresh call; quotes are still re-verified against live source pages even on a cache hit) in `tests/Unit/ProductEnrichment/ResearchResultsCacheTest.php`

### Implementation for User Story 2

- [ ] T052 [P] [US2] Create `QuoteVerifier` service (`Http::get()` the cited source, strip to normalized visible text, exact-substring assertion, short-TTL cache by URL purely to avoid re-fetching the same page during one research run — distinct from T053's cache) in `app/Services/ProductEnrichment/QuoteVerifier.php`
- [ ] T053 [P] [US2] Create `ResearchResultsCache` service (caches a product's established research facts/sources keyed by a stable hash of `sku`/`name`/`brand`/`gtin`/`mpn`, TTL from `config('product-enrichment.research_cache_ttl_days')`, per FR-026 and `research.md` §7) in `app/Services/ProductEnrichment/ResearchResultsCache.php`
- [ ] T054 [US2] Implement `ResearchProduct` job (checks `ResearchResultsCache` first per FR-026; on a miss, calls `OpenRouterClient` with `perplexity/sonar-pro-search` + `json_schema` response format; creates `Source` rows, verifying each is positively identified as describing the exact product per FR-008 — excluding any source that describes a merely similar product, or that no longer matches on re-fetch; verifies every quote via `QuoteVerifier`, discarding unverified facts per FR-009; creates `ProductAttributeValue` rows with derived `confidence_tier`; marks the product ungrounded, capped at `low`, when no reliable source is found per FR-010; accumulates cost onto the owning `Import`) in `app/Jobs/ProductEnrichment/ResearchProduct.php`
- [ ] T055 [US2] Implement `AnalyzeProductPhoto` job (calls `OpenRouterClient` with `google/gemini-2.5-flash-lite` — the only vision-capable model available, since neither IBM Granite model on OpenRouter accepts image input, per `research.md` §8 — against the product's primary photo; requests conservative, visually-observable-only attribute values via `json_schema`; creates `ProductAttributeValue` rows with `origin=ai_vision` and `confidence_tier` hard-coded to `low`, never derived from the model's own output, per FR-012/FR-014) in `app/Jobs/ProductEnrichment/AnalyzeProductPhoto.php`
- [ ] T056 [US2] Implement `GenerateProductCopy` job (waits for both `ResearchProduct` and `AnalyzeProductPhoto` to complete for a product; calls `OpenRouterClient` with `ibm-granite/granite-4.1-8b` using only the product's already-established facts per FR-013; creates descriptive `ProductAttributeValue` rows: short/long description, bullet points, SEO title/summary) in `app/Jobs/ProductEnrichment/GenerateProductCopy.php`
- [ ] T057 [US2] Implement `GenerateImageVariants` job (registers each variant as its own media item per `research.md` §4 implementation note; `Fit::Contain` manipulation via `spatie/image` for `primary-ecommerce`/`social-square`; orientation correction + EXIF strip on the `original` collection item; computes and stores `quality_flags`) in `app/Jobs/ProductEnrichment/GenerateImageVariants.php`
- [ ] T058 [US2] Implement cost-cap halt logic (accumulate `processing_cost_usd`; when `cost_cap_usd` is reached, set `Import.status = halted_cost_cap`, leave already-completed products usable, stop only remaining unprocessed products per FR-025) in `app/Models/ProductEnrichment/Import.php`
- [ ] T059 [US2] Dispatch `ResearchProduct`/`AnalyzeProductPhoto`/`GenerateProductCopy` onto the `research`/`research`/`copy-generation` Horizon queues respectively and `GenerateImageVariants` onto `image-variants` (update dispatch calls in `ImportConfirmationController` from T043 and job `$queue` properties; ensure `GenerateProductCopy` is chained/batched to run only after both its Phase-4 predecessors complete for the same product)

**Checkpoint**: User Stories 1 AND 2 both work independently

---

## Phase 5: User Story 3 - Field-Level Review and Approval (Priority: P1) 🎯 MVP step 3

**Goal**: A reviewer sees original vs. proposed value, confidence, and evidence per field, and can
approve/reject/edit/regenerate each one independently before it can reach export; a manager can
also change which uploaded photo is primary for a product.

**Independent Test**: Open a processed product, take each of approve/reject/edit-and-approve on
different fields, confirm state updates correctly and independently per field; change the primary
photo and confirm the prior primary is un-set.

### Tests for User Story 3

- [ ] T060 [US3] Write feature tests covering all 4 acceptance scenarios (approving one field never implicitly affects another; confidence tier + source link + verbatim evidence are visible; edited value is attributed to a human, not the AI; approving a whole product approves every currently-pending field in one action), plus a scenario confirming that setting a different `original`-collection asset as primary (FR-002) un-sets the prior primary asset on the same product in `tests/Feature/ProductEnrichment/ReviewTest.php`

### Implementation for User Story 3

- [ ] T061 [P] [US3] Implement `ProductController@show` (assembles `attributeValues` grouped by attribute with original/proposed/confidence/source/evidence/history, and `assets` with quality flags/review status/`is_primary`) in `app/Http/Controllers/ProductEnrichment/ProductController.php`
- [ ] T062 [P] [US3] Create `EditAttributeValueRequest` form request (`value` required, typed per the attribute's `type`) in `app/Http/Requests/ProductEnrichment/EditAttributeValueRequest.php`
- [ ] T063 [US3] Implement `ProductAttributeValueController@update` (creates a new versioned row with `origin=human_edit`/`review_status=approved`, supersedes the prior row without deleting it, records a `ReviewEvent`) in `app/Http/Controllers/ProductEnrichment/ProductAttributeValueController.php`
- [ ] T064 [US3] Implement `ProductAttributeValueApprovalController@store` (single-row approve happy path; conflict-sibling-rejection behavior is added in Phase 7/US4) in `app/Http/Controllers/ProductEnrichment/ProductAttributeValueApprovalController.php`
- [ ] T065 [US3] Implement `ProductAttributeValueRejectionController@store` in `app/Http/Controllers/ProductEnrichment/ProductAttributeValueRejectionController.php`
- [ ] T066 [US3] Implement `ProductApprovalController@store` (approves every currently-pending field on the product in one transaction, one `ReviewEvent`) in `app/Http/Controllers/ProductEnrichment/ProductApprovalController.php`
- [ ] T067 [P] [US3] Create `UpdateProductAssetRequest` form request (`is_primary`: required, boolean) in `app/Http/Requests/ProductEnrichment/UpdateProductAssetRequest.php`
- [ ] T068 [US3] Implement `ProductAssetController@update` (depends on the `UpdateProductAssetRequest` above; precondition `collection_name=original`; sets the target asset primary and un-sets every sibling `original` asset on the same product, per FR-002 and `contracts/product-review.md`) in `app/Http/Controllers/ProductEnrichment/ProductAssetController.php`
- [ ] T069 [P] [US3] Register `PATCH /product-attribute-values/{value}`, `POST /product-attribute-values/{value}/approval`, `POST /product-attribute-values/{value}/rejection`, `POST /products/{product}/approval`, `PATCH /product-assets/{asset}`, `GET /products/{product}` in `routes/product-enrichment.php`, then run `php artisan wayfinder:generate`
- [ ] T070 [P] [US3] Create `ConfidenceBadge.vue` shadcn-vue component (High/Medium/Low tier display) in `resources/js/components/product-enrichment/ConfidenceBadge.vue`
- [ ] T071 [P] [US3] Create `FieldReviewCard.vue` component (original vs. proposed value, evidence link/quote, approve/reject/edit actions, calling the generated Wayfinder actions) in `resources/js/components/product-enrichment/FieldReviewCard.vue`
- [ ] T072 [US3] Create the review Inertia page (per-field cards, whole-product approve action, asset gallery with a "set as primary" action calling the generated Wayfinder action) in `resources/js/pages/ProductEnrichment/Products/Review.vue`

**Checkpoint**: User Stories 1, 2 AND 3 all work independently — this is the practical MVP loop
minus export

---

## Phase 6: User Story 7 - Export the Current Catalog (Priority: P1) 🎯 MVP step 4

**Goal**: Export everything currently approved across the whole catalog as a spreadsheet, image
package, and provenance manifest.

**Independent Test**: Approve some fields/products, leave others pending/rejected, run an export,
confirm only approved content is included with an accurate skipped-item summary.

### Tests for User Story 7

- [ ] T073 [US7] Write feature tests covering all 3 acceptance scenarios (only approved values exported with accurate excluded-count summary; original columns preserved unchanged with source reference + confidence tier per enriched value; image package includes every approved variant per product plus a full-provenance manifest) plus SC-008's empty/entirely-unreviewed-catalog explicit-summary edge case in `tests/Feature/ProductEnrichment/ExportTest.php`

### Implementation for User Story 7

- [ ] T074 [US7] Implement `GenerateCatalogExport` job (selects `is_current=true AND review_status=approved` values per FR-023; builds the spreadsheet with original columns preserved plus enriched value/source/confidence columns; packages approved media per product/collection; writes the provenance manifest; produces the inclusion/exclusion summary, present even for an entirely-unreviewed catalog per SC-008) in `app/Jobs/ProductEnrichment/GenerateCatalogExport.php`
- [ ] T075 [P] [US7] Implement `ExportController@store`/`@show` in `app/Http/Controllers/ProductEnrichment/ExportController.php`
- [ ] T076 [P] [US7] Implement `ExportArtifactController@show` (streams `spreadsheet`/`images`/`manifest`; 404 if not completed or missing) in `app/Http/Controllers/ProductEnrichment/ExportArtifactController.php`
- [ ] T077 [P] [US7] Register `POST /exports`, `GET /exports/{export}`, `GET /exports/{export}/artifacts/{artifact}` in `routes/product-enrichment.php`, then run `php artisan wayfinder:generate`
- [ ] T078 [P] [US7] Create the export trigger and summary Inertia pages (calling the generated Wayfinder actions) in `resources/js/pages/ProductEnrichment/Exports/Create.vue` and `resources/js/pages/ProductEnrichment/Exports/Show.vue`

**Checkpoint**: MVP complete — US1+US2+US3+US7 form a full import→enrich→review→export loop

---

## Phase 7: User Story 4 - Explicit Resolution of Conflicting Source Data (Priority: P2)

**Goal**: When two equally-credible sources disagree, the reviewer sees both candidates and must
explicitly choose — never silently resolved automatically.

**Independent Test**: Trigger a fixture scenario where two sources disagree on a field; confirm
the reviewer is shown both values with sources and must pick one before the field can be
approved.

### Tests for User Story 4

- [ ] T079 [US4] Write feature tests covering all 3 acceptance scenarios (disagreement flagged as conflicted showing both candidates+sources; selecting one candidate approves it while the other is retained in history but unused; an unresolved conflicted field cannot be included in an export) in `tests/Feature/ProductEnrichment/ConflictResolutionTest.php`

### Implementation for User Story 4

- [ ] T080 [US4] Extend `ResearchProduct` to detect equal-trust source disagreement on a field and assign a shared `conflict_group_id` across the candidate rows (`review_status=conflicted`) in `app/Jobs/ProductEnrichment/ResearchProduct.php`
- [ ] T081 [US4] Extend `ProductAttributeValueApprovalController@store` so approving a conflicted candidate also sets every sibling row sharing its `conflict_group_id` to `review_status=rejected`, `is_current=false` (never deleted) in `app/Http/Controllers/ProductEnrichment/ProductAttributeValueApprovalController.php`
- [ ] T082 [US4] Verify/extend `GenerateCatalogExport`'s selection query to exclude any field still `review_status=conflicted` in `app/Jobs/ProductEnrichment/GenerateCatalogExport.php`

**Checkpoint**: US4 adds conflict handling without breaking US1–US3/US7

---

## Phase 8: User Story 5 - Feedback-Driven Regeneration (Priority: P2)

**Goal**: A reviewer submits free-text feedback on a field and the system regenerates just that
field, keeping the prior version in history.

**Independent Test**: Reject a field with feedback and confirm a new version is produced
reflecting that feedback while the previous version remains visible in history.

### Tests for User Story 5

- [ ] T083 [US5] Write feature tests covering all 3 acceptance scenarios (new version produced, prior version retained, never deleted; regenerating one field leaves an already-approved field completely untouched; the regenerated version never introduces a fact not already established for that product) in `tests/Feature/ProductEnrichment/RegenerationTest.php`

### Implementation for User Story 5

- [ ] T084 [P] [US5] Create `RegenerateFieldRequest` form request (`feedback` required, string, max 1000 chars) in `app/Http/Requests/ProductEnrichment/RegenerateFieldRequest.php`
- [ ] T085 [US5] Implement `ProductAttributeValueRegenerationController@store` (precondition: target row `is_current=true` and not `approved`; dispatches the targeted regeneration; records a `ReviewEvent` with `feedback_text`) in `app/Http/Controllers/ProductEnrichment/ProductAttributeValueRegenerationController.php`
- [ ] T086 [US5] Extend `GenerateProductCopy` with a feedback-driven single-field regeneration path (established facts + feedback text only; versioned output with `regeneration_feedback` set) in `app/Jobs/ProductEnrichment/GenerateProductCopy.php`
- [ ] T087 [P] [US5] Register `POST /product-attribute-values/{value}/regenerations` in `routes/product-enrichment.php`, then run `php artisan wayfinder:generate`
- [ ] T088 [P] [US5] Add a feedback textarea + regenerate action (calling the generated Wayfinder action) to `FieldReviewCard.vue` in `resources/js/components/product-enrichment/FieldReviewCard.vue`

**Checkpoint**: US5 adds regeneration without breaking US1–US4/US7

---

## Phase 9: User Story 6 - Bulk Approval by Rule (Priority: P2)

**Goal**: A reviewer approves many fields at once by a rule (e.g., confidence tier) instead of
clicking through each individually.

**Independent Test**: Define a rule, trigger bulk action, confirm exactly the previewed number of
fields change state, no others.

### Tests for User Story 6

- [ ] T089 [US6] Write feature tests covering both acceptance scenarios (preview shows the exact count that would be affected before any change; confirmed bulk approval affects exactly the matched set as one recorded action, nothing outside the rule) in `tests/Feature/ProductEnrichment/BulkApprovalTest.php`

### Implementation for User Story 6

- [ ] T090 [P] [US6] Create `BulkApprovalRuleRequest` form request (`rule`: confidence_tier/category/attribute_code shape) in `app/Http/Requests/ProductEnrichment/BulkApprovalRuleRequest.php`
- [ ] T091 [P] [US6] Create `BulkApprovalRuleMatcher` service (translates a rule into a `ProductAttributeValue` query scoped to `review_status=pending`) in `app/Services/ProductEnrichment/BulkApprovalRuleMatcher.php`
- [ ] T092 [US6] Implement `BulkApprovalController@create` (preview count via `BulkApprovalRuleMatcher`) and `@store` (commits the rule, one `ReviewEvent` with `bulk_rule`) in `app/Http/Controllers/ProductEnrichment/BulkApprovalController.php`
- [ ] T093 [P] [US6] Register `GET /bulk-approvals/create`, `POST /bulk-approvals` in `routes/product-enrichment.php`, then run `php artisan wayfinder:generate`
- [ ] T094 [P] [US6] Create the bulk-approval rule-builder Inertia page (calling the generated Wayfinder actions) in `resources/js/pages/ProductEnrichment/BulkApprovals/Create.vue`

**Checkpoint**: All seven user stories are now independently functional

---

## Phase 10: Polish & Cross-Cutting Concerns

**Purpose**: Verification and fixtures spanning every story

- [ ] T095 [P] Create the quickstart fixture files (`sample-catalog.xlsx`, `sample-photos.zip`, including the duplicate-SKU row, known-source row, unfindable-product row, busy-background photo, and low-resolution photo) in `tests/Fixtures/ProductEnrichment/`
- [ ] T096 Run `vendor/bin/pint --dirty --format agent` and fix any formatting issues across all new files
- [ ] T097 Run `composer run lint:check` (PHPStan, Rector dry-run, Pint check, frontend checks) and resolve every violation
- [ ] T098 Run `php artisan test --parallel --coverage --min=100` and close any coverage gaps
- [ ] T099 [P] Create `DiffView.vue` component (visual original-vs-proposed diff, used by `FieldReviewCard.vue`) in `resources/js/components/product-enrichment/DiffView.vue`
- [ ] T100 Manually execute `quickstart.md` Steps 1–7 end-to-end against the running app, including the vision-inference expectation (Step 2), the cost-cap input and primary-photo-change actions (Steps 1 and 3), and confirm every "Expected" outcome
- [ ] T101 Security review pass: confirm `ZipInspectorTest` (T037) exercises every FR-005 threat case (path traversal, encryption, decompression bomb) with adversarial fixtures, adding any missing cases

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately
- **Foundational (Phase 2)**: Depends on Setup — BLOCKS all user stories
- **User Stories (Phase 3–9)**: All depend on Foundational completion
  - P1 stories (US1→US2→US3→US7) have a genuine dependency chain — each needs the data the last
    one produced (import → enrichment → review → export) — build in that order
  - P2 stories (US4, US5, US6) each extend the review phase (US3) independently of each other and
    may be built in any order, or in parallel by different developers, once US3 is done
- **Polish (Phase 10)**: Depends on all desired user stories being complete

### Within Each User Story

- Tests are written before implementation and must fail first
- Services before jobs/controllers that use them
- Controllers before route registration before Wayfinder generation before Inertia pages
- Story complete and checkpoint-verified before moving to the next priority

### Parallel Opportunities

- All Setup tasks marked [P] can run in parallel (T002, T003, T005, T006)
- All Foundational model/factory tasks marked [P] can run in parallel (T016–T031, T033–T035) once
  their migrations (T008–T015) exist
- Once US3 (Phase 5) is complete, US4/US5/US6 (Phases 7–9) can be worked on in parallel by
  different developers
- Within any story, tasks marked [P] (distinct files, e.g. services vs. form requests vs. route
  registration vs. Vue pages) can run in parallel

---

## Parallel Example: User Story 1

```bash
# Launch the two independent unit-test files together:
Task: "Write unit tests for ZipInspector safety checks in tests/Unit/ProductEnrichment/ZipInspectorTest.php"
Task: "Write unit tests for SpreadsheetImporter parsing in tests/Unit/ProductEnrichment/SpreadsheetImporterTest.php"

# Launch the two independent services together:
Task: "Create ZipInspector service in app/Services/ProductEnrichment/ZipInspector.php"
Task: "Create SpreadsheetImporter service in app/Services/ProductEnrichment/SpreadsheetImporter.php"
```

---

## Implementation Strategy

### MVP First (US1 → US2 → US3 → US7)

Because US1 alone imports nothing reviewable, the practical MVP for this feature is the full
P1 chain, not User Story 1 in isolation:

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (CRITICAL — blocks all stories)
3. Complete Phase 3: US1 (Import) → **checkpoint**: products land in the catalog
4. Complete Phase 4: US2 (Enrichment) → **checkpoint**: products carry AI-proposed, evidenced fields (text and vision)
5. Complete Phase 5: US3 (Review) → **checkpoint**: fields can be approved/rejected/edited, primary photo changeable
6. Complete Phase 6: US7 (Export) → **STOP and VALIDATE**: run `quickstart.md` Steps 1–3 and 7
   end-to-end — this is the deployable MVP
7. Deploy/demo if ready

### Incremental Delivery After MVP

8. Add US4 (Conflicts) → test independently via `quickstart.md` Step 4
9. Add US5 (Regeneration) → test independently via `quickstart.md` Step 5
10. Add US6 (Bulk Approval) → test independently via `quickstart.md` Step 6
11. Complete Phase 10: Polish — full coverage/lint gates, fixtures, full quickstart re-run

### Parallel Team Strategy

With multiple developers, once the MVP (Phases 1–6) is complete:
- Developer A: US4 (Conflicts)
- Developer B: US5 (Regeneration)
- Developer C: US6 (Bulk Approval)

All three extend US3's review surface but touch distinct controllers/files, so they integrate
independently.

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps every Phase 3+ task to its user story for traceability
- Every controller listed here exposes only standard CRUD-style actions per the recorded
  "Cruddy by Design" rule (`.ai/rules/controllers.md`) — do not add custom verb methods when
  implementing
- Every route registration task is followed by `php artisan wayfinder:generate`; every Vue task
  that calls a route uses the generated action, never a hardcoded URL string
- Verify tests fail before implementing against them
- Commit after each task or logical group
- Stop at any checkpoint to validate a story independently before continuing
- Avoid: vague tasks, same-file conflicts marked [P], cross-story dependencies that break the
  independence of US1/US2/US3/US7's chain or US4/US5/US6's independence from each other

---

## Phase 11: Convergence

- [x] T102 Dispatch `GenerateImageVariants` onto the `image-variants` queue for each product after confirmation — add the dispatch call to `ImportConfirmationController@store` alongside the existing `ResearchProduct` and `AnalyzeProductPhoto` dispatches, and update `GenerateImageVariants` `$queue` property to `'image-variants'` per `plan.md`'s Horizon supervisor config per T059 (missing)
- [x] T103 Fix `GenerateProductCopy` dispatch coordination: `ResearchProduct::dispatchCopyGenerationIfReady()` currently dispatches copy generation unconditionally after research alone completes; add a completion-flag mechanism (e.g., an `enrichment_flags` JSON column on `products` or a dedicated `product_enrichment_states` table) so `GenerateProductCopy` is dispatched only after both `ResearchProduct` AND `AnalyzeProductPhoto` have completed for the same product, per T059 and `plan.md`'s batch/chain requirement (partial)
- [x] T104 Fix `ResearchProduct::failed()` ungrounded-marking: the handler currently only touches `updated_at`; add logic to mark the product as ungrounded (set a `is_ungrounded` flag or create `low`-confidence ungrounded `ProductAttributeValue` rows) and cap all its fields at `low` confidence tier, per FR-010 (partial)
- [x] T105 Fix `AnalyzeProductPhoto` vision image delivery: `getUrl()` returns a private medialibrary URL that OpenRouter cannot fetch externally; convert the primary photo to a base64-encoded data URI (or generate a short-lived public URL) before constructing the vision API payload, per FR-014 and `research.md` §8 (partial)
- [x] T106 Replace the hardcoded `$0.05/product` cost estimate in `ImportController@store` with a derivation from `OpenRouterClient`'s actual per-model token rates (sonar-pro-search + granite-4.1-8b + gemini-2.5-flash-lite), using a shared cost-estimation helper or method, so the estimate genuinely reflects "derived from per-call AI provider pricing" per FR-003 (partial)
- [x] T107 Pass `unmatched_row_threshold` from `ImportController@show` to the `ValidationReport` Inertia page and replace the hardcoded "more than 50%" string in `ValidationReport.vue` with the dynamic config value, per FR-003 (partial)
- [x] T108 Fix `FieldReviewCard.vue` regenerate button visibility: change the `v-if="value.origin === 'ai_generated_copy'"` guard to show the regenerate button for all copy-field attribute codes (`short_description`, `description`, `bullet_points`, `seo_title`, `seo_summary`) regardless of origin — either pass a server-supplied `is_regeneratable` boolean from `ProductController@show` or check `attribute_code` on the client, per FR-019 / US5 (partial)
- [x] T109 Complete security review pass T101: confirm `ZipInspectorTest` exercises all three FR-005 adversarial fixture cases — path-traversal entry, encrypted archive, and decompression-bomb ratio — with purpose-built adversarial fixtures; add any missing cases and verify `ZipInspector` rejects each without extraction, per T101 and FR-005 (partial)
- [ ] T110 Execute `quickstart.md` Steps 1–7 end-to-end against the running application and confirm every "Expected" outcome, including vision inference (Step 2), cost-cap input and primary-photo-change actions (Steps 1 and 3), and the full import→enrich→review→export loop, per T100 (missing)
