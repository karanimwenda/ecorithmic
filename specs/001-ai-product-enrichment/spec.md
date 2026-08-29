# Feature Specification: AI-Powered Product Content Enrichment

**Feature Branch**: `feature/001-ai-product-enrichment`

**Created**: 2026-08-29

**Status**: Draft

**Input**: User description: "Raw TDD 'AI Content Studio for E-commerce Catalogues' (v0.3, hackathon MVP), reframed for eCorithmic — a Product Information Management platform in the vein of Plytix/Akeneo — as a persistent-catalog AI enrichment capability: import product spreadsheets and photos into the catalog (all at once or in portions), enrich each product with web-grounded facts and platform-ready imagery, review every AI claim with verbatim evidence before it counts, and export whatever is currently approved across the whole catalog."

## Clarifications

### Session 2026-08-29

- Q: What file format(s) must the system accept for the product spreadsheet upload? → A: XLSX and CSV (parsed via `rap2hpoutre/fast-excel`)
- Q: What archive format(s) must the system accept for the product photo upload? → A: ZIP only
- Q: What should the concrete numeric upload limits be for row count, image count, per-image size, and archive size? → A: 5,000 rows max / 5,000 images max / 20MB max per image / 1GB max archive size
- Q: What unit should the system use to measure and cap "processing cost" per import? → A: Estimated monetary cost in USD, derived from per-call AI provider pricing
- Q: What exact filename convention must a photo use to match its spreadsheet row? → A: Exact match — filename stem must equal the SKU exactly (e.g., `SKU123.jpg`)

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Import Products Into the Catalog (Priority: P1)

A catalog manager uploads a spreadsheet of product rows together with a folder of product photos, in one go or across multiple uploads over time, so that products enter eCorithmic's catalog ready for enrichment.

**Why this priority**: Nothing else in this feature can happen until products exist in the catalog. This is also the single riskiest point of user-visible failure (mismatched files, bad data) if it isn't handled explicitly with a review step.

**Independent Test**: Can be fully tested by uploading a spreadsheet + photo archive and confirming: (a) a validation report is shown before anything is committed, (b) new products appear in the catalog after confirmation, and (c) re-uploading a spreadsheet referencing an already-catalogued SKU updates that product rather than creating a duplicate or erroring.

**Acceptance Scenarios**:

1. **Given** a spreadsheet with 20 valid rows and a photo archive where every row has a matching photo, **When** the manager uploads both and confirms the validation report, **Then** 20 new products appear in the catalog and begin processing.
2. **Given** a spreadsheet where 3 rows have no matching photo, **When** the manager uploads it, **Then** the validation report lists those 3 rows before any product is created, and the manager may confirm anyway or re-upload corrected files.
3. **Given** an upload where more than half the rows have no matching photo, **When** the manager attempts to confirm, **Then** the system rejects the import outright and no products are created or updated.
4. **Given** a spreadsheet row whose SKU already exists in the catalog, **When** the manager uploads and confirms it, **Then** the existing product's data is updated with the new row's values rather than a duplicate product being created.
5. **Given** an uploaded photo archive containing an unsafe file (e.g., a path-traversal entry or an encrypted archive), **When** the system inspects it, **Then** the unsafe content is rejected and reported without being extracted or executed.

---

### User Story 2 - Automatic Grounded Content & Image Generation (Priority: P1)

Once a product is imported, eCorithmic automatically researches it, produces platform-ready photos, and drafts descriptive content — visibly progressing in the background — without the manager needing to wait or babysit each product.

**Why this priority**: This is the core value delivery — turning a thin spreadsheet row into a fully-fledged, richly described, platform-ready catalog entry — and it's what makes review (Story 3) possible.

**Independent Test**: Can be tested by importing a product with fixture-known facts and confirming imagery variants and grounded field values with confidence tiers and evidence appear, without any manual step.

**Acceptance Scenarios**:

