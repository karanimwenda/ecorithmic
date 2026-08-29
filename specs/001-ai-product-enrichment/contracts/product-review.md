# Contract: Product Review (Field-Level Actions, Conflicts, Feedback, Bulk Approval)

Covers User Stories 2–6 (FR-007–FR-022). All routes require `auth` + `verified` middleware.

Per the **Cruddy by Design** controller convention: every state change below is modeled as its
own resource with only the 7 standard actions (`index`, `show`, `create`, `store`, `edit`,
`update`, `destroy`) — never a custom verb method on a shared controller. "Approving,"
"rejecting," and "regenerating" a field are nouns in disguise (an *approval*, a *rejection*, a
*regeneration request*), each getting its own small, single-purpose controller.

## `GET /products/{product}`

- **Controller**: `App\Http\Controllers\ProductEnrichment\ProductController@show`
- **Inertia component**: `ProductEnrichment/Review`
- **Props**: `product` (sku, timestamps), `attributeValues` (grouped by attribute — each with
  original manager-supplied value alongside the current proposed value(s), `confidence_tier`,
  `source` [url + trust_classification] when present, `evidence_quote`, `review_status`,
  `conflict_group_id` when conflicted, and a `history` array of superseded versions),
  `assets` (original + `primary-ecommerce` + `social-square`, each with `quality_flags`,
  `review_status`, `is_primary`).

## `PATCH /product-assets/{productAsset}`

- **Form Request**: `UpdateProductAssetRequest` — `is_primary`: required, boolean
- **Controller**: `App\Http\Controllers\ProductEnrichment\ProductAssetController@update`
- **Resource modeled**: this *is* a plain attribute update on the asset itself (Cruddy by
  Design — no new resource needed, unlike the approval/rejection/regeneration actions above,
  because there's no branching behavior beyond the one side effect below).
- **Precondition**: target asset's `collection_name = original` (only the original upload can be
  "the" primary photo per FR-002 — variants are derived, not candidates for primary).
- **Behavior**: sets `is_primary = true` on the targeted asset and `is_primary = false` on every
  other `original`-collection asset belonging to the same product (FR-002: "changeable by the
  manager").

## `PATCH /product-attribute-values/{productAttributeValue}`

- **Form Request**: `EditAttributeValueRequest` — `value` required, typed per the attribute's
  `type`
- **Controller**: `App\Http\Controllers\ProductEnrichment\ProductAttributeValueController@update`
- **Resource modeled**: the field's current value — a plain attribute update from the caller's
  perspective (the append-only versioning underneath is a model/observer concern, not a
  controller-level branch, so it legitimately stays `update` rather than becoming its own
  resource).
- **Behavior**: creates a **new** `ProductAttributeValue` row (`origin = human_edit`,
  `confidence_tier = high`, `review_status = approved`, `previous_value_id` = the prior row,
  `is_current = true` on the new row / `false` on the prior). The prior AI-provided row is never
  overwritten (FR-019, FR-022). Records a `ReviewEvent` (`action = edit`).

## `POST /product-attribute-values/{productAttributeValue}/approval`

- **Controller**: `App\Http\Controllers\ProductEnrichment\ProductAttributeValueApprovalController@store`
- **Resource modeled**: an *approval* of this specific field value — a new record, distinct from
  the value itself.
- **Behavior**: sets `review_status = approved`, `approved_by`, `approved_at` on the targeted
  row. Records a `ReviewEvent` (`action = approve`). Independent of every other field on the
  product (US3 AC1).
- **Conflict resolution (US4) is the same action, not a separate one**: if the targeted row's
  `conflict_group_id` is set, approving it also sets every *other* row sharing that
  `conflict_group_id` to `review_status = rejected`, `is_current = false` (retained in history,
  never deleted — US4 AC2). There is no separate "resolve conflict" endpoint — choosing a
  candidate *is* approving it.

## `POST /product-attribute-values/{productAttributeValue}/rejection`

- **Controller**: `App\Http\Controllers\ProductEnrichment\ProductAttributeValueRejectionController@store`
- **Resource modeled**: a *rejection* of this specific field value.
- **Behavior**: sets `review_status = rejected`. Records a `ReviewEvent` (`action = reject`).
  A rejected field is excluded from export (FR-023) until a subsequent edit/regeneration produces
  a new approved version.

## `POST /product-attribute-values/{productAttributeValue}/regenerations`

- **Form Request**: `RegenerateFieldRequest` — `feedback` required, string, max 1,000 chars
- **Controller**: `App\Http\Controllers\ProductEnrichment\ProductAttributeValueRegenerationController@store`
- **Resource modeled**: a *regeneration request* against this field — creating one is what
  triggers the new version, distinct from the value resource itself.
- **Precondition**: target row must be `is_current = true` and not `approved` (FR-022: an
  already-approved field is never affected by another field's regeneration, and regeneration
  never touches an approved row directly)
- **Behavior**: dispatches the `image-variants`/`copy-generation` queue's targeted single-field
  regeneration job with the feedback text and the product's already-established facts only
  (FR-013, US5 AC3 — no new unstated fact may be introduced). On completion, creates a new
  `ProductAttributeValue` row (`origin = ai_generated_copy`, `previous_value_id` = prior row,
  `regeneration_feedback` = submitted text). Records a `ReviewEvent`
  (`action = regenerate`, `feedback_text`).

## `POST /products/{product}/approval`

- **Controller**: `App\Http\Controllers\ProductEnrichment\ProductApprovalController@store`
- **Resource modeled**: an *approval of the whole product* — deliberately a separate resource
  from a single field's approval (a different thing is being created: "this product is approved"
  cascades to many fields, rather than approving one field).
- **Behavior**: sets every currently-`pending` `ProductAttributeValue` on the product to
  `approved` in one transaction (FR-020). Records one `ReviewEvent`
  (`action = approve_product`).

## `GET /bulk-approvals/create`

- **Query params**: `rule` (e.g. `confidence_tier=high`, `category=X`, `attribute_code=Y`)
- **Controller**: `App\Http\Controllers\ProductEnrichment\BulkApprovalController@create`
- **Resource modeled**: this *is* the standard `create` action (the "new resource" form) —
  showing what a bulk approval *would* affect before committing is exactly what `create` is for;
  no separate "preview" verb is needed.
- **Response**: Inertia prop with the exact count of fields the rule would affect (FR-021), no
  state change.

## `POST /bulk-approvals`

- **Form Request**: `BulkApprovalRuleRequest` — `rule` (same shape as above)
- **Controller**: `App\Http\Controllers\ProductEnrichment\BulkApprovalController@store`
- **Behavior**: applies the rule submitted from the `create` form; sets every matched,
  currently-`pending` field to `approved` in one transaction; records one `ReviewEvent`
  (`action = bulk_approve`, `bulk_rule` = the rule json). No field outside the rule is affected
  (FR-021 AC2).

## Controller summary

```text
ProductController                          → show
ProductAttributeValueController              → update
ProductAssetController                       → update  (toggles is_primary, FR-002)
ProductAttributeValueApprovalController      → store   (also resolves conflicts, US4)
ProductAttributeValueRejectionController     → store
ProductAttributeValueRegenerationController  → store
ProductApprovalController                    → store
BulkApprovalController                       → create, store
```
