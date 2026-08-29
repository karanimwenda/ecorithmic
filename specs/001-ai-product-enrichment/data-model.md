# Phase 1 Data Model: AI-Powered Product Content Enrichment

**Feature**: [spec.md](./spec.md) | **Plan**: [plan.md](./plan.md) | **Research**: [research.md](./research.md)

Schema style: a trimmed, Bagisto/Akeneo-style EAV model (matching `bagisto_store`, `db_naivas`,
`naivas_v3` on this same database server), extended with confidence/source/review-state columns
this feature needs. `attribute_families`, `attribute_groups`, `attribute_group_mappings`, and the
category-hierarchy tables from the Bagisto reference are intentionally **not** replicated (see
`plan.md` → Complexity Tracking and the confirmed clarifications).

## Entity: Attribute

Canonical definition of every field the fixed spreadsheet template can populate and/or the AI can
enrich. Seeded once via a database seeder — not admin-editable in this feature (Assumptions: one
fixed, documented column template).

| Column | Type | Notes |
|---|---|---|
| `id` | unsigned bigint PK | |
| `code` | string, unique | e.g. `sku`, `name`, `brand`, `gtin`, `mpn`, `short_description`, `description`, `bullet_points`, `seo_title`, `seo_summary`, `price`, `color`, `category` |
| `name` | string | Admin-facing label |
| `type` | enum(`text`,`textarea`,`price`,`boolean`,`select`,`json`) | `json` used for `bullet_points` (array of strings) |
| `is_required` | boolean | |
| `is_unique` | boolean | true only for `sku` |
| `is_ai_enrichable` | boolean | false for manager-only fields (`sku`, `price`); true for fields the AI may propose (FR-013/014) |
| `validation` | string, nullable | e.g. a regex or named rule |
| `position` | integer | display order in the review UI |
| `created_at` / `updated_at` | timestamp | |

## Entity: AttributeOption

Options for `select`-type attributes (`color`, `category`, `brand`).

| Column | Type | Notes |
|---|---|---|
| `id` | unsigned bigint PK | |
| `attribute_id` | FK → `attributes.id` | |
| `admin_name` | string | e.g. "Navy" |
| `sort_order` | integer | |
| `created_at` / `updated_at` | timestamp | |

## Entity: Import

One upload event. Carries its own validation report, progress, and cost — never scopes review or
export (FR-024, Key Entities).

| Column | Type | Notes |
|---|---|---|
| `id` | unsigned bigint PK | |
| `spreadsheet_path` | string | Stored XLSX/CSV file |
| `spreadsheet_original_filename` | string | |
| `archive_path` | string | Stored ZIP file |
| `row_count` | unsigned integer | |
| `matched_row_count` | unsigned integer | Rows matched to a photo |
| `unmatched_row_count` | unsigned integer | Rows without a photo |
| `unmatched_photo_count` | unsigned integer | Photos without a matching row |
| `duplicate_sku_count` | unsigned integer | Duplicate SKUs within this upload |
| `unreadable_file_count` | unsigned integer | |
| `estimated_cost_usd` | decimal(10,4) | Shown in the validation report before confirmation (FR-003) |
| `processing_cost_usd` | decimal(10,4), default 0 | Running actual total, accumulated from every OpenRouter call's `usage` (FR-025) |
| `cost_cap_usd` | decimal(10,4), nullable | Configurable cap (FR-025) — set via `StoreImportRequest` on upload; `null` means uncapped |
| `status` | enum(`pending_validation`,`rejected`,`confirmed`,`processing`,`completed`,`halted_cost_cap`) | See state transitions below |
| `validation_report` | json | Full per-row/per-photo breakdown shown pre-confirmation |
| `confirmed_by` | FK → `users.id`, nullable | |
| `confirmed_at` | timestamp, nullable | |
| `created_at` / `updated_at` | timestamp | |

**State transitions**: `pending_validation` → (`rejected` | `confirmed`) → `processing` →
(`completed` | `halted_cost_cap`). `halted_cost_cap` still leaves already-completed products
usable (FR-025) — only remaining unprocessed rows in that import stop.

## Entity: Product

Persists across imports; owns its field values, images, and review state independent of any one
import (Key Entities).

| Column | Type | Notes |
|---|---|---|
| `id` | unsigned bigint PK | |
| `sku` | string, unique | Identity — matches spreadsheet row and photo filename stem |
| `first_import_id` | FK → `imports.id`, nullable | Which import first created this product |
| `last_import_id` | FK → `imports.id`, nullable | Most recent import that touched this product (FR-006) |
| `created_at` / `updated_at` | timestamp | |

No `type`/`parent_id`/variant-configuration columns (Bagisto's `bgproducts` has them for
configurable products) — this feature has only flat products (Assumptions).

## Entity: ProductAttributeValue

The append-only, versioned value of one attribute for one product. This is where confidence,
source, evidence, and review state attach — extending Bagisto's `product_attribute_values` shape.

