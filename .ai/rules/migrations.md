---
paths:
    - 'database/migrations/**'
---

# Migrations

## Use DB enum() columns for state/status fields

Store enumerated state fields as MySQL `enum()` columns in migrations. Use a PHP-backed enum cast on the model.
