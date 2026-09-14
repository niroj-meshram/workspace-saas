# Workspace SaaS

A multi-tenant team workspace. People belong to several workspaces, switch
between them, and manage projects, tasks and teammates inside each one. Every
change is recorded in a permanent activity log.

A Laravel REST API with a React single-page front end.

> The specification this was built against is [PROJECT_SPEC.md](PROJECT_SPEC.md),
> and it remains the source of truth — where this README and the spec disagree,
> the spec wins.

---

## Contents

**Getting it running**
&nbsp;&nbsp;[Quick start](#quick-start) ·
[Requirements](#requirements) ·
[Installation](#installation) ·
[Troubleshooting](#troubleshooting)

**Using it**
&nbsp;&nbsp;[How it's organised](#how-its-organised) ·
[Signing in](#signing-in) ·
[What each role can do](#what-each-role-can-do) ·
[As an admin](#walkthrough-as-an-admin) ·
[As a member](#walkthrough-as-a-member) ·
[Joining by invitation](#walkthrough-joining-by-invitation) ·
[Switching workspaces](#switching-workspaces)

**Reference**
&nbsp;&nbsp;[Everyday commands](#everyday-commands) ·
[Tests](#running-the-tests) ·
[API docs](#api-documentation) ·
[Environment variables](#environment-variables) ·
[Architecture](#architecture) ·
[Security](#security) ·
[Limitations](#known-limitations)

---

## Quick start

Four terminals' worth of setup, once:

```bash
# 1. database
sudo -u postgres psql -c "CREATE USER workspace WITH PASSWORD 'choose-a-password';"
sudo -u postgres createdb -O workspace workspace_saas

# 2. mail catcher (so invitation emails land somewhere you can read)
docker run -d --name mailpit --restart unless-stopped -p 1025:1025 -p 8025:8025 axllent/mailpit

# 3. backend
cd backend
composer install
cp .env.example .env
php artisan key:generate
#   → put your DB_USERNAME / DB_PASSWORD in .env
php artisan migrate
php artisan db:seed
php artisan serve

# 4. frontend (new terminal)
cd frontend
nvm use
npm install
cp .env.example .env
npm run dev
```

Then open **<http://localhost:5173>** and sign in as
`admin@example.com` / `password`.

| | |
| --- | --- |
| App | <http://localhost:5173> |
| API | <http://localhost:8000/api/v1> |
| Mail inbox | <http://localhost:8025> |

---

## Requirements

| | |
| --- | --- |
| PHP | 8.3+ with `pdo_pgsql`, `mbstring`, `xml`, `curl`, `zip` |
| Composer | 2 |
| PostgreSQL | 14+ |
| Node.js | 20.19+ or 22.12+ — `frontend/.nvmrc` pins 24 |
| Docker | optional, only for the mail catcher |

---

## Installation

### 1. Database

Create a role and a database it **owns**:

```bash
sudo -u postgres psql -c "CREATE USER workspace WITH PASSWORD 'choose-a-password';"
sudo -u postgres createdb -O workspace workspace_saas
```

Ownership matters. PostgreSQL 15+ requires the role to own the `public` schema
before it can create tables, and `-O` gives it that. If the database already
exists and somebody else owns it:

```bash
sudo -u postgres psql -d workspace_saas -c "GRANT ALL ON SCHEMA public TO workspace;"
```

Any role name works — use whatever you like and put it in `backend/.env`.

<details>
<summary><strong>If <code>php artisan migrate</code> offers to create the database and then fails anyway</strong></summary>

Laravel offers to create a missing database for you, but it does so as the role
in your `.env`, and a role made with a plain `CREATE USER` has no `CREATEDB`
privilege. Laravel swallows the resulting *"permission denied to create
database"* and retries the original connection, so all you see is the original
*"database … does not exist"*.

Either create the database yourself as above, or grant the privilege so the
prompt works:

```bash
sudo -u postgres psql -c "ALTER ROLE workspace CREATEDB;"
```

Check which it is:

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
```

Open `.env` and set `DB_USERNAME` and `DB_PASSWORD` to match the role you made.
Then:

```bash
php artisan migrate
php artisan db:seed     # optional demo data — see "Signing in"
php artisan serve       # http://localhost:8000
```

Check it: `curl http://localhost:8000/api/v1/health` → `{"status":"ok","version":"v1"}`

### 3. Frontend

In a second terminal:

```bash
cd frontend
nvm use          # honours .nvmrc; Node 20.19+ is required
npm install
cp .env.example .env
npm run dev      # http://localhost:5173
```

`VITE_API_URL` must point at the backend origin, and that origin must appear in
the backend's `FRONTEND_URL` **and** `SANCTUM_STATEFUL_DOMAINS` — otherwise
sign-in appears to succeed but the session never sticks. The defaults already
line up.

### 4. Mail

Invitation emails need somewhere to go. Locally that's **Mailpit**: an SMTP
server with a web inbox that accepts everything and delivers nothing, so no
message can escape your machine.

```bash
docker run -d --name mailpit --restart unless-stopped \
  -p 1025:1025 -p 8025:8025 axllent/mailpit
```

`.env.example` already points at it. Read the mail at **<http://localhost:8025>**.

It catches mail for **any** address, so you can invite
`anything@mailinator.com` and read it in Mailpit — but the message is not
forwarded on and will not appear in the real Mailinator inbox.

Two alternatives, both a `.env` change only:

| Goal | Setting |
| --- | --- |
| Write emails to `storage/logs/laravel.log` | `MAIL_MAILER=log` |
| Deliver for real | a provider — see [Environment variables](#environment-variables) |

---

# Using the product

## How it's organised

```
Workspace ──┬── Members      people, each with a role in this workspace
            ├── Projects     containers for work; active or archived
            │     └── Tasks  the work itself
            └── Activity     a permanent record of what changed
```

**Workspace** — the tenant boundary. Everything belongs to exactly one, and
nothing crosses between them. You can belong to as many as you like.

**Role** — `admin` or `member`, and it is **per workspace**. You can be an admin
of one and a member of another; the interface changes accordingly.

**Project** — groups related tasks. Names are unique within a workspace.
Projects are never deleted, only **archived**: an archived project stays
readable and keeps its tasks and history, but stops accepting new ones.

**Task** — belongs to one project, has a status (`to do`, `in progress`,
`blocked`, `done`), a priority (`low`, `medium`, `high`), an optional assignee
and an optional due date. Deleting a task hides it everywhere but keeps its
history.

**Activity** — written automatically by the operations that cause it. Nothing
can edit or delete it.

---

## Signing in

You need an account. There are three ways to get one:

1. **Register yourself** at <http://localhost:5173/register>. A brand-new
   account belongs to no workspace, so the app asks you to create your first
   one — and you become its admin.
2. **Accept an invitation** — see [Joining by invitation](#walkthrough-joining-by-invitation).
3. **Use the demo data**, if you ran `php artisan db:seed`.

### Demo accounts

`php artisan db:seed` creates two workspaces with projects, tasks across every
status, a pending invitation and a populated activity feed. All three accounts
share the password `password`.

| Email | Acme Product | Internal Tools |
| --- | --- | --- |
| `admin@example.com` | **admin** | member |
| `lead@example.com` | **admin** | **admin** |
| `member@example.com` | member | — |

**Start with `admin@example.com`.** It is an admin in one workspace and a plain
member in the other, so switching workspace shows you both interfaces without
signing out — which is the fastest way to understand the permission model.

The seeder refuses to run twice. To rebuild from scratch:

```bash
php artisan migrate:fresh --seed     # drops every table first
```

---

## What each role can do

Enforced server-side by policies. The interface hides what you can't do, but
that is a convenience — the API refuses it regardless of what the client shows.

| | Admin | Member |
| --- | :---: | :---: |
| View the workspace, its projects, tasks, members and activity | ✓ | ✓ |
| See the dashboard | ✓ | ✓ |
| Create, edit and delete **tasks** | ✓ | ✓ |
| Assign tasks to anyone in the workspace | ✓ | ✓ |
| Create, rename and archive **projects** | ✓ | — |
| Invite people, and withdraw invitations | ✓ | — |
| Change someone's role | ✓ | — |
| Remove someone from the workspace | ✓ | — |
| Rename or delete the workspace | ✓ | — |

Two rules apply to everyone, including admins:

- **A workspace always keeps at least one admin.** The last admin cannot be
  demoted or removed — the menu items are disabled and the API refuses.
- **Archived projects accept no new tasks**, and an existing task cannot be
  moved into one. Tasks already inside stay fully editable.

---

## Walkthrough: as an admin

Sign in as `admin@example.com` and make sure the switcher (top-left) reads
**Acme Product**.

**Create a project.** Go to **Projects → New project**. Give it a name — unique
within this workspace — and an optional description.

**Add work.** Go to **Tasks → New task**. Pick the project, then set status,
priority, assignee and due date as needed. Only the title and project are
required.

**Invite a teammate.** Go to **Members → Invite**, enter their email and choose
whether they join as a member or an admin. They get an email with a link that
works for seven days. The invitation appears under *Pending invitations* until
they accept, and you can **Withdraw** it at any time.

**Change someone's role or remove them.** On the **Members** page, use the `…`
menu on their row. Removing someone revokes their access immediately and
**unassigns every task they held in this workspace** — their tasks stay, they
just become unassigned. Their history remains in the activity feed, and you can
invite them back.

**Archive a project.** On **Projects**, use the `…` menu → *Archive*. Nothing is
deleted: the project stays readable with all its tasks and history, it simply
stops accepting new tasks. Open the project and choose *Make active* to reverse
it.

**Rename or delete the workspace.** Under **Settings**. Deleting hides the
workspace from everyone; nothing underneath it is erased.

---

## Walkthrough: as a member

Sign in as `member@example.com`, or as `admin@example.com` and switch to
**Internal Tools**.

The **Projects** page has no *New project* button and no `…` menus. The
**Members** page shows who's who but offers no controls, and there is no
*Pending invitations* section. **Settings** shows the workspace name as plain
text.

What you *can* do is the work itself:

- **Create and edit tasks** in any project in the workspace.
- **Change status and priority** — open a task from the list and save.
- **Assign tasks** to anyone who is an active member here.
- **Delete tasks** you no longer need, with a confirmation step.
- **Filter and search** the task list by project, status, priority, assignee or
  free text, and sort by title, due date or when it was created or updated.
  Filters live in the URL, so a filtered view can be bookmarked or shared.
- **See the dashboard and activity feed** exactly as an admin does.

---

## Walkthrough: joining by invitation

This is what someone with no account experiences.

**1 — The email arrives.** Open <http://localhost:8025> and find *"You've been
invited to join …"*. It names the workspace, who invited you, the role, and the
email address you must use.

**2 — Click *Accept invitation*.** Because you're not signed in, the app takes
you to the sign-in screen and explains why you're there. The invitation is
remembered throughout.

**3 — Create an account.** Follow *Create an account* and register **with the
address the invitation was sent to**. This is the one rule that matters:
invitations are tied to their address, and signing up with a different one gives
you *"This invitation is for someone else"*.

**4 — You're in.** As soon as the account exists you're returned to the
invitation, it's accepted automatically, and you land in the workspace with the
role the admin chose.

If you **already have an account** on that address, sign in instead at step 3 —
same outcome.

Invitations last **seven days**. After that the link reports that it has
expired, and an admin can simply send a new one. A link that has already been
used, or been withdrawn, says so too.

---

## Switching workspaces

The switcher sits at the top of the sidebar. It lists every workspace you're an
active member of, and **New workspace** creates another with you as its admin.

Your choice is remembered between visits. If you lose access to the remembered
workspace, the app quietly falls back to one you still belong to.

Switching changes what you see *and* what you may do — the same account can be
an admin in one workspace and a member in the next. Nothing from one workspace
is ever visible in another.

---

# Reference

## Everyday commands

```bash
# backend
php artisan serve                  # run the API
php artisan migrate                # apply new migrations
php artisan migrate:fresh --seed   # wipe and rebuild with demo data
php artisan db:seed                # add demo data to an existing database
php artisan route:list             # every registered route
vendor/bin/pint                    # format PHP

# frontend
npm run dev                        # dev server
npm run build                      # production build
npm run typecheck                  # TypeScript
npm run lint                       # oxlint
npm run format                     # Prettier
```

## Running the tests

```bash
# backend
cd backend
php artisan test                   # whole Pest suite
php artisan test --filter=Tenancy  # one area
vendor/bin/pint --test             # formatting check

# frontend
cd frontend
npm run typecheck && npm run lint && npm test && npm run build
```

Backend tests run against **SQLite in-memory** by default (configured in
`phpunit.xml`), so they need no database and never touch your PostgreSQL. To run
the same suite against PostgreSQL, point it at a **separate** database —
`RefreshDatabase` drops every table:

```bash
DB_CONNECTION=pgsql DB_DATABASE=workspace_saas_test php artisan test
```

Two guards run inside every test:

- **`Model::preventLazyLoading()`** — any relation read without being eager
  loaded fails the test. The feature suite covers every endpoint, which is what
  keeps N+1 queries out.
- **The OpenAPI document is tested against the routes**, the enums and the morph
  map, so documentation cannot drift from the code.

## API documentation

[`backend/docs/openapi.yaml`](backend/docs/openapi.yaml) — OpenAPI 3.1 covering
authentication, workspaces, projects, tasks, members, invitations, activities
and the dashboard, with request and response examples.

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

### Calling the API directly

Authentication is a session cookie, not a token. A client must:

1. `GET /sanctum/csrf-cookie` once, to receive the `XSRF-TOKEN` cookie.
2. Send that cookie's value in an `X-XSRF-TOKEN` header on every mutating
   request, with credentials included.
3. Send an `Origin` header from a host listed in `SANCTUM_STATEFUL_DOMAINS` —
   browsers do this automatically, **curl and Postman do not** and will not get
   a session without it.

## Environment variables

`.env.example` is committed in both apps and is complete. `.env` is git-ignored
and no secrets are committed.

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
| `MAIL_MAILER` | `smtp` | Mailpit locally |
| `MAIL_HOST` / `MAIL_PORT` | `127.0.0.1` / `1025` | Mailpit's SMTP listener |

For real email delivery, Laravel has `postmark`, `resend`, `ses` and `smtp`
built in. It needs credentials and the provider's transport package, but no
application code changes:

```bash
composer require symfony/postmark-mailer
```

```
MAIL_MAILER=postmark
POSTMARK_TOKEN=…
MAIL_FROM_ADDRESS="invitations@yourdomain.com"
```

### `frontend/.env`

| Variable | Local value |
| --- | --- |
| `VITE_API_URL` | `http://localhost:8000` |

---

## Architecture

### Modular monolith

One deployable application, organised by business capability rather than by
framework layer. The modules — **Tenancy**, **Projects**, **Tasks**,
**Invitations**, **Activity** — are visible in the directory names:

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
│   ├── Controllers/   thin; the longest method is 15 lines
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

**Actions** hold the operations worth naming — transactional, enforcing an
invariant, or recording history. A plain read or a one-field update stays in the
controller. There are deliberately **no repositories, no service layer and no
interfaces with a single implementation**.

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
none makes an HTTP request directly.

### Multi-tenancy

A shared database; every tenant-owned row carries a `workspace_id`. Tenant
resolution is centralised in one middleware and runs before any controller or
policy:

```
authenticated user
   → resolve the workspace from the route
   → verify an active membership
   → establish the WorkspaceContext
   → policies authorize against that context
   → the action runs
```

Three things follow, and they are what make the isolation hold:

**A `workspace_id` in a request body is never read.** The workspace comes from
the route, always. Ownership keys are not mass-assignable on any model.

**An inaccessible tenant resource is indistinguishable from a missing one.**
Both answer `404` with the same body, so the API cannot be used to discover
which ids exist. Nested route parameters resolve *through* the workspace
relationship, so another tenant's record is a 404 before application code runs.

**The database enforces what it can.** A composite foreign key on
`tasks (project_id, workspace_id) → projects (id, workspace_id)` makes a task in
one workspace referencing a project in another impossible to write, even if the
application layer were wrong.

### Authentication

Laravel Sanctum in SPA mode: an HTTP-only session cookie with CSRF protection.
No JWT, no bearer tokens — the `personal_access_tokens` table is deliberately
absent. The session id is rotated on login and invalidated on logout.

Registration creates the account **only** — no session and no workspace — which
is why a new account lands on "create your first workspace".

### Notable decisions

**UUID v4, not v7.** Laravel's `HasUuids` switched to v7, so
`HasUuidPrimaryKey` wraps `HasVersion4Uuids` instead — an *ordered* v4, keeping
the RFC version nibble at 4 while giving B-tree locality on high-insert tables.

**Projects archive; they do not soft delete.** `archived ≠ deleted`, so
`projects` has no `deleted_at`. `DELETE` runs the same archive transition as
`PATCH {"status":"archived"}` and returns `204`.

**`404` for cross-tenant, `403` for wrong role.** `409` is for duplicate-state
collisions (already a member, already invited, already accepted, expired); `422`
for validation and business rules, including the last-admin rule.

**Last-admin protection locks the whole admin set.** Two concurrent demotions
could each see the other as a survivor and both commit. The guard takes a
`FOR UPDATE` lock on *every* admin membership, so the second transaction
re-reads after the first commits and is refused.

**Invitation tokens exist in one place.** 256 bits from `random_bytes`, stored
only as a SHA-256 digest, emailed as the raw value, absent from every API
response. SHA-256 rather than bcrypt is deliberate: the token is already
high-entropy, so it needs a deterministic digest that can be found by index.

**Activities are append-only, and enforced.** The model throws on `updating` and
`deleting`, nothing is mass-assignable, no route writes one, and both foreign
keys are `ON DELETE RESTRICT`.

---

## Security

Covered, and covered by tests:

- **Cross-tenant access** — reading, updating and deleting another workspace's
  records across every module.
- **Cross-tenant relationships** — a task cannot take a project or an assignee
  from another workspace; the composite foreign key blocks it at the database
  even if validation were bypassed.
- **Assignee validation** — must be an *active* member of the same workspace.
- **Mass assignment** — `workspace_id`, `user_id`, `role`, `project_id` and
  `assignee_id` are fillable nowhere.
- **Last-admin protection** — cannot demote or remove the final admin, verified
  under real concurrency.
- **Invitation security** — CSPRNG token, hash-only storage, 7-day expiry,
  one-time acceptance, and the accepting account's email must match.
- **Soft deletes** — removing a member revokes access immediately and unassigns
  their tasks in that workspace only; deleted tasks vanish from listings while
  their history remains.
- **Credentials** — password hashes never leave the database; the user resource
  lists its fields explicitly rather than spreading the model.
- **Rate limiting** — login 5/min per email+IP plus 20/min per IP; registration
  5/min per IP.
- **Session hardening** — HTTP-only cookie, `SameSite=Lax`, CSRF on every
  mutating request, session rotated on login and invalidated on logout.
- **Enumeration** — identical messages whether or not an account exists;
  identical 404s whether or not a record exists.

---

## Troubleshooting

**"Port 5173 is already in use."** The port is deliberately strict — the backend
only issues session cookies to origins in `SANCTUM_STATEFUL_DOMAINS`, so a
silent move to 5174 would break sign-in confusingly. Find and stop whatever
holds it:

```bash
ss -ltnp | grep ':5173'
kill $(ss -ltnp | grep ':5173' | grep -oE 'pid=[0-9]+' | cut -d= -f2)
```

`pkill -f vite` will not match it — the process reports its name as
`MainThread`.

**Sign-in succeeds but you're immediately signed out.** `VITE_API_URL`,
`FRONTEND_URL` and `SANCTUM_STATEFUL_DOMAINS` disagree. All three must name the
same origin, including the port.

**`419` on every write.** The CSRF cookie is missing. The app calls
`/sanctum/csrf-cookie` automatically; a manual client must do it first.

**No invitation email.** Check Mailpit is running (`docker ps`) and that
`MAIL_PORT=1025`. With `MAIL_MAILER=log` the message is in
`backend/storage/logs/laravel.log` instead.

**"This invitation is for someone else."** You're signed in as a different
address from the one invited. Sign out and use the invited address.

**A seeded account won't sign in.** All seeded accounts use the password
`password`. If you ran the seeder before, `php artisan db:seed` refuses to run
again — use `php artisan migrate:fresh --seed` to rebuild.

---

## Known limitations

Deliberately out of scope, per the specification: billing, subscriptions, chat,
file uploads, a notifications system, microservices, cloud infrastructure,
Terraform, Redis, GraphQL, advanced reporting, and any permission model beyond
the two workspace roles.

Also not built, and worth knowing:

- **No CI pipeline.** The spec parks it; the commands above are what a pipeline
  would run.
- **No password reset and no email verification.** The specified auth surface is
  register, login, logout and me. The `password_reset_tokens` table exists
  unused.
- **No "remember me"** — the model disables the remember-token mechanism rather
  than leaving it half-wired.
- **Invitation details are not readable before sign-in.** The link is public,
  but there is no public endpoint to preview which workspace it is for, so the
  invitee sees the workspace name only after accepting.
- **Tasks cannot be sorted by status or priority.** Both would sort lexically —
  priority would order *high, low, medium* — so they are left out of the
  whitelist rather than shipped subtly wrong.
- **Activity subjects are returned as a type and an id**, not embedded, so a
  feed showing a task's title relies on what the event recorded in its metadata.
- **Description fields are capped at 5,000 characters.** The specification sets
  no limit; the bound is a choice.
- **The demo seeder shares one known password** across its accounts — safe
  locally, unsafe anywhere exposed.