| Column | Type | Notes |
|---|---|---|
| `id` | unsigned bigint PK | |
| `product_id` | FK → `products.id` | |
| `attribute_id` | FK → `attributes.id` | |
| `locale` | string, nullable | Kept for future-proofing (Assumptions: no locale filter today) |
| `text_value` | text, nullable | |
| `integer_value` | integer, nullable | |
| `float_value` | decimal(12,4), nullable | e.g. `price` |
| `boolean_value` | boolean, nullable | |
| `json_value` | json, nullable | e.g. `bullet_points` array |
| `origin` | enum(`manager`,`ai_research`,`ai_vision`,`ai_generated_copy`,`human_edit`) | Drives confidence-tier derivation (FR-012); `ai_vision` rows are produced by the `AnalyzeProductPhoto` job (research.md §8) |
| `source_id` | FK → `sources.id`, nullable | Set only when `origin = ai_research` and a source substantiates the value |
| `evidence_quote` | text, nullable | Verbatim, programmatically-verified quote (FR-009) |
| `confidence_tier` | enum(`high`,`medium`,`low`) | Stored, not computed on the fly — derivation rules in FR-012 (never derived from the AI's self-reported confidence) |
| `review_status` | enum(`pending`,`approved`,`rejected`,`conflicted`) | |
| `conflict_group_id` | uuid, nullable | Groups the candidate rows of a conflicted field (US4) — multiple rows may share one group while unresolved |
| `is_current` | boolean, default true | false = superseded history row, never deleted (FR-022) |
| `previous_value_id` | FK → `product_attribute_values.id`, nullable, self-referencing | Version chain |
| `regeneration_feedback` | text, nullable | Free-text feedback that produced this version (FR-022) |
| `approved_by` | FK → `users.id`, nullable | Null for AI-approved-by-rule bulk actions is not applicable — every approval has an actor |
| `approved_at` | timestamp, nullable | |
| `created_at` / `updated_at` | timestamp | |

**Confidence-tier derivation (FR-012, enforced in code, not user input)**:
- `origin = manager` or an authoritative official source → `high`
- `origin = ai_research` with a verified quote from an allowed (non-authoritative) source → `medium`
- `origin = ai_vision`, `origin = ai_generated_copy` derived purely from established facts,
  ungrounded products, or any row still `conflicted` → `low`

**Versioning invariant**: for a given `(product_id, attribute_id)`, there is at most one row with
`is_current = true AND review_status != 'conflicted'` — the live value. Conflicted fields may have
multiple simultaneous `is_current = true, review_status = 'conflicted'` rows sharing a
`conflict_group_id` until a reviewer resolves them (FR-011); the resolution flips the chosen row to
`approved` and every other candidate in the group to `rejected` with `is_current = false`, never
deleted (US4 AC2).

## Entity: Source

An external page consulted while researching one product (Key Entities) — scoped per-product, not
globally shared.

| Column | Type | Notes |
|---|---|---|
| `id` | unsigned bigint PK | |
| `product_id` | FK → `products.id` | |
| `url` | string | |
| `domain` | string | Extracted host, for trust-classification grouping |
| `trust_classification` | enum(`authoritative`,`allowed`,`disallowed`) | |
| `is_usable` | boolean | Whether it was positively identified as describing the exact product (FR-008) |
| `fetched_at` | timestamp, nullable | |
| `created_at` / `updated_at` | timestamp | |

## Entity: Asset (media)

Uses `spatie/laravel-medialibrary`'s `media` table as the base, with a customized migration
adding review/quality columns. Each variant is its **own** media row in its own collection (see
`research.md` §4 implementation note) so every asset — original or variant — carries independent
quality flags and approval state (Key Entities: "carrying its own quality flags and approval
state").

| Column | Type | Notes |
|---|---|---|
| *(all stock medialibrary columns)* | — | `model_type`/`model_id` (→ Product), `collection_name` (`original`\|`primary-ecommerce`\|`social-square`), `file_name`, `mime_type`, `disk`, `size`, etc. |
| `review_status` | enum(`pending`,`approved`,`rejected`), **added** | |
| `quality_flags` | json, nullable, **added** | e.g. `{"low_resolution": false, "busy_background": true}` |
| `is_primary` | boolean, default false, **added** | Meaningful on `collection_name = original` only (FR-002); changeable via `ProductAssetController@update`, which un-sets it on sibling `original` assets of the same product |

## Entity: ReviewEvent

Audit record of every reviewer action (Key Entities).

| Column | Type | Notes |
|---|---|---|
| `id` | unsigned bigint PK | |
| `product_id` | FK → `products.id` | |
| `product_attribute_value_id` | FK → `product_attribute_values.id`, nullable | Null for whole-product or bulk actions |
| `action` | enum(`approve`,`reject`,`edit`,`regenerate`,`approve_product`,`bulk_approve`) | |
| `actor_id` | FK → `users.id` | |
| `feedback_text` | text, nullable | Present for `regenerate` |
| `bulk_rule` | json, nullable | Present for `bulk_approve` — the rule that was applied (FR-021) |
| `created_at` | timestamp | |

## Relationships (summary)

```text
Import 1──* Product          (first_import_id / last_import_id, nullable both directions)
Product 1──* ProductAttributeValue
Product 1──* Source
Product 1──* Media (via spatie/laravel-medialibrary polymorphic relation)
Product 1──* ReviewEvent
Attribute 1──* ProductAttributeValue
Attribute 1──* AttributeOption
Source 1──* ProductAttributeValue (nullable FK, only for origin = ai_research)
ProductAttributeValue 1──1 ProductAttributeValue (previous_value_id, self-referencing version chain)
ProductAttributeValue 1──* ReviewEvent
```

## Out of scope for this schema (confirmed with stakeholder)

- `attribute_families`, `attribute_groups`, `attribute_group_mappings` — no admin-configurable
  attribute organization UI exists in this feature.
- `categories` / `category_translations` / nested category tree — `category` is modeled as a
  plain `select`-type attribute (via `AttributeOption`), since FR-021 is the only mention of
  "category" in the spec and it's just one example bulk-approval rule dimension, not a navigation
  hierarchy.
