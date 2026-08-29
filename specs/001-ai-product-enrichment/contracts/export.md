# Contract: Catalog Export

Covers User Story 7 (FR-023–FR-024). Requires `auth` + `verified` middleware. Export is always
catalog-wide — it is not scoped to any one `Import` (Key Entities: Import "does not scope review
or export").

## `POST /exports`

- **Controller**: `App\Http\Controllers\ProductEnrichment\ExportController@store`
- **Behavior**: dispatches a queued `GenerateCatalogExport` job that:
  1. Selects every `Product` and, per product, every `ProductAttributeValue` where
     `is_current = true AND review_status = approved` (FR-023 excludes anything not currently
     approved).
  2. Builds an updated spreadsheet: original uploaded columns preserved unchanged, plus one
     column pair per enriched attribute (`{attribute}_value`, `{attribute}_source_reference`,
     `{attribute}_confidence_tier`) (FR-024).
  3. Packages every `approved` media asset (per product, per collection) into a per-product
     folder structure (FR-024).
  4. Writes a manifest (json) recording, for every exported fact and image, its `source_id`/URL
     (or an explicit "no source" marker) and `confidence_tier` (SC-006).
  5. Produces a summary: counts of products/fields excluded and why (not-approved, rejected,
     conflicted-unresolved) — present even when the catalog is entirely unreviewed (FR-023,
     SC-008, edge case: empty/unreviewed catalog still produces an explicit "everything skipped"
     summary rather than silently producing nothing).
- **Response**: Inertia redirect to `GET /exports/{export}`.

## `GET /exports/{export}`

- **Controller**: `ExportController@show`
- **Inertia component**: `ProductEnrichment/ExportSummary`
- **Props**: `export` (status, summary: `{included_products, included_fields,
  excluded_products, excluded_fields, excluded_reasons}`, download URLs for the spreadsheet, the
  image package, and the manifest once `status = completed`).

## `GET /exports/{export}/artifacts/{artifact}`

- **Controller**: `App\Http\Controllers\ProductEnrichment\ExportArtifactController@show`
- **Resource modeled**: a specific artifact (`spreadsheet`, `images`, or `manifest`) *of* an
  export — its own nested resource rather than a custom `download` verb on `ExportController`.
- **Route parameter**: `artifact` ∈ `{spreadsheet, images, manifest}`
- **Behavior**: streams the requested file from storage; 404 if the export hasn't completed or
  the artifact doesn't exist for that export.

## Controller summary

```text
ExportController          → store, show
ExportArtifactController  → show
```