1. **Given** a newly imported product with a findable, authoritative online source, **When** processing completes, **Then** its descriptive fields carry values with a verbatim quote and confidence tier drawn from that source.
2. **Given** a product for which no reliable online source can be found, **When** processing completes, **Then** the product is still fully processed (never left in a failed state), marked as unverified/"ungrounded," with every field capped at the lowest confidence tier.
3. **Given** an import of 30 newly imported products, **When** processing runs, **Then** the first product is ready for review within about a minute, and the whole set completes within about five minutes.
4. **Given** one product's processing encounters an unrecoverable error, **When** the rest of the import continues, **Then** other products' processing is unaffected and continues to completion.
5. **Given** a product's photo, **When** platform-ready image variants are generated, **Then** the product itself is never cropped out of frame — variants pad rather than cut into the subject — and any quality concerns (low resolution, non-uniform background) are flagged, not silently fixed or blocked.

---

### User Story 3 - Field-Level Review and Approval (Priority: P1)

A reviewer opens a processed product and, field by field, sees the original uploaded value next to the proposed value with its confidence and supporting evidence, then approves, rejects, or edits each one before it can ever reach an export.

**Why this priority**: This is the trust mechanism the whole feature exists to deliver — nothing generated is usable until a human has looked at it.

**Independent Test**: Can be tested by opening a processed product, taking each of the four field actions (approve/reject/edit-and-approve/regenerate) on different fields, and confirming state updates correctly and independently per field.

**Acceptance Scenarios**:

1. **Given** a product with several proposed fields, **When** the reviewer approves one field and rejects another, **Then** each field's state updates independently — approving one does not implicitly approve or reject any other.
2. **Given** a proposed field with a source-backed value, **When** the reviewer views it, **Then** they can see the confidence tier, a link to the source, and the exact quoted evidence backing that value.
3. **Given** a proposed field the reviewer disagrees with, **When** they edit the value and save, **Then** the field is marked as approved with the edit attributed to a human, not the AI.
4. **Given** a reviewer approves an entire product, **When** that action is taken, **Then** every currently-pending field on that product becomes approved in one action.

---

### User Story 4 - Explicit Resolution of Conflicting Source Data (Priority: P2)

When two equally credible sources disagree about a fact, the reviewer sees both candidate values side by side and must explicitly choose — the system never silently resolves the disagreement on the reviewer's behalf.

**Why this priority**: Protects the trust guarantee in the one case where automatic resolution would be actively misleading; not needed for every product, so it can follow the baseline review flow.

**Independent Test**: Can be tested by triggering a fixture scenario where two sources disagree on a field and confirming the reviewer is shown both values with their sources and must pick one (or supply their own) before the field can be approved.

**Acceptance Scenarios**:

1. **Given** two equally-trusted sources disagree on a field's value, **When** processing completes, **Then** the field is flagged as conflicted and shows both candidate values with their respective sources.
2. **Given** a conflicted field, **When** the reviewer selects one of the candidate values, **Then** that value becomes the approved value and the other is retained in the field's history but not used.
3. **Given** a conflicted field, **When** no reviewer action has been taken, **Then** the field cannot be included in an export.

---

### User Story 5 - Feedback-Driven Regeneration (Priority: P2)

A reviewer who dislikes a specific piece of generated content types free-text feedback ("make it shorter", "wrong colour, it's navy") and the system regenerates just that field, keeping the prior version in history.

**Why this priority**: Meaningfully raises the ceiling on review efficiency (fix by asking rather than manually rewriting) but the feature works without it — reviewers can already edit-and-approve manually.

**Independent Test**: Can be tested by rejecting a field with feedback text and confirming a new version is produced reflecting that feedback while the previous version remains visible in history.

**Acceptance Scenarios**:

1. **Given** a pending field, **When** the reviewer submits feedback and requests regeneration, **Then** a new version of that field is produced and the prior version is retained, never deleted.
2. **Given** a field that has already been approved, **When** a regeneration is requested for a different, still-pending field on the same product, **Then** the already-approved field is left completely untouched.
3. **Given** a regeneration request, **When** the new version is produced, **Then** it does not introduce any fact not already established for that product.

---

