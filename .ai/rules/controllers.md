---
paths:
    - 'app/Http/Controllers/**'
---

# Controllers

## No repository layer — query Eloquent directly in controllers

Controllers query Eloquent models directly. Do not introduce repository or query-object abstractions.

## Use Inertia::flash() for success feedback

Signal success to the frontend with `Inertia::flash('toast', ['type' => 'success', 'message' => __('...')])`. Do not pass status flags as Inertia props or via session flash directly.

## Use to_route() for named route redirects

Use the `to_route('route.name')` helper for redirects to named routes. Do not use `redirect()->route('route.name')`.

## Cruddy by Design — only 7 RESTful actions per controller

Every controller MUST expose only the seven standard RESTful actions: index, show, create/store, edit/update, destroy. Never add a custom action (subscribe, publish, approve, archive, etc.). When you need a non-standard action, create a new controller named after the resource being created/updated/destroyed. A controller with a single action MUST be invokable (`__invoke`). State changes ("published", "archived") are resources too — model them as their own controller. One controller = one resource = one set of RESTful operations. See Principle IX of the constitution.
