---
name: add-endpoint
description: Add or change a REST endpoint in this Laravel + React workspace app. Use when adding a route, controller action, form request, policy rule, or action to backend/, or when wiring a new API call into the React frontend. Covers the required OpenAPI and test updates that CI enforces.
---

# Adding an API endpoint

This app enforces its own conventions with tests. An endpoint that skips a step
below **fails the suite**, usually in `OpenApiTest`.

Read [PROJECT_SPEC.md](../../../PROJECT_SPEC.md) for the rule you are
implementing. It is the source of truth; cite it in comments as
`(PROJECT_SPEC.md §N)`.

## The pipeline

```
Request → Controller → Form Request → Policy → Action → Model/DB → Activity → API Resource
```

## Checklist

Work through these in order. Skip a layer only for the reason given.

### 1. Route — `backend/routes/api.php`

Put it inside the `Route::middleware('workspace')->scopeBindings()` group unless
the caller is **not yet a member** (invitation acceptance is the only current
exception — it sits outside, because there is no tenant to resolve).

```php
Route::post('/workspaces/{workspace}/things', [ThingController::class, 'store'])->name('things.store');
```

`scopeBindings()` makes a child of another workspace 404 before your code runs.
Do not re-check that yourself.

### 2. Form Request — `backend/app/Http/Requests/<Module>/`

- `authorize()` returns `true` — the policy does the real work in the controller.
- Validate any relation **against the tenant**: take `WorkspaceContext` as a
  `rules()` parameter and reuse the `Concerns/` rule builders.
- Expose a typed accessor (`thingAttributes()`) that resolves enums, so the
  action receives finished values. Do **not** name it `attributes()` —
  `FormRequest` reserves that.

### 3. Policy — `backend/app/Policies/`

Inject `WorkspaceContext`. Use `$context->established()` for collection-level
checks and `$context->owns($record)` for a specific record. Role gates use
`$context->isAdmin()`.

### 4. Action — `backend/app/Actions/<Module>/` *(only if it earns one)*

Write an Action when the operation is **transactional, enforces an invariant, or
records history**. A plain read or a one-field update stays in the controller.

```php
public function handle(Workspace $workspace, User $actor, array $attributes): Thing
{
    return DB::transaction(function () use ($workspace, $actor, $attributes): Thing {
        $thing = new Thing;
        $thing->name = $attributes['name'];      // assign explicitly —
        $workspace->things()->save($thing);      // workspace_id comes from the
                                                 // relationship, never input
        $this->activity->handle($workspace, $actor, ActivityType::ThingCreated, $thing, [...]);

        return $thing;
    });
}
```

Never read `workspace_id` from request input. Ownership keys are not
mass-assignable anywhere.

### 5. Controller — `backend/app/Http/Controllers/Api/V1/`

Authorize, delegate, return a Resource. Keep it short.

```php
public function store(StoreThingRequest $request, CreateThing $action): JsonResponse
{
    $this->authorize('create', Thing::class);

    $thing = $action->handle($this->context->workspace(), $request->user(), $request->thingAttributes());

    return ThingResource::make($thing->load(['relation']))
        ->response()->setStatusCode(Response::HTTP_CREATED);
}
```

**Eager-load every relation the Resource touches** — `preventLazyLoading()` is
on in tests and will fail you otherwise.

### 6. Resource — `backend/app/Http/Resources/`

List fields explicitly. Never spread the model; never expose a credential,
token or hash.

### 7. OpenAPI — `backend/docs/openapi.yaml` ⚠️ not optional

Add the path and every operation. `OpenApiTest` asserts **both** directions:
an undocumented route fails, and a documented-but-unrouted path fails too.

Also update it when you change an **enum** or add a **morph alias** — those are
asserted against the PHP enums and `Relation::morphMap()`.

New morphable model? Register it in `AppServiceProvider::registerMorphMap()`
(`enforceMorphMap` errors on anything unmapped) **and** in the `Activity`
schema's `subject_type` enum.

### 8. Tests — `backend/tests/Feature/<Module>/`

One file per behaviour. Helpers: `workspaceWithAdmin()`, `memberOf($ws, $user, $role)`.

Cover:
- the happy path, per role where the roles differ (use a Pest dataset)
- validation failures → `422`
- **cross-tenant isolation** → `404` (every module needs this)
- the activity row, if the action records one

### 9. Frontend — `frontend/src/`

1. `types/api.ts` — mirror the OpenAPI schema.
2. `features/<domain>/api.ts` — the axios call. **Axios goes nowhere else.**
3. `features/<domain>/hooks.ts` — the TanStack Query hook, with invalidation.
4. Components call the hook. No component makes an HTTP request directly.

## Before you finish

```bash
cd backend  && php artisan test && vendor/bin/pint
cd frontend && npm run typecheck && npm run lint && npm test && npm run build
```

## Status codes used here

| Code | When |
| --- | --- |
| `403` | Signed-in member, wrong role |
| `404` | Missing **or** cross-tenant — identical on purpose, so ids can't be probed |
| `409` | Duplicate-state collision (already a member, already invited) |
| `422` | Validation and business rules, including the last-admin rule |

## Gotchas

- Role wire value is **`user`**, not `'member'` — the UI just labels it "Member".
- Projects **archive**, they don't soft-delete. `DELETE` → archive transition → `204`.
- Activities are append-only; the model throws on `updating`/`deleting`. Write
  them only through `RecordActivity`, from inside an Action.
