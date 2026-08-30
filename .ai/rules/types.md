---
paths:
    - 'resources/js/types/**'
---

# Types

## Never hand-author TypeScript types for page props or shared data

TypeScript types for Data classes and Enums are generated exclusively by `php artisan typescript:transform` into `resources/js/types/generated.d.ts`. Do NOT write or edit type definitions that duplicate generated types. Run `composer run transform-types` to regenerate after changing `app/Data/` or `app/Enums/`.
