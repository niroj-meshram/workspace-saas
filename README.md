# Workspace SaaS

A multi-tenant team workspace: people belong to several workspaces, switch
between them, and manage projects, tasks and teammates inside each one. Every
change is recorded in a permanent activity log.

Built as a Laravel REST API with a React single-page front end.

The specification this was built against is [PROJECT_SPEC.md](PROJECT_SPEC.md),
and it remains the source of truth — where this README and the spec disagree,
the spec wins.

---

## Contents

- [Tech stack](#tech-stack)
- [Architecture](#architecture)
- [Multi-tenancy](#multi-tenancy)
- [Authentication](#authentication)
- [Local setup](#local-setup)
- [Environment variables](#environment-variables)
- [Running the tests](#running-the-tests)
- [API documentation](#api-documentation)
- [Architectural decisions](#architectural-decisions)
- [Security](#security)
- [Known limitations](#known-limitations)

---

## Tech stack

| | |
| --- | --- |
| **Backend** | Laravel 13, PHP 8.4, PostgreSQL 16, Laravel Sanctum, Pest, Pint |
| **Frontend** | React 19, TypeScript, Vite, Tailwind CSS v4, shadcn/ui, TanStack Query, React Router, Axios, React Hook Form, Zod |
| **Testing** | Pest (backend), Vitest + React Testing Library (frontend) |
| **IDs** | Native PostgreSQL `uuid`, UUID v4 throughout |

```
workspace-saas/
├── backend/     Laravel REST API
├── frontend/    React SPA
└── README.md
```

---

## Architecture

### Modular monolith

One deployable application, organised by business capability rather than by
framework layer. The modules are **Tenancy**, **Projects**, **Tasks**,
**Invitations** and **Activity**, and they are visible in the directory names:

```
backend/app/
├── Actions/           business operations, grouped by module
│   ├── Tenancy/       CreateWorkspace, ChangeMemberRole, RemoveMember
│   ├── Projects/      CreateProject, UpdateProject, ArchiveProject
│   ├── Tasks/         CreateTask, UpdateTask, DeleteTask
│   ├── Invitations/   InviteMember, AcceptInvitation, RevokeInvitation
│   └── Activity/      RecordActivity
├── Tenancy/           WorkspaceContext — the resolved tenant
├── Dashboard/         the one read model that spans modules
├── Http/
│   ├── Middleware/    ResolveWorkspace
│   ├── Controllers/   thin; longest method is 15 lines
│   ├── Requests/      validation, grouped by module
│   └── Resources/     API output
├── Policies/          authorization
├── Models/            Eloquent + relationships
└── Enums/             roles, statuses, priorities, activity types
```

A request flows exactly as the specification lays out:

```
Request → Controller → Form Request → Policy → Action → Model/DB → Activity → API Resource
```

**Actions** hold the operations worth naming — the ones that are transactional,
enforce an invariant, or record history. A plain read or a one-field update
stays in the controller; wrapping it in a class would add a file and explain
nothing.

There are deliberately **no repositories, no service layer and no interfaces
with a single implementation**. Eloquent is the data layer.

### Frontend

```
frontend/src/
├── features/      one folder per domain: api.ts, hooks.ts, components
├── pages/         one per route
├── layouts/       app shell and the signed-out shell
├── components/    shared UI, with ui/ holding the shadcn primitives
├── lib/           axios client, error normalisation, formatting
└── types/         the API contract in TypeScript
```

Axios lives only in `features/*/api.ts`. Components call TanStack Query hooks;
none of them makes an HTTP request directly.

---

## Multi-tenancy

A shared database. Every tenant-owned row carries a `workspace_id`.

Tenant resolution is centralised in one middleware, and runs before any
controller or policy:

```
authenticated user
   → resolve the workspace from the route
   → verify an active membership
   → establish the WorkspaceContext
   → policies authorize against that context
   → the action runs
```

Three things follow from that, and they are what make the isolation hold:

**A `workspace_id` in a request body is never read.** The workspace comes from
the route, always. Ownership keys are not mass-assignable on any model;
actions set them explicitly.

**An inaccessible tenant resource is indistinguishable from a missing one.**
Both answer `404` with the same body, so the API cannot be used to discover
which workspace, project or task ids exist. Nested route parameters are
resolved *through* the workspace relationship, so a record belonging to
somebody else is a 404 before application code runs.

**The database enforces what it can.** A composite foreign key on
`tasks (project_id, workspace_id) → projects (id, workspace_id)` makes a task
in one workspace referencing a project in another impossible to write, even if
the application layer were wrong.

There is no scattered `where('workspace_id', …)`. Queries start from the
resolved workspace's relationships.

---

## Authentication

Laravel Sanctum, SPA mode: an HTTP-only session cookie with CSRF protection.
No JWT, no bearer tokens — the `personal_access_tokens` table is deliberately
absent.

A browser client:

1. calls `GET /sanctum/csrf-cookie` once,
2. sends the `XSRF-TOKEN` cookie value back in an `X-XSRF-TOKEN` header on
   every mutating request, with `withCredentials: true`.

Sanctum only starts a session for requests whose `Origin`/`Referer` is listed
in `SANCTUM_STATEFUL_DOMAINS`. Browsers send that header automatically; a
non-browser client (curl, Postman) must set `Origin` explicitly or it will not
get a session.

Registration creates the account **only** — no session and no workspace. The
client signs in as a separate step, which is why a new account lands on "create
your first workspace".

---

## Local setup

### Requirements

- PHP 8.3+ with `pdo_pgsql`, `mbstring`, `xml`, `curl`, `zip`
- Composer 2
- PostgreSQL 14+
- Node.js 20.19+ or 22.12+ — `frontend/.nvmrc` pins Node 24

### 1. Database

Create a role and a database it owns:

```bash
sudo -u postgres psql -c "CREATE USER workspace WITH PASSWORD 'choose-a-password';"
sudo -u postgres createdb -O workspace workspace_saas
```

Creating the database with `-O` matters: the role then owns the `public`
schema too, which PostgreSQL 15+ requires for it to create tables. If the
database already exists and is owned by someone else, grant that separately:

```bash
sudo -u postgres psql -d workspace_saas -c "GRANT ALL ON SCHEMA public TO workspace;"
```

Any role name works — put whatever you use into `backend/.env`.

<details>
<summary><strong>If <code>php artisan migrate</code> offers to create the database and then fails anyway</strong></summary>

Laravel offers to create a missing database for you, but it does so as the
role in your `.env`, and a role made with plain `CREATE USER` has no
`CREATEDB` privilege. Laravel swallows the resulting "permission denied to
create database" error and retries the original connection, so what you see
is only the original *"database ... does not exist"*.

Either create the database yourself as above, or grant the privilege so the
prompt works:

```bash
sudo -u postgres psql -c "ALTER ROLE workspace CREATEDB;"
```

Check which it is with:

```bash
psql -h 127.0.0.1 -U workspace -d postgres -c "\du workspace"
```
</details>

### 2. Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
# set DB_USERNAME / DB_PASSWORD in .env to match the role above
php artisan migrate
php artisan db:seed          # optional — demo data, see below
php artisan serve            # http://localhost:8000
```

Sanity check: `curl http://localhost:8000/api/v1/health` → `{"status":"ok","version":"v1"}`

#### Demo data

`php artisan db:seed` creates two workspaces with projects, tasks across every
status, a pending invitation and a populated activity feed — enough to see
every screen with something in it.

| Email | Password | Acme Product | Internal Tools |
| --- | --- | --- | --- |
| `admin@example.com` | `password` | admin | member |
| `lead@example.com` | `password` | admin | admin |
| `member@example.com` | `password` | member | — |

Sign in as `admin@example.com` and switch workspace to see the same account
with admin controls in one and without them in the other.

The seeder builds everything through the same actions the API uses, so the
business rules hold and the activity feed is populated as a side effect rather
than faked. It refuses to run twice; to rebuild from scratch:

```bash
php artisan migrate:fresh --seed
```

The invitation it leaves pending is addressed to `example.com`, a domain
reserved by RFC 2606, so it can never reach a real inbox even with a live mail
provider configured. **The accounts share a known password — seed a database
that is exposed to anyone else and you have handed them the keys.**

### 3. Frontend

In a second terminal:

```bash
cd frontend
nvm use                      # honours .nvmrc
npm install
cp .env.example .env
npm run dev                  # http://localhost:5173
```

Open http://localhost:5173, create an account, and create your first
workspace.

### Invitation emails

Locally, mail goes to **Mailpit** — an SMTP server with a web inbox that
accepts everything and delivers nothing, so no message can escape your
machine. Start it once:

```bash
docker run -d --name mailpit --restart unless-stopped \
  -p 1025:1025 -p 8025:8025 axllent/mailpit
```

`.env.example` already points at it (`MAIL_MAILER=smtp`, port `1025`). Invite
somebody, then open **<http://localhost:8025>** and click *Accept invitation*
in the message — the whole flow works without leaving your laptop.

It catches mail for **any** address, so you can invite
`anything@mailinator.com` and read it in Mailpit. Note that the message is
*not* forwarded on: it will not appear in the real Mailinator inbox.

Two alternatives, both a `.env` change only:

| Goal | Setting |
| --- | --- |
| Write emails to `storage/logs/laravel.log` | `MAIL_MAILER=log` |
| Actually deliver, to a temp inbox or anywhere else | a provider, below |

Laravel has `postmark`, `resend`, `ses` and `smtp` built in, so real delivery
needs credentials and the provider's transport package, but no application
code changes:

```
MAIL_MAILER=postmark
POSTMARK_TOKEN=…
MAIL_FROM_ADDRESS="invitations@yourdomain.com"
```

```bash
composer require symfony/postmark-mailer   # or resend/resend-php, etc.
```

---

## Environment variables

`.env.example` is committed in both apps and is complete. `.env` is git-ignored
and **no secrets are committed**.

### `backend/.env`

| Variable | Local value | Why it matters |
| --- | --- | --- |
| `APP_URL` | `http://localhost:8000` | |
| `FRONTEND_URL` | `http://localhost:5173` | CORS origin, and the base for invitation links |
| `DB_CONNECTION` | `pgsql` | |
| `DB_HOST` / `DB_PORT` | `127.0.0.1` / `5432` | |
| `DB_DATABASE` | `workspace_saas` | |
| `DB_USERNAME` / `DB_PASSWORD` | your role | |
| `SANCTUM_STATEFUL_DOMAINS` | `localhost:5173,127.0.0.1:5173` | Hosts that receive a session cookie |
| `SESSION_DOMAIN` | `localhost` | Must cover both ports |
| `SESSION_DRIVER` | `database` | Needs the `sessions` table |
| `SESSION_SECURE_COOKIE` | `false` | Set `true` once served over HTTPS |
| `MAIL_MAILER` | `smtp` | Invitation email transport — Mailpit locally |
| `MAIL_HOST` / `MAIL_PORT` | `127.0.0.1` / `1025` | Mailpit's SMTP listener |

### `frontend/.env`

| Variable | Local value |
| --- | --- |
| `VITE_API_URL` | `http://localhost:8000` |

`VITE_API_URL` must be an origin the backend lists in `FRONTEND_URL` **and**
`SANCTUM_STATEFUL_DOMAINS`, or sign-in will not stick.

---

## Running the tests

### Backend

```bash
cd backend
php artisan test              # the whole Pest suite
php artisan test --filter=Tenancy
vendor/bin/pint --test        # formatting check
vendor/bin/pint               # fix formatting
```

Tests run against **SQLite in-memory** by default (configured in
`phpunit.xml`), so they need no database setup. To run the same suite against
PostgreSQL:

```bash
DB_CONNECTION=pgsql DB_DATABASE=workspace_saas_test php artisan test
```

Two things the suite enforces beyond ordinary assertions:

- **`Model::preventLazyLoading()`** is on for every test, so any relation read
  without being eager loaded fails. The feature tests cover every endpoint,
  which is what keeps N+1 queries out.
- **The OpenAPI document is tested against the routes** — an endpoint that is
  added, removed or renamed without updating `docs/openapi.yaml` fails the
  suite, as does an enum that drifts.

### Frontend

```bash
cd frontend
npm run typecheck
npm run lint
npm test                      # Vitest, one pass
npm run test:watch
npm run build                 # production build
npm run format:check
```

---

## API documentation

[`backend/docs/openapi.yaml`](backend/docs/openapi.yaml) — OpenAPI 3.1,
covering authentication, workspaces, projects, tasks, members, invitations,
activities and the dashboard, with request and response examples.

View it with any OpenAPI tool, for example:

```bash
npx @redocly/cli preview-docs backend/docs/openapi.yaml
```

or paste it into <https://editor.swagger.io>.

### Conventions

- Base path `/api/v1`; all ids are UUID v4.
- A single resource is wrapped in `data`; a collection is a `data` array.
- Paginated collections add Laravel's `links` and `meta`. Default 20 per page,
  capped at 100 via `per_page`.

| Status | Meaning here |
| --- | --- |
| `200` | Read or update succeeded |
| `201` | Created |
| `204` | Succeeded, nothing to return |
| `401` | No valid session |
| `403` | Signed in and a member, but not allowed to do this |
| `404` | No such record — **or** one you may not reach |
| `409` | Conflicts with the resource's current state |
| `422` | Validation or business-rule failure |
| `419` | CSRF token missing or stale |
| `429` | Rate limited |

---

## Architectural decisions

**UUID v4, not v7.** The spec calls for v4. Laravel's `HasUuids` switched to
v7, so `HasUuidPrimaryKey` wraps `HasVersion4Uuids` instead — an *ordered* v4,
which keeps the RFC version nibble at 4 while giving B-tree locality on
high-insert tables such as `activities`.

**Projects archive; they do not soft delete.** The spec is explicit that
`archived ≠ deleted`, so `projects` has no `deleted_at`. `DELETE` on a project
runs the same archive transition as `PATCH {"status":"archived"}`, returns
`204`, and the row stays readable with its tasks and history intact. Archived
projects refuse *new* tasks, including a task moved into one; a task already
there stays editable.

**`404` for cross-tenant, `403` for wrong role.** The spec maps 404 to "not
found / inaccessible tenant resource". Every API 404 shares one body, so a
real-but-inaccessible id is indistinguishable from a fictional one — tested by
comparing the two responses byte for byte.

**`409` for conflicts, `422` for rules.** Duplicate-state collisions (already a
member, already invited, already accepted, expired) are `409`. Validation and
business-rule failures — including "a workspace must always have at least one
admin" — are `422` with field-level errors the form can render.

**Last-admin protection locks the whole admin set.** Two concurrent demotions
could each see the other as a surviving admin and both commit. The guard takes
a `FOR UPDATE` lock on *every* admin membership, not just the others, so the
second transaction re-reads after the first commits and is refused. Verified
with parallel processes against PostgreSQL.

**Invitation tokens exist in exactly one place.** 256 bits from
`random_bytes`, stored only as a SHA-256 digest, emailed as the raw value, and
absent from every API response including the one that creates the invitation.
SHA-256 rather than bcrypt is deliberate: the token is already high-entropy, so
it needs a deterministic digest that can be found by index.

**Activities are append-only, and enforced.** The model throws on `updating`
and `deleting`, nothing is mass-assignable, no route writes one, and both
foreign keys are `ON DELETE RESTRICT`. History survives a soft-deleted subject
because the polymorphic relation includes trashed records.

**Email delivery cannot corrupt an invitation.** The mail is sent after the
transaction commits, and a delivery failure is logged rather than thrown — a
provider outage must not roll back an invitation that already exists, nor
return a 500 for it.

**One shared tenant context, not a scoping framework.** `WorkspaceContext` is
request-scoped and set once by the middleware. Policies read it; queries start
from the workspace's relationships. That is the whole mechanism.

**The frontend knows roles for UX only.** The workspace payload carries the
caller's own `role` so the UI can hide controls it may not use, and it refuses
to offer last-admin operations that would fail. Every one of those decisions is
re-made by a policy on the server.

---

## Security

Covered, and covered by tests:

- **Cross-tenant access** — reading, updating and deleting another workspace's
  workspace, projects, tasks, members, invitations, activity and dashboard.
- **Cross-tenant relationships** — a task cannot take a project or an assignee
  from another workspace; the composite foreign key blocks it at the database
  even if validation were bypassed.
- **Assignee validation** — must be an *active* member of the same workspace.
- **Mass assignment** — `workspace_id`, `user_id`, `role`, `project_id` and
  `assignee_id` are not fillable anywhere; actions assign them explicitly.
- **Last-admin protection** — cannot demote or remove the final admin, under
  concurrency.
- **Invitation security** — CSPRNG token, hash-only storage, 7-day expiry,
  one-time acceptance, and the accepting account's email must match.
- **Soft deletes** — removing a member revokes access immediately, and the
  person can be invited back; deleted tasks vanish from every listing while
  their history remains.
- **Credentials** — password hashes never leave the database; the user resource
  lists its fields explicitly rather than spreading the model.
- **Rate limiting** — login is 5/min per email+IP plus 20/min per IP;
  registration is 5/min per IP.
- **Session hardening** — HTTP-only cookie, `SameSite=Lax`, CSRF on every
  mutating request, session id rotated on login, session invalidated on logout.
- **Enumeration** — identical failure messages whether or not an account
  exists; identical 404s whether or not a record exists.

---

## Known limitations

Deliberately out of scope, per the specification:

Billing, subscriptions, chat, file uploads, a notifications system,
microservices, cloud infrastructure, Terraform, Redis, GraphQL, advanced
reporting, and any permission model beyond the two workspace roles.

Also not built, and worth knowing:

- **No CI pipeline.** The spec parks it; the commands above are what a pipeline
  would run.
- **Removing a member does not yet unassign their tasks.** The spec calls for
  it (§8, §18) and `RemoveMember` has the transaction seam ready, but the step
  is not implemented. Their tasks keep pointing at them until reassigned.
- **Password reset is not implemented.** The spec's auth surface is register,
  login, logout and me. The `password_reset_tokens` table exists unused.
- **No email verification**, and no "remember me" — the model disables the
  remember-token mechanism rather than leaving it half-wired.
- **Invitation details are not readable before sign-in.** The link is public,
  but there is no public endpoint to preview which workspace it is for, so the
  invitee sees the workspace name only after accepting.
- **Tasks cannot be sorted by status or priority.** Both would sort
  lexically — priority would order *high, low, medium* — so they are left out
  of the whitelist rather than shipped subtly wrong.
- **Activity subjects are returned as a type and an id**, not embedded, so a
  feed showing a task's title relies on what the event recorded in its
  metadata.
- **Description fields are capped at 5,000 characters.** The spec sets no
  limit; the bound is a choice.
