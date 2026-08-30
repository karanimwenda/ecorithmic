---
paths:
    - 'app/Data/**'
---

# Data

## Data classes replace API Resources for Inertia responses

Use `Spatie\LaravelData\Data` subclasses in `app/Data/` for all Inertia response payloads. Annotate each class `#[TypeScript]`. Do NOT create `JsonResource` classes for shaping Inertia props. Controllers MUST pass a single typed Data object to `Inertia::render()`, never a raw array. Form Requests are a separate concern and still own input validation.
