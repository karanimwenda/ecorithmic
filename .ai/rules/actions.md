---
paths:
    - 'app/Actions/**'
---

# Actions

## Actions implement Fortify contracts, not a generic handle() pattern

Action classes in `app/Actions/Fortify/` implement named Fortify contract interfaces (`CreatesNewUsers`, `ResetsUserPasswords`). Use the contract-defined method name. Do not add a generic `handle()` or `__invoke()` method.
