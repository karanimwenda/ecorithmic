---
paths:
    - 'resources/js/**'
---

# Js

## Always use Wayfinder route functions for URLs

Import route functions from `@/routes/*` (Wayfinder-generated). Never hardcode URLs or use a `route()` helper in Vue components.

## Inertia v3 + Vue 3 + shadcn-vue frontend

The frontend is Inertia v3 with Vue 3. All pages live in `resources/js/pages/`. Use shadcn-vue components from `resources/js/components/ui/`. Do not introduce Blade views for application pages.
