---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Cruddy by Design — controllers only expose the 7 standard REST actions
Every controller must expose only index/show/create/store/edit/update/destroy. Never add a custom verb method (approve, publish, confirm, download, regenerate, etc.) to a controller.

When tempted to add one, name the resource being created/updated/destroyed and give it its own controller instead:
- "Confirming" an import → ImportConfirmationController@store, not ImportController@confirm.
- "Approving" a field → ProductAttributeValueApprovalController@store, not a custom approve() method. Conflict resolution folds into the same approval (approving one candidate rejects its siblings as a side effect) rather than a separate resolve() action.
- "Downloading an artifact" → ExportArtifactController@show (GET /exports/{export}/artifacts/{artifact}), not ExportController@download.
- Previewing what a bulk action would affect → the standard create action (GET .../create), not a custom preview() method.
- Only fold a state change into update if it stays a plain attribute change with no special-cased branching; if it has real side effects (dispatches jobs, cascades to other records), give it its own resource/controller.

Prefer more small, single-purpose controllers over fewer large ones with special-cased actions. See specs/001-ai-product-enrichment/contracts/*.md for worked examples.
