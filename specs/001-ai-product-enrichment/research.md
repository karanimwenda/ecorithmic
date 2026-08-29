# Phase 0 Research: AI-Powered Product Content Enrichment

**Feature**: [spec.md](./spec.md) | **Plan**: [plan.md](./plan.md)

All unknowns from the Technical Context have been resolved below. No `NEEDS CLARIFICATION`
markers remain.

## 1. AI provider contract (grounded research + copy generation)

- **Decision**: Call OpenRouter's OpenAI-compatible `POST /api/v1/chat/completions` endpoint
  directly via Laravel's HTTP Client (`Illuminate\Support\Facades\Http`, which wraps Guzzle
  internally) — no additional SDK/package, and no direct use of the Guzzle client.
  - **Research** (`perplexity/sonar-pro-search`, model slug `perplexity/sonar-pro-search`):
    request with `response_format: {type: "json_schema", json_schema: {...}}` asking for a
    structured array of `{attribute_code, value, source_url, quote, source_trust_hint}` per
    candidate fact. This model does **not** accept `tools` (it is already agentic/self-searching)
    but does support structured JSON-schema output, so we get machine-parseable
    quote-per-fact data without prose parsing.
  - **Copy generation** (`ibm-granite/granite-4.1-8b`): request with the product's already-
    established facts (from step above) as context, `response_format: json_schema` returning
    `{short_description, description, bullet_points[], seo_title, seo_summary}`. Granite supports
    both tool calling and structured outputs.
  - **Cost tracking**: every response's `usage.prompt_tokens` / `usage.completion_tokens` is
    multiplied by the model's published per-token rate and summed onto the owning `Import.
    processing_cost_usd` (FR-003, FR-025):
    - `perplexity/sonar-pro-search`: $3.00 / 1M input tokens, $15.00 / 1M output tokens, **plus**
      $18.00 per 1,000 requests (its search step is billed per-call, not just per-token).
    - `ibm-granite/granite-4.1-8b`: $0.05 / 1M input tokens, $0.10 / 1M output tokens.
- **Rationale**: OpenRouter's chat-completions surface is OpenAI-compatible, so no bespoke SDK is
  needed beyond Laravel's HTTP Client; JSON-schema structured output directly satisfies FR-009 (a
  verbatim quote per fact) without fragile prose parsing; usage-based accounting gives an exact,
  not estimated, USD cost per call.
- **Alternatives considered**: A dedicated LLM PHP package (e.g., `prism-php/prism`) — rejected;
  adding an abstraction package for a single well-documented HTTP call shape isn't justified when
  Laravel's HTTP Client already covers it (avoids an unnecessary new dependency, and keeps to
  Constitution VIII — the framework primitive, not raw Guzzle, is the call surface).

## 2. Verbatim quote verification (FR-009)

- **Decision**: Never trust the LLM's self-reported quote. After receiving a candidate
  `{value, source_url, quote}`, independently fetch `source_url` via Laravel's HTTP Client
  (`Http::get()`), reduce the HTML to
  normalized visible text (strip tags/scripts/styles, collapse whitespace), and assert `quote` is
  an exact, case-insensitive substring of that text. Discard the fact if the assertion fails.
  Cache the fetched + normalized page text keyed by URL, with a short TTL, purely so a
  regeneration request doesn't re-fetch the same page moments later — this is a distinct,
  narrower cache from the product-identifier-keyed research-results cache FR-026 actually
  requires (see §7 below; earlier drafts of this document incorrectly conflated the two).
- **Rationale**: This is the literal mechanism FR-009 requires ("verify programmatically that the
  quote genuinely appears in the source content").
- **Alternatives considered**: Trusting the citation the model returns — rejected; that is exactly
  the ungrounded-claim risk FR-009 exists to close.

## 3. Queue driver and management (Laravel Horizon) against the SC-004 timing target

- **Decision**: Commit to `QUEUE_CONNECTION=redis` (Redis is already reachable per `.env`,
  `REDIS_CLIENT=phpredis`, and both `ext-redis`/`ext-pcntl`/`ext-posix` are present) with
  `laravel/horizon` (^5.48) managing workers from the start — not deferred. Define three
  distinct Horizon supervisors/queues, one per job type (`research`, `copy-generation`,
  `image-variants`), so each can be scaled independently and Horizon's dashboard gives per-queue
  throughput/failure metrics for free. This directly supports the SC-004 timing target (30
  products: first ready in ~1 min, full set in ~5 min) by letting research jobs for many products
  run concurrently across dedicated workers rather than competing with copy-generation/image jobs
  on a single queue.
- **Dashboard access**: `/horizon` must be explicitly gated in `HorizonServiceProvider::gate()`
  (restricted to authorized users) rather than left on Horizon's default — Horizon closes the
  dashboard outside the `local` environment by default, but the gate should still be defined
  explicitly, not left as an implicit TODO.
- **Rationale**: Horizon requires a Redis-backed queue connection; committing to it upfront
  (rather than the previously-planned "start on database, evaluate Redis later") avoids a
  mid-implementation migration of queue configuration once Horizon is in place.
- **Alternatives considered**: Plain `queue:work` on the database driver — rejected now that
  Horizon is the stated direction; Horizon does not support the database driver. Installing
  Horizon without dedicating per-job-type supervisors — rejected; a single shared queue would not
  give the independent scaling the SC-004 target benefits from.

## 4. Image variant generation (FR-015)

- **Decision**: Use `spatie/laravel-medialibrary`'s named conversions on each product's media
  collection: `primary-ecommerce` (large, white-background canvas) and `social-square` (small,
  square canvas) — both using the `Fit::Contain` manipulation (resize to fit within the target
  canvas, padding as needed) and **never** `Fit::Crop`, so the subject is never cut. Orientation is
  corrected from EXIF before any resize; sensitive EXIF metadata is stripped as an explicit
  manipulation step when the original is registered, before the "original" media item is
  persisted — covering both halves of FR-015 (normalize *and* generate variants) in one pipeline.
- **Rationale**: This maps directly onto FR-015's "declarative size/aspect/background
  specification" language; conversions generate on a queued job automatically, which lines up with
  FR-017's background-processing requirement for free.
- **Alternatives considered**: Hand-rolled GD/Imagick resize code — rejected; medialibrary already
  provides this declaratively with less custom code to maintain and test.
- **Implementation note**: the Key Entity "Asset" (spec.md) requires each variant to carry its
  *own* independent quality flags and approval state, not just the original. Medialibrary's
  built-in `addMediaConversion()` mechanism stores conversions as derived files tracked inside the
  *same* media row (`generated_conversions` json) — too coarse for per-variant review state. Instead,
  each variant is registered as its **own media item** in its own collection (`original`,
  `primary-ecommerce`, `social-square`), generated by an explicit queued job that runs the
  `Fit::Contain` manipulation via `spatie/image` and adds the resulting file with
  `addMedia()->toMediaCollection(...)`. This still uses medialibrary for storage/disk/URL
  management, just not its automatic conversion-registration API, so every variant gets its own
  row and therefore its own `review_status`/`quality_flags` columns.

## 5. Quality flags — low resolution & "busy" background (FR-016)

- **Decision**:
  - *Low resolution*: pure arithmetic — compare the original image's pixel dimensions against
    each conversion's target dimensions; no package needed.
  - *Busy background*: sample a thin border region around the image edges (where a clean product
    photo's background is normally visible) and compute the standard deviation of pixel
    luminance across that sampled region; flag as "busy" above an empirically-tuned threshold
    (tuned against real fixture photos during implementation — one clean-background and one
    busy-background sample at minimum).
  - Both flags are computed once per original upload and stored in the extended `media.
    quality_flags` json column; neither flag ever blocks processing or silently corrects the
    image (FR-016 explicitly forbids silent correction).
- **Rationale**: Deterministic, dependency-free, and directly unit-testable against fixture
  images.
- **Alternatives considered**: An ML-based background-complexity classifier — rejected as
  disproportionate for a boolean quality flag; would itself require a new model/dependency
  decision this feature doesn't need.

## 6. ZIP archive safety validation (FR-005)

- **Decision**: Using PHP's native `ext-zip` (`ZipArchive`), before extracting anything:
  1. Reject if the archive reports an encryption flag on open or on any entry (encrypted
     archives are rejected outright, never extracted).
  2. Reject any entry whose normalized path resolves outside the target extraction directory
     (path-traversal guard).
  3. Reject if any entry's uncompressed-to-compressed size ratio exceeds a fixed multiplier (e.g.,
     100×), or if the archive's total uncompressed size would exceed the FR-005 archive cap
     (decompression-bomb guard).
  4. Only after every entry passes these checks does extraction proceed, into a quarantined
     temporary directory, before filenames are matched to SKUs.
- **Rationale**: Native extension, zero new dependencies, and maps 1:1 onto the exact threat list
  FR-005 names (path traversal, encrypted archives, decompression bombs).
- **Alternatives considered**: A dedicated archive-scanning Composer package — none well-
  maintained and Laravel-specific exists for this narrow need; native `ZipArchive` plus explicit
  checks is standard practice for this threat model.

## 7. Research-results caching by product identifier (FR-026)

- **Decision**: This is a distinct mechanism from §2's page-text cache. Before `ResearchProduct`
  makes any OpenRouter call for a product, it checks a cache keyed by a stable hash of the
  product's research identifiers (`sku`, `name`, `brand`, `gtin`/`mpn` when present). If a cache
  entry exists (within a configurable TTL — default 7 days), the job reuses the previously
  established facts/sources instead of repeating the external research call entirely; it still
  re-verifies quotes against freshly-fetched source pages (§2) rather than trusting stale
  verification, since page content can change independently of the research-cache TTL. On a
  cache hit, `ResearchProduct` still runs, but skips straight to reusing the cached candidate
  facts instead of calling `perplexity/sonar-pro-search` again — this is what actually avoids
  "repeating external research" per FR-026's wording.
- **Rationale**: FR-026 explicitly requires caching *research results* "for a given product's
  identifiers," which is a different concern from caching one fetched page's text by URL (§2) —
  the former avoids repeating the (expensive, `$18`-per-call) search step itself on reprocessing;
  the latter only avoids re-fetching a page already known from an *in-progress* research run.
- **Alternatives considered**: Relying solely on the URL-keyed page-text cache — rejected; it does
  nothing to prevent a second full `sonar-pro-search` call (the dominant cost driver per §1's
  pricing) when the same product is reprocessed.

## 8. Vision-based attribute inference (FR-014)

- **Decision**: Add a dedicated `AnalyzeProductPhoto` job, dispatched onto the `research` Horizon
  queue alongside `ResearchProduct`, that sends the product's primary photo to a vision-capable
  model via the same `OpenRouterClient`/Laravel HTTP Client call surface as every other AI call in
  this feature. It requests conservative, visually-observable-only descriptions (e.g., "appears
  navy") for attributes not already established with higher confidence, using `response_format:
  json_schema` to return `{attribute_code, value}` pairs. Every resulting `ProductAttributeValue`
  row is created with `origin = ai_vision` and `confidence_tier` **hard-coded to `low`** — never
  derived from the model's own output — per FR-012 and FR-014's explicit requirement that visual
  observations are "never asserted as a specification." `GenerateProductCopy` (which drafts
  descriptive copy from established facts) must wait for both `ResearchProduct` and
  `AnalyzeProductPhoto` to complete for a product, so vision-inferred facts are available to it.
  This job is also what makes FR-010's ungrounded-fallback wording literal: a product with no
  usable text source is still "fully processed using only its photo and name" via this job,
  rather than being left with no field values beyond the manager-supplied spreadsheet columns.
- **Model choice**: `google/gemini-2.5-flash-lite` (via OpenRouter) — **not** an IBM Granite
  model, because neither IBM Granite model available on OpenRouter (`ibm-granite/granite-4.1-8b`,
  `ibm-granite/granite-4.0-micro`) accepts image input; both are text-only. Every other AI call in
  this feature uses an IBM model per the hackathon constraint, but no IBM alternative exists for
  this specific vision-input capability, so a cheap, vision-capable, structured-output-supporting
  model is used instead: $0.10/1M input tokens, $0.40/1M output tokens, +$0.10/1M for image input.
- **Rationale**: Closes the gap between spec.md (FR-014, FR-010) and the schema (`data-model.md`'s
  `ai_vision` origin value existed with no producer prior to this fix); reuses the existing
  OpenRouterClient/HTTP Client pattern, so no new dependency is introduced.
- **Alternatives considered**: Skipping vision inference entirely (leaving `ai_vision` as a
  theoretical, unused enum value) — rejected; it's an explicit, testable FR (FR-014) with its own
  acceptance-scenario-adjacent language ("appears navy"), not an optional enhancement. Using a
  second IBM model — rejected; none with vision input exists on OpenRouter at the time of this
  research.

## Summary of new dependencies

| Package | Purpose | Already compatible with PHP 8.5 / Laravel 13? |
|---|---|---|
| `rap2hpoutre/fast-excel` | XLSX/CSV spreadsheet parsing (agreed in `/speckit.clarify`) | Yes |
| `spatie/laravel-medialibrary` (^11.23) | Image storage, normalization, declarative variant conversions (pulls in `spatie/image` ^3.3 transitively) | Yes — confirmed via Packagist (`illuminate/*: ^13.0`, `php: ^8.2`) |
| `laravel/horizon` (^5.48) | Redis queue dashboard, monitoring, and per-job-type supervisors (§3) | Yes — confirmed via Packagist (`illuminate/*: ^13.0`, `php: ^8.0`); requires `ext-pcntl`/`ext-posix`, both present locally |

No other new Composer dependencies are required — AI calls and source-page fetches use Laravel's
HTTP Client (`Illuminate\Support\Facades\Http`), never the raw Guzzle client directly, so that
`Http::fake()` is the test double for every external call (Constitution VIII: use the framework
primitive, not what it wraps); archive safety uses the native `ext-zip` extension.