### User Story 6 - Bulk Approval by Rule (Priority: P2)

A reviewer approves many fields at once by rule (e.g., "every high-confidence field across the catalog") instead of clicking through each one individually.

**Why this priority**: A significant time-saver at real catalog scale, but the catalog remains fully usable via one-by-one review without it.

**Independent Test**: Can be tested by defining a rule (e.g., confidence tier = High), triggering the bulk action, and confirming exactly the previewed number of fields change state, no others.

**Acceptance Scenarios**:

1. **Given** a rule that matches a specific set of pending fields, **When** the reviewer requests bulk approval, **Then** the system shows the exact count of fields that will be affected before anything changes.
2. **Given** a confirmed bulk approval, **When** it completes, **Then** all matched fields become approved as a single recorded action, and no field outside the rule is affected.

---

### User Story 7 - Export the Current Catalog (Priority: P1)

A catalog manager exports the entirety of what's currently approved in the catalog as an updated spreadsheet, a package of finished images, and a manifest recording where every fact and image came from.

**Why this priority**: This is how value actually leaves the system — without export, review has no destination.

**Independent Test**: Can be tested by approving some fields/products, leaving others pending or rejected, running an export, and confirming only approved content is included with an accurate skipped-item summary.

**Acceptance Scenarios**:

1. **Given** a catalog with a mix of approved, pending, and rejected fields, **When** an export is run, **Then** the exported spreadsheet includes only approved values, with unreviewed/rejected fields excluded and counted in an export summary.
2. **Given** an export is run, **When** the output is produced, **Then** original uploaded spreadsheet columns are preserved unchanged and every enriched value is paired with its source reference and confidence tier.
3. **Given** an export is run, **When** the image package is produced, **Then** it includes every approved image variant for every product, organized so each product's variants are easy to locate, plus a manifest with full provenance for every exported fact and image.

---

### Edge Cases

