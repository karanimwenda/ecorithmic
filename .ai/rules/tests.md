---
paths:
    - 'tests/**'
---

# Tests

## Use assertInertia with fluent AssertableInertia for page assertions

Assert Inertia responses with `->assertInertia(fn (Assert $page) => $page->component('...')->where('key', value))`. Import `AssertableInertia as Assert`.
