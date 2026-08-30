---
paths:
    - 'app/Concerns/**'
---

# Concerns

## Shared validation rules live in Concerns traits

Extract reusable validation rules into traits under `app/Concerns/`. Both Form Requests and Fortify Actions import these traits. Do not duplicate rule arrays across the two layers.
