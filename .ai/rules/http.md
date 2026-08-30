---
paths:
    - 'app/Http/**'
---

# Http

## Form Requests for controller validation

Use Form Request classes for all controller input validation. Do not use `$request->validate()` inline in controllers. `Validator::make()` is reserved for Fortify Action classes that implement framework contracts.
