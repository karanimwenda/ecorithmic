---
paths:
    - 'resources/js/pages/**'
---

# Pages

## Use Inertia Form component with Wayfinder .form() binding

All forms use the Inertia v3 `<Form>` component bound with `v-bind="route.form()"` and `v-slot="{ errors, processing }"`. Do not use `useForm()`.
