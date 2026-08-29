# Contract: Imports (Upload → Validation → Confirm)

Covers User Story 1 (FR-001–FR-006). All routes require `auth` + `verified` middleware (per
Assumptions: any authenticated eCorithmic user may import/review/export).

## `POST /imports`

- **Controller**: `App\Http\Controllers\ProductEnrichment\ImportController@store`
- **Form Request**: `App\Http\Requests\ProductEnrichment\StoreImportRequest`
  - `spreadsheet`: required, file, mimes:xlsx,csv, max 1GB-equivalent guard deferred to the
    archive/row-count checks below (spreadsheet size itself is bounded by row count, not a
    separate byte cap)
  - `archive`: required, file, mimes:zip, max: 1,048,576 KB (1GB, FR-005)
  - `cost_cap_usd`: nullable, numeric, min:0 — the manager's configured spend cap for this import
    (FR-025). Omitting it leaves the import uncapped.
- **Behavior**: parses the spreadsheet (`rap2hpoutre/fast-excel`) and inspects the archive
  (native `ZipArchive`, safety checks per `research.md` §6) without persisting any `Product` row
  yet. Creates one `Import` row with `status = pending_validation` and a populated
  `validation_report` json (matched/unmatched/duplicate/unreadable counts, `estimated_cost_usd`).
- **Response**: Inertia redirect to `GET /imports/{import}` (the validation report page).
- **Rejection case** (FR-004): if unmatched-row share exceeds the configured default (50%), the
  `Import` is created with `status = rejected` and no further action is possible on it — the
  manager must upload a corrected file as a new `Import`.

## `GET /imports/{import}`

- **Controller**: `App\Http\Controllers\ProductEnrichment\ImportController@show`
- **Inertia component**: `ProductEnrichment/ValidationReport`
- **Props**: `import` (id, status, validation_report, estimated_cost_usd, row_count,
  matched_row_count, unmatched_row_count, unmatched_photo_count, duplicate_sku_count,
  unreadable_file_count)

## `POST /imports/{import}/confirmation`

- **Controller**: `App\Http\Controllers\ProductEnrichment\ImportConfirmationController@store`
- **Resource modeled**: the *confirmation* of an import — a distinct resource from the import
  itself (Cruddy by Design: confirming has real side effects — creating products, dispatching
  jobs — so it is not a plain field update on `Import`, it is its own creatable resource).
- **Precondition**: `import.status = pending_validation` (otherwise 409/redirect with error)
- **Behavior**: creates/updates `Product` + seed `ProductAttributeValue` rows (`origin = manager`,
  `confidence_tier = high`) from spreadsheet values (FR-006: existing SKU → update, not
  duplicate); sets `import.status = confirmed` then dispatches one `ResearchProduct` job per
  product; sets `import.status = processing` once dispatched; records `confirmed_by`/`confirmed_at`
  on the `Import`.
- **Response**: Inertia redirect to `GET /imports/{import}` (now showing live progress).

## Progress visibility (FR-017)

No separate polling route — the frontend polls the same `GET /imports/{import}` (`show`) resource
Inertia v3's `poll` helper already re-requests. Progress (per-product processing status counts,
`processing_cost_usd`, and `status = halted_cost_cap` messaging per FR-025) is just the current
state of the `Import` resource, not a distinct action.
