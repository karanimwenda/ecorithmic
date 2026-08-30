---
paths:
    - 'resources/js/pages/**'
---

# Pages

## Use Inertia Form component with Wayfinder .form() binding

All forms use the Inertia v3 `<Form>` component bound with `v-bind="route.form()"` and `v-slot="{ errors, processing }"`. Do not use `useForm()`.

## Import page prop types from generated.d.ts, not hand-authored files

Every Inertia page MUST receive props typed by a generated Data class from `resources/js/types/generated.d.ts`. Do not declare ad-hoc prop types inline or in hand-authored type files for anything that has a corresponding `app/Data/` class.