- What happens when an uploaded spreadsheet has zero readable rows, or the photo archive is empty? → validation report shows this before any processing begins; nothing is imported.
- What happens if the same SKU appears twice within one uploaded spreadsheet? → flagged as a duplicate-row conflict in that upload's validation report; the ambiguity must be resolved (e.g., a corrected file re-uploaded) before those rows are imported.
- How does the system handle a source page that no longer matches the product it was found for? → that source is excluded from fact extraction entirely for that product, as if it had never been found.
- What happens when every possible online source for a product fails or is disallowed (e.g., blocked by robots rules)? → the product still completes processing, marked ungrounded, never left failed.
- How does the system handle an import whose processing cost exceeds its configured cap partway through? → already-completed products remain usable as-is; only the remaining unprocessed products in that import stop, and this is reported to the manager.
- What happens if a reviewer requests regeneration on a field that another reviewer just approved moments earlier? → the approved field is never overwritten; regeneration only ever affects fields still in a pending state.
- What happens when an export is run against an empty or entirely-unreviewed catalog? → the export completes with an explicit summary stating that everything was skipped, rather than silently producing an empty result.
- What happens to a product's images if its source photo is lower resolution than a target platform's requirement? → the variant is still produced but flagged as below target resolution rather than being silently upscaled or blocked.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST allow a catalog manager to upload a product spreadsheet (XLSX or CSV, against a fixed, documented column template) together with a ZIP archive of product photos, either as a single upload or as multiple separate uploads over time.
- **FR-002**: System MUST match each uploaded photo to a spreadsheet row by requiring the photo's filename stem to exactly equal the row's SKU (e.g., `SKU123.jpg`), designating one matched photo as primary for that product (changeable by the manager).
- **FR-003**: Before importing anything, System MUST present a validation report showing counts of matched rows, rows without a photo, photos without a matching row, duplicate SKUs within the same upload, and unreadable files, plus an estimated processing cost in USD (derived from per-call AI provider pricing), and MUST require the manager's explicit confirmation before any product is created or updated.
- **FR-004**: System MUST reject an upload outright (creating or updating nothing) when more than a configurable share of its rows (default 50%) have no matching photo.
- **FR-005**: System MUST enforce upload limits (maximum 5,000 rows per spreadsheet, maximum 5,000 images per archive, maximum 20MB per image, maximum 1GB per archive), MUST accept only ZIP archives, and MUST reject unsafe archive content — including directory-traversal entries, encrypted archives, and decompression bombs — without ever extracting or executing uploaded content that fails these checks.
- **FR-006**: When an uploaded row's SKU already exists in the catalog, System MUST update that existing product's spreadsheet-supplied values rather than creating a duplicate product.
- **FR-007**: System MUST research each newly imported or updated product using its available identifiers (SKU, name, brand, and GTIN/MPN when present) to locate candidate source pages that plausibly describe that exact product.
- **FR-008**: System MUST extract factual attributes only from source pages it can positively identify as describing the exact product in question; pages identified as describing a merely similar (but not identical) product MUST be excluded from fact extraction for that product.
- **FR-009**: Every fact the system attributes to a source MUST be accompanied by a verbatim quote from that source; the system MUST verify programmatically that the quote genuinely appears in the source content and MUST discard any fact whose quote cannot be verified.
- **FR-010**: When no reliable source can be found for a product, or all attempts to retrieve sources fail, System MUST still fully process that product using only its photo and name, MUST mark it as unverified ("ungrounded"), and MUST cap every one of its fields' confidence at the lowest tier — the system MUST NEVER leave a product in a failed or incomplete state solely due to lack of grounding.
- **FR-011**: When multiple credible sources disagree on a field's value at equal trust, System MUST retain all conflicting candidate values and require a human reviewer to make an explicit choice; the system MUST NEVER silently choose between them.
- **FR-012**: System MUST assign every field a confidence tier (High/Medium/Low) derived from where its value came from (e.g., manager-supplied data or an authoritative official source = High; text extracted from an allowed source with a verified quote = Medium; image-only inference, ungrounded products, or unresolved conflicts = Low), and MUST NEVER derive this tier from the AI's own self-reported confidence.
- **FR-013**: System MUST generate descriptive copy (short/long description, bullet points, SEO title and summary) for each product using only the facts already established for that product, and MUST NOT introduce any unstated fact (e.g., dimensions, materials, certifications, warranty terms, safety claims, country of origin) that isn't present in that product's established field values.
- **FR-014**: System MUST allow descriptive language about what is visually observable in a product photo, but such statements MUST be phrased conservatively (e.g., "appears navy") and MUST always be tagged at the lowest confidence tier, never asserted as a specification.
- **FR-015**: System MUST normalize every product photo (correcting orientation, removing sensitive embedded metadata) and produce at least two platform-ready image variants per product from a declarative size/aspect/background specification, without ever cropping into the product subject to fit a canvas.
- **FR-016**: System MUST flag, but never silently correct, image quality concerns such as a source photo below a variant's target resolution or a non-uniform ("busy") background that may not composite cleanly onto a plain canvas.
- **FR-017**: System MUST process imported products in the background, MUST make each import's processing progress visible within a few seconds of it changing, and MUST ensure one product's processing failure never halts or discards the processing of other products in the same or a different import.
- **FR-018**: System MUST present a field-by-field review for each product: the original uploaded value alongside the proposed value, its confidence tier, its source (if any) with a link to it, and the verbatim evidence text backing it.
- **FR-019**: For every field, System MUST allow a reviewer to independently: approve it, reject it, edit its value and approve that edit (recorded as human-provided, distinct from AI-provided), or request regeneration with free-text feedback.
- **FR-020**: System MUST allow a reviewer to approve an entire product in one action, which approves every field on that product still in a pending state.
- **FR-021**: System MUST allow bulk approval of fields across the catalog by a chosen rule (e.g., confidence tier, category, or field type) and MUST show the reviewer the exact number of fields that will be affected before the action is committed.
- **FR-022**: When a reviewer requests regeneration of a field with free-text feedback, System MUST produce a new version of that field reflecting the feedback, MUST retain every prior version of that field, and MUST NEVER overwrite or discard a field that has already been approved.
- **FR-023**: System MUST exclude any field or product that is not currently approved from an export, and MUST report in the export's summary exactly how many fields/products were excluded and why.
- **FR-024**: System MUST allow exporting the entire current catalog — not a subset tied to any one import — as an updated spreadsheet (original columns preserved unchanged, enriched values appended alongside their source reference and confidence tier), a package of approved image variants organized per product, and a manifest recording full provenance for every exported fact and image.
- **FR-025**: System MUST track the processing cost (in USD, derived from per-call AI provider pricing) of each import and MUST allow a configurable cap on that cost per import; when the cap is reached, already-completed products MUST remain usable and only the remaining unprocessed products in that import MUST stop, with this reported to the manager.
- **FR-026**: System MUST cache prior research results for a given product's identifiers for a limited time so that reprocessing the same product does not repeat external research unnecessarily.

