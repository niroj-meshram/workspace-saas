# Workspace SaaS — working notes

Multi-tenant team workspace. Laravel REST API (`backend/`) + React SPA
(`frontend/`) in one repo. See [README.md](README.md) to run it.

> **[PROJECT_SPEC.md](PROJECT_SPEC.md) is the source of truth.** Where this
> file, the README, or the code disagree with the spec, the spec wins. Code
> comments cite it as `(PROJECT_SPEC.md §7)` — keep that habit when you add a
> non-obvious rule.

---

## The two rules that break things quietly

**1. Tenancy comes from the route, never from the request body.**
`workspace_id` is not mass-assignable on any model, and no action reads it from
input. The tenant is resolved once by the `workspace` middleware into
`WorkspaceContext`, before any controller or policy runs.

**2. The OpenAPI document is tested against the routes, in both directions.**
Add a route without documenting it in `backend/docs/openapi.yaml` and
`tests/Feature/Documentation/OpenApiTest.php` fails. Same for enum values and
morph aliases. This is deliberate — see that file's header comment.

---

## Backend

### Request pipeline

```
Request → Controller → Form Request → Policy → Action → Model/DB → Activity → API Resource
```

| Layer | Path | Rule |
| --- | --- | --- |
| **Controller** | `app/Http/Controllers/Api/V1/` | Thin. Calls `$this->authorize(...)`, delegates, returns a Resource. Longest method is 15 lines — keep it that way. |
| **Form Request** | `app/Http/Requests/<Module>/` | `authorize()` returns `true`; real authorization is the policy. Exposes a typed `xAttributes()` accessor for the action. |
| **Policy** | `app/Policies/` | Injects `WorkspaceContext`. Use `$context->owns($record)` and `$context->established()`. |
| **Action** | `app/Actions/<Module>/` | Only for operations worth naming: transactional, enforcing an invariant, or recording history. |
| **Resource** | `app/Http/Resources/` | Lists fields **explicitly**. Never spread a model. |

**A plain read or a one-field update does not need an Action** — leave it in the
controller.

**Deliberately absent:** repositories, a service layer, and interfaces with a
single implementation. Don't add them.

### Modules

`Tenancy` · `Projects` · `Tasks` · `Invitations` · `Activity` — the directory
names under `app/Actions/` and `app/Http/Requests/`. Organise by module, not by
framework layer.

### Conventions that bite

| Thing | Convention |
| --- | --- |
| **Role enum** | `WorkspaceRole::Admin = 'admin'`, `WorkspaceRole::User = 'user'`. The **wire value is `user`**; the UI labels it "Member". Don't write `'member'`. |
| **Primary keys** | UUID **v4** via `HasUuidPrimaryKey` (wraps `HasVersion4Uuids`). Not Laravel's `HasUuids`, which is v7. |
| **Projects** | Archive, never soft-delete. `projects` has no `deleted_at`. `DELETE` runs the archive transition and returns `204`. |
| **Activities** | Append-only and enforced — the model throws on `updating`/`deleting`. Write them only from an Action, via `RecordActivity`. |
| **Morph map** | `Relation::enforceMorphMap()` in `AppServiceProvider`. A new morphable model **must** be added there or it errors. |
| **Nested routes** | The workspace group uses `->scopeBindings()`, so a child of another tenant 404s before your code runs. |

### Status codes

`403` wrong role · `404` cross-tenant **or** missing (indistinguishable, on
purpose) · `409` duplicate-state collision · `422` validation and business
rules, including the last-admin rule.

### Testing

Pest. `tests/Feature/<Module>/`, one file per behaviour
(`CreateTaskTest`, `TaskIsolationTest`, …).

- SQLite in-memory by default — **no database needed**.
- `Model::preventLazyLoading()` is on in `tests/TestCase.php`: **any** uneager-
  loaded relation fails the test. Always `->with([...])`.
- Helpers in `tests/Pest.php`: `workspaceWithAdmin()`, `memberOf($ws, $user, $role)`.
- Every module needs an isolation test proving cross-tenant access 404s.

```bash
php artisan test
php artisan test --filter=Tenancy
vendor/bin/pint            # laravel preset, alpha-ordered imports
```

---

## Frontend

```
src/features/<domain>/   api.ts · hooks.ts · components · __tests__
src/pages/               one per route
src/lib/                 axios client, error normalisation, formatting
src/types/api.ts         the API contract in TypeScript
```

- **Axios lives only in `features/*/api.ts`.** Components call TanStack Query
  hooks; none makes an HTTP request directly.
- `src/types/api.ts` mirrors the OpenAPI schemas — update it alongside them.
- shadcn primitives live in `components/ui/`. Shared app UI in `components/`.

```bash
npm run typecheck && npm run lint && npm test && npm run build
```

---

## Local setup gotchas

- **Mail** is Mailpit on `:1025`, inbox at <http://localhost:8025>.
- **Port 5173 is strict.** Sessions are only issued to origins in
  `SANCTUM_STATEFUL_DOMAINS`, so a silent move to 5174 breaks sign-in.
- `VITE_API_URL`, `FRONTEND_URL` and `SANCTUM_STATEFUL_DOMAINS` must name the
  same origin **including the port**.
- Seeded accounts all use the password `password`.