### Key Entities

- **Import**: A single upload event (spreadsheet plus optional photo archive) that adds new products to the catalog or updates existing ones; carries its own validation report, processing progress, and processing cost (USD). Does not scope review or export — those always operate on the catalog as a whole.
- **Product**: A single catalog item, persisting across imports, holding its own set of field values, images, and review state independent of other products.
- **Attribute Value**: A single named piece of product information (e.g., description, colour) together with where it came from, its confidence tier, the evidence supporting it, and its version history (including any superseded prior versions).
- **Source**: An external page the system consulted while researching a product, recorded with its trust classification and whether it was actually usable for that product.
- **Asset**: An image belonging to a product — the original upload or a derived platform-ready variant — carrying its own quality flags and approval state.
- **Review Event**: A record of a reviewer's action (approve, reject, edit, regenerate, or bulk-approve) kept for audit and history purposes.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A catalog manager can upload a spreadsheet and photo archive and see a complete validation report, identifying every mismatch, before any product is created or updated.
- **SC-002**: At least 95% of rows in a well-formed upload (clean filenames, valid spreadsheet) are matched to their photo without any manual correction.
- **SC-003**: Across a fixed evaluation set of products with known facts, zero unsupported factual claims (claims lacking a verifiable source quote) reach the review stage.
- **SC-004**: The first product in a 30-product import is ready for review within about one minute of the import starting, and the full set completes within about five minutes.
- **SC-005**: A single product's processing failure never prevents any other product, in the same or a different import, from completing.
- **SC-006**: 100% of fields included in an export carry either a traceable source reference or an explicit "no source" marker, and a confidence tier.
- **SC-007**: A reviewer can request a content change via free-text feedback and see the updated field without any other, already-approved field being altered.
- **SC-008**: An export always states exactly how many products/fields were excluded and why, even when the catalog is entirely unreviewed.

## Assumptions

- Only one implicit user role exists for this feature (import, review, and export actions are all available to any authenticated eCorithmic user); dedicated roles, permissions, and multi-tenant workspaces are a broader platform concern, not built by this feature.
- Uploaded spreadsheets follow one fixed, documented column template; mapping arbitrary spreadsheet layouts to this template is not covered by this feature.
- Product photos are assumed to already be reasonably clean, front-facing shots; this feature flags photos with quality concerns (low resolution, busy backgrounds) but does not automatically remove or replace backgrounds — that is a possible future capability, not built here.
- This feature produces export files only; it does not publish directly to any external marketplace or sales channel. Direct channel publishing is a broader platform concern for later.
- Feedback-driven regeneration in this feature applies to descriptive copy fields only; regenerating images from feedback is not covered.
- When no locale is specified for an import, research defaults to no locale filter (English-first, global sources); a per-import locale setting may be added later without changing this feature's core behavior.
- This feature ships exactly two platform-ready image variant profiles: one larger, white-background profile suited to primary e-commerce listing use, and one smaller, square profile suited to social/secondary use. Additional profiles are a future extension.
- When an import's processing cost cap is reached, the default behavior is to halt only the remaining unprocessed products in that import (already-completed products remain usable) rather than rejecting the whole import.
