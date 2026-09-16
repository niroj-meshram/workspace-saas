# Workspace SaaS

A **multi-tenant team workspace**. People belong to several workspaces, switch
between them, and manage projects, tasks and teammates inside each one. Every
change is recorded in a permanent activity log.

| | |
| --- | --- |
| **Backend** | Laravel 13 · PHP 8.3 · PostgreSQL · Sanctum (cookie sessions) |
| **Frontend** | React 19 · TypeScript · Vite · Tailwind 4 · TanStack Query |
| **Shape** | REST API (`/api/v1`) + single-page app, in one repo |
| **Spec** | [PROJECT_SPEC.md](PROJECT_SPEC.md) — the source of truth. Where this README and the spec disagree, **the spec wins**. |

---

## Contents

**Part 1 · Setup**

1. [Quick start](#quick-start) — copy, paste, running in ~5 minutes
2. [Requirements](#requirements) — what you need installed
3. [Installation](#installation) — the same thing, explained step by step

**Part 2 · Using the app**

4. [Core concepts](#core-concepts) — workspace, project, task, role, activity
5. [Signing in](#signing-in) — three ways in, plus the demo accounts
6. [Roles and permissions](#roles-and-permissions) — who can do what
7. [Walkthrough for admins](#walkthrough-for-admins)
8. [Walkthrough for members](#walkthrough-for-members)
9. [Walkthrough for invitees](#walkthrough-for-invitees)
10. [Switching workspaces](#switching-workspaces)

**Part 3 · Reference**

11. [Command reference](#command-reference)
12. [Testing](#testing)
13. [API reference](#api-reference)
14. [Environment variables](#environment-variables)
15. [Architecture](#architecture)
16. [Security](#security)
17. [Troubleshooting](#troubleshooting)
18. [Known limitations](#known-limitations)

---

# Part 1 · Setup

## Quick start

> **Goal:** a working app at <http://localhost:5173>, signed in as an admin.
> **Time:** about 5 minutes. **You need:** two terminals.

### Step 1 — Create the database

```bash
sudo -u postgres psql -c "CREATE USER workspace WITH PASSWORD 'choose-a-password';"
sudo -u postgres createdb -O workspace workspace_saas
```

### Step 2 — Start the mail catcher

So invitation emails land somewhere you can read.

```bash
docker run -d --name mailpit --restart unless-stopped \
  -p 1025:1025 -p 8025:8025 axllent/mailpit
```

### Step 3 — Start the backend *(terminal 1)*

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate

# ⚠️  Now open .env and set DB_USERNAME / DB_PASSWORD to the role you just made

php artisan migrate
php artisan db:seed
php artisan serve
```

### Step 4 — Start the frontend *(terminal 2)*

```bash
cd frontend
nvm use
npm install
cp .env.example .env
npm run dev
```

### Step 5 — Sign in

Open **<http://localhost:5173>** and use:

| Email | Password |
| --- | --- |
| `admin@example.com` | `password` |

### What you should now have

| Service | URL | Notes |
| --- | --- | --- |
| **App** | <http://localhost:5173> | the React front end |
| **API** | <http://localhost:8000/api/v1> | health check: `/health` |
| **Mail inbox** | <http://localhost:8025> | Mailpit — every email lands here |

✅ **Quick check:** `curl http://localhost:8000/api/v1/health` should return
`{"status":"ok","version":"v1"}`.

❌ Something broken? Jump to [Troubleshooting](#troubleshooting).

---

## Requirements

| Tool | Version | Notes |
| --- | --- | --- |
| **PHP** | 8.3+ | extensions: `pdo_pgsql`, `mbstring`, `xml`, `curl`, `zip` |
| **Composer** | 2 | |
| **PostgreSQL** | 14+ | |
| **Node.js** | 20.19+ or 22.12+ | `frontend/.nvmrc` pins **24** |
| **Docker** | optional | only used to run the mail catcher |

---

## Installation

The [Quick start](#quick-start) above is the condensed version. This section is
the same four steps with the *why* included — read it if anything failed.

### Step 1. Database

Create a role, and a database that the role **owns**:

```bash
sudo -u postgres psql -c "CREATE USER workspace WITH PASSWORD 'choose-a-password';"
sudo -u postgres createdb -O workspace workspace_saas
```

**Why ownership matters.** PostgreSQL 15+ requires the role to own the `public`
schema before it can create tables. The `-O` flag grants that.

Any role name works — use whatever you like, then put it in `backend/.env`.

<details>
<summary><strong>Fix: the database already exists and someone else owns it</strong></summary>

```bash
sudo -u postgres psql -d workspace_saas -c "GRANT ALL ON SCHEMA public TO workspace;"
```
</details>

<details>
<summary><strong>Fix: <code>php artisan migrate</code> offers to create the database, then fails anyway</strong></summary>

Laravel offers to create a missing database for you, but it does so as the role
in your `.env` — and a role made with a plain `CREATE USER` has no `CREATEDB`
privilege. Laravel swallows the resulting *"permission denied to create
database"* and retries the original connection, so all you see is the original
*"database … does not exist"*.

Either create the database yourself (above), or grant the privilege so the
prompt works:

```bash
sudo -u postgres psql -c "ALTER ROLE workspace CREATEDB;"
```

Check which you have:

```bash
psql -h 127.0.0.1 -U workspace -d postgres -c "\du workspace"
```
</details>

### Step 2. Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

Open `.env` and set **`DB_USERNAME`** and **`DB_PASSWORD`** to match the role you
made in Step 1. Then:

```bash
php artisan migrate      # create the schema
php artisan db:seed      # optional demo data — see "Signing in"
php artisan serve        # → http://localhost:8000
```

✅ **Verify:** `curl http://localhost:8000/api/v1/health` → `{"status":"ok","version":"v1"}`

### Step 3. Frontend

In a second terminal:

```bash
cd frontend
nvm use            # honours .nvmrc; Node 20.19+ is required
npm install
cp .env.example .env
npm run dev        # → http://localhost:5173
```

> **⚠️ The three origins must agree.** `VITE_API_URL` must point at the backend
> origin, and that origin must appear in the backend's `FRONTEND_URL` **and**
> `SANCTUM_STATEFUL_DOMAINS`. Otherwise sign-in appears to succeed but the
> session never sticks. **The defaults already line up** — this only bites you
> if you change a port.

### Step 4. Mail

Invitation emails need somewhere to go. Locally that is **Mailpit**: an SMTP
server with a web inbox that accepts everything and delivers nothing, so no
message can escape your machine.

```bash
docker run -d --name mailpit --restart unless-stopped \
  -p 1025:1025 -p 8025:8025 axllent/mailpit
```

`.env.example` already points at it. Read the mail at **<http://localhost:8025>**.

> **Note:** Mailpit catches mail for **any** address. You can invite
> `anything@mailinator.com` and read it in Mailpit — but the message is *not*
> forwarded on and will never reach the real Mailinator inbox.

**Alternatives** — both are a `.env` change only, no code:

| Goal | Setting |
| --- | --- |
| Write emails to `storage/logs/laravel.log` | `MAIL_MAILER=log` |
| Deliver for real | a provider — see [Environment variables](#environment-variables) |

---

# Part 2 · Using the app

## Core concepts

```
Workspace ──┬── Members      people, each with a role in this workspace
            ├── Projects     containers for work; active or archived
            │     └── Tasks  the work itself
            └── Activity     a permanent record of what changed
```

| Concept | What it is | The rule to remember |
| --- | --- | --- |
| **Workspace** | The tenant boundary. | Everything belongs to exactly one workspace, and **nothing crosses between them**. You can belong to as many as you like. |
| **Role** | `admin` or `member`. | It is **per workspace** — you can be an admin of one and a member of another. The interface changes accordingly. |
| **Project** | Groups related tasks. | Names are unique within a workspace. Projects are **never deleted, only archived**. |
| **Task** | The work itself; belongs to one project. | Has a status, priority, optional assignee and optional due date. Deleting hides it everywhere but **keeps its history**. |
| **Activity** | A permanent record of changes. | Written automatically by the operations that cause it. **Nothing can edit or delete it.** |

**Task fields**

| Field | Values |
| --- | --- |
| Status | `to do` · `in progress` · `blocked` · `done` |
| Priority | `low` · `medium` · `high` |
| Assignee | optional — any active member of the workspace |
| Due date | optional |

> **Archived ≠ deleted.** An archived project stays readable and keeps all its
> tasks and history. It simply stops accepting new ones.

---

## Signing in

You need an account. There are **three ways** to get one:

| # | Route in | How |
| --- | --- | --- |
| 1 | **Register yourself** | Go to <http://localhost:5173/register>. A brand-new account belongs to no workspace, so the app asks you to create your first one — and you become its admin. |
| 2 | **Accept an invitation** | See [Walkthrough for invitees](#walkthrough-for-invitees). |
| 3 | **Use the demo data** | If you ran `php artisan db:seed` — see below. |

### Demo accounts

`php artisan db:seed` creates two workspaces with projects, tasks across every
status, a pending invitation and a populated activity feed.

**All three accounts share the password `password`.**

| Email | Acme Product | Internal Tools |
| --- | --- | --- |
| `admin@example.com` | **admin** | member |
| `lead@example.com` | **admin** | **admin** |
| `member@example.com` | member | — |

> **👉 Start with `admin@example.com`.** It is an admin in one workspace and a
> plain member in the other, so switching workspace shows you both interfaces
> without signing out — the fastest way to understand the permission model.

**Re-seeding.** The seeder refuses to run twice. To rebuild from scratch:

```bash
php artisan migrate:fresh --seed     # ⚠️ drops every table first
```

---

## Roles and permissions

Permissions are enforced **server-side by policies**. The interface hides what
you can't do, but that is only a convenience — the API refuses it regardless of
what the client shows.

| Action | Admin | Member |
| --- | :---: | :---: |
| View the workspace, its projects, tasks, members and activity | ✅ | ✅ |
| See the dashboard | ✅ | ✅ |
| Create, edit and delete **tasks** | ✅ | ✅ |
| Assign tasks to anyone in the workspace | ✅ | ✅ |
| Create, rename and archive **projects** | ✅ | ❌ |
| Invite people, and withdraw invitations | ✅ | ❌ |
| Change someone's role | ✅ | ❌ |
| Remove someone from the workspace | ✅ | ❌ |
| Rename or delete the workspace | ✅ | ❌ |

**Two rules apply to everyone, including admins:**

1. **A workspace always keeps at least one admin.** The last admin cannot be
   demoted or removed — the menu items are disabled and the API refuses.
2. **Archived projects accept no new tasks**, and an existing task cannot be
   moved into one. Tasks already inside stay fully editable.

---

## Walkthrough for admins

**Sign in as** `admin@example.com`, and make sure the switcher (top-left) reads
**Acme Product**.

| # | Task | Where | What happens |
| --- | --- | --- | --- |
| 1 | **Create a project** | Projects → *New project* | Give it a name (unique in this workspace) and an optional description. |
| 2 | **Add work** | Tasks → *New task* | Pick the project, then set status, priority, assignee and due date. **Only title and project are required.** |
| 3 | **Invite a teammate** | Members → *Invite* | Enter their email, choose member or admin. They get an email with a link valid for **7 days**. |
| 4 | **Change a role / remove someone** | Members → `…` menu on their row | See the warning below. |
| 5 | **Archive a project** | Projects → `…` menu → *Archive* | Nothing is deleted. Reverse it by opening the project and choosing *Make active*. |
| 6 | **Rename or delete the workspace** | Settings | Deleting hides the workspace from everyone; nothing underneath it is erased. |

> **⚠️ Removing a member** revokes their access immediately and **unassigns
> every task they held in this workspace** — the tasks stay, they just become
> unassigned. Their history remains in the activity feed, and you can invite
> them back.

**Pending invitations** appear on the Members page until accepted. You can
**Withdraw** one at any time.

---

## Walkthrough for members

**Sign in as** `member@example.com` — or as `admin@example.com` and switch to
**Internal Tools**.

**What's different:**

| Page | As a member you see |
| --- | --- |
| Projects | No *New project* button, no `…` menus |
| Members | Who's who, but no controls and no *Pending invitations* section |
| Settings | The workspace name as plain text |

**What you can do — the work itself:**

- ✅ **Create and edit tasks** in any project in the workspace
- ✅ **Change status and priority** — open a task from the list and save
- ✅ **Assign tasks** to anyone who is an active member here
- ✅ **Delete tasks** you no longer need, with a confirmation step
- ✅ **Filter and search** by project, status, priority, assignee or free text,
  and sort by title, due date, created or updated
  — *filters live in the URL, so a filtered view can be bookmarked or shared*
- ✅ **See the dashboard and activity feed**, exactly as an admin does

---

## Walkthrough for invitees

This is what someone with **no account** experiences.

| Step | What you do | What happens |
| --- | --- | --- |
| **1** | Open <http://localhost:8025> and find *"You've been invited to join …"* | The email names the workspace, who invited you, the role, and **the email address you must use**. |
| **2** | Click **Accept invitation** | You're not signed in, so the app takes you to the sign-in screen and explains why. The invitation is remembered throughout. |
| **3** | Follow **Create an account** and register | ⚠️ Use **the address the invitation was sent to** — see the rule below. |
| **4** | Done | The invitation is accepted automatically and you land in the workspace with the role the admin chose. |

> **🔑 The one rule that matters.** Invitations are tied to their email address.
> Signing up with a different one gives you *"This invitation is for someone
> else."*

**Already have an account** on that address? Sign in instead at step 3 — same outcome.

**Invitation lifetime:** 7 days. After that the link reports that it has
expired, and an admin can simply send a new one. A link that has already been
used, or been withdrawn, says so too.

---

## Switching workspaces

The switcher sits at the **top of the sidebar**.

- It lists every workspace you're an **active member** of.
- **New workspace** creates another, with you as its admin.
- Your choice is **remembered between visits**. If you lose access to the
  remembered workspace, the app quietly falls back to one you still belong to.

> Switching changes what you *see* **and** what you *may do* — the same account
> can be an admin in one workspace and a member in the next. Nothing from one
> workspace is ever visible in another.

---

# Part 3 · Reference

## Command reference

**Backend** — run from `backend/`

| Command | What it does |
| --- | --- |
| `php artisan serve` | Run the API on :8000 |
| `php artisan migrate` | Apply new migrations |
| `php artisan db:seed` | Add demo data to an existing database |
| `php artisan migrate:fresh --seed` | ⚠️ Wipe and rebuild with demo data |
| `php artisan route:list` | List every registered route |
| `vendor/bin/pint` | Format PHP |

**Frontend** — run from `frontend/`

| Command | What it does |
| --- | --- |
| `npm run dev` | Dev server on :5173 |
| `npm run build` | Production build |
| `npm run typecheck` | TypeScript |
| `npm run lint` | oxlint |
| `npm run format` | Prettier |

---

## Testing

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

**Backend tests use SQLite in-memory by default** (configured in `phpunit.xml`),
so they need no database and never touch your PostgreSQL.

To run the same suite against PostgreSQL, point it at a **separate** database —
`RefreshDatabase` drops every table:

```bash
DB_CONNECTION=pgsql DB_DATABASE=workspace_saas_test php artisan test
```

**Two guards run inside every test:**

| Guard | Effect |
| --- | --- |
| `Model::preventLazyLoading()` | Any relation read without being eager loaded **fails the test**. The feature suite covers every endpoint, which is what keeps N+1 queries out. |
| OpenAPI document tested against the routes | Checked against the enums and the morph map, so **documentation cannot drift from the code**. |

---

## API reference

📄 [`backend/docs/openapi.yaml`](backend/docs/openapi.yaml) — OpenAPI 3.1
covering authentication, workspaces, projects, tasks, members, invitations,
activities and the dashboard, with request and response examples.

**View it:**

```bash
npx @redocly/cli preview-docs backend/docs/openapi.yaml
```

…or paste it into <https://editor.swagger.io>.

### Conventions

- Base path **`/api/v1`**; all ids are **UUID v4**.
- A single resource is wrapped in `data`; a collection is a `data` **array**.
- Paginated collections add Laravel's `links` and `meta`.
  Default **20** per page, capped at **100** via `per_page`.

### Status codes

| Status | Meaning here |
| --- | --- |
| `200` | Read or update succeeded |
| `201` | Created |
| `204` | Succeeded, nothing to return |
| `401` | No valid session |
| `403` | Signed in and a member, but not allowed to do this |
| `404` | No such record — **or** one you may not reach |
| `409` | Conflicts with the resource's current state |
| `419` | CSRF token missing or stale |
| `422` | Validation or business-rule failure |
| `429` | Rate limited |

### Calling the API directly

> Authentication is a **session cookie, not a token**.

A client must:

1. `GET /sanctum/csrf-cookie` **once**, to receive the `XSRF-TOKEN` cookie.
2. Send that cookie's value in an **`X-XSRF-TOKEN` header** on every mutating
   request, with credentials included.
3. Send an **`Origin` header** from a host listed in `SANCTUM_STATEFUL_DOMAINS`.

> **⚠️ Browsers send `Origin` automatically — curl and Postman do not,** and
> will not get a session without it.

---

## Environment variables

`.env.example` is committed in both apps and is complete. `.env` is git-ignored,
and **no secrets are committed**.

### `backend/.env`

| Variable | Local value | Why it matters |
| --- | --- | --- |
| `APP_URL` | `http://localhost:8000` | |
| `FRONTEND_URL` | `http://localhost:5173` | CORS origin, and the base for invitation links |
| `DB_CONNECTION` | `pgsql` | |
| `DB_HOST` / `DB_PORT` | `127.0.0.1` / `5432` | |
| `DB_DATABASE` | `workspace_saas` | |
| `DB_USERNAME` / `DB_PASSWORD` | your role | **You must set these** |
| `SANCTUM_STATEFUL_DOMAINS` | `localhost:5173,127.0.0.1:5173` | Hosts that receive a session cookie |
| `SESSION_DOMAIN` | `localhost` | Must cover both ports |
| `SESSION_DRIVER` | `database` | Needs the `sessions` table |
| `SESSION_SECURE_COOKIE` | `false` | Set `true` once served over HTTPS |
| `MAIL_MAILER` | `smtp` | Mailpit locally |
| `MAIL_HOST` / `MAIL_PORT` | `127.0.0.1` / `1025` | Mailpit's SMTP listener |

### `frontend/.env`

| Variable | Local value |
| --- | --- |
| `VITE_API_URL` | `http://localhost:8000` |

<details>
<summary><strong>Sending real email in production</strong></summary>

Laravel has `postmark`, `resend`, `ses` and `smtp` built in. Each needs
credentials and the provider's transport package, but **no application code
changes**:

```bash
composer require symfony/postmark-mailer
```

```
MAIL_MAILER=postmark
POSTMARK_TOKEN=…
MAIL_FROM_ADDRESS="invitations@yourdomain.com"
```
</details>

---

## Architecture

### Modular monolith

One deployable application, organised **by business capability** rather than by
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

**A request flows exactly as the specification lays out:**

```
Request → Controller → Form Request → Policy → Action → Model/DB → Activity → API Resource
```

**Actions** hold the operations worth naming — transactional, enforcing an
invariant, or recording history. A plain read or a one-field update stays in the
controller. There are deliberately **no repositories, no service layer, and no
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

> Axios lives **only** in `features/*/api.ts`. Components call TanStack Query
> hooks; none makes an HTTP request directly.

### Multi-tenancy

A shared database; every tenant-owned row carries a `workspace_id`. Tenant
resolution is centralised in **one middleware** and runs before any controller
or policy:

```
authenticated user
   → resolve the workspace from the route
   → verify an active membership
   → establish the WorkspaceContext
   → policies authorize against that context
   → the action runs
```

**Three things follow, and they are what make the isolation hold:**

| # | Rule | How it's enforced |
| --- | --- | --- |
| 1 | **A `workspace_id` in a request body is never read** | The workspace comes from the **route**, always. Ownership keys are not mass-assignable on any model. |
| 2 | **An inaccessible resource is indistinguishable from a missing one** | Both answer `404` with the same body, so the API cannot be used to discover which ids exist. Nested route parameters resolve *through* the workspace relationship, so another tenant's record is a 404 **before application code runs**. |
| 3 | **The database enforces what it can** | A composite foreign key on `tasks (project_id, workspace_id) → projects (id, workspace_id)` makes a task in one workspace referencing a project in another **impossible to write**, even if the application layer were wrong. |

### Authentication

**Laravel Sanctum in SPA mode** — an HTTP-only session cookie with CSRF
protection. No JWT, no bearer tokens; the `personal_access_tokens` table is
deliberately absent. The session id is **rotated on login** and **invalidated on
logout**.

Registration creates the account **only** — no session and no workspace — which
is why a new account lands on *"create your first workspace"*.

### Notable decisions

| Decision | Reasoning |
| --- | --- |
| **UUID v4, not v7** | Laravel's `HasUuids` switched to v7, so `HasUuidPrimaryKey` wraps `HasVersion4Uuids` instead — an *ordered* v4, keeping the RFC version nibble at 4 while giving B-tree locality on high-insert tables. |
| **Projects archive; they do not soft delete** | `archived ≠ deleted`, so `projects` has no `deleted_at`. `DELETE` runs the same archive transition as `PATCH {"status":"archived"}` and returns `204`. |
| **`404` for cross-tenant, `403` for wrong role** | `409` is for duplicate-state collisions (already a member, already invited, already accepted, expired); `422` for validation and business rules, including the last-admin rule. |
| **Last-admin protection locks the whole admin set** | Two concurrent demotions could each see the other as a survivor and both commit. The guard takes a `FOR UPDATE` lock on *every* admin membership, so the second transaction re-reads after the first commits and is refused. |
| **Invitation tokens exist in one place** | 256 bits from `random_bytes`, stored only as a SHA-256 digest, emailed as the raw value, absent from every API response. SHA-256 rather than bcrypt is deliberate: the token is already high-entropy, so it needs a deterministic digest that can be found by index. |
| **Activities are append-only, and enforced** | The model throws on `updating` and `deleting`, nothing is mass-assignable, no route writes one, and both foreign keys are `ON DELETE RESTRICT`. |

---

## Security

Covered, **and covered by tests**:

| Area | What's guaranteed |
| --- | --- |
| **Cross-tenant access** | Reading, updating and deleting another workspace's records is blocked across every module. |
| **Cross-tenant relationships** | A task cannot take a project or an assignee from another workspace; the composite foreign key blocks it **at the database**, even if validation were bypassed. |
| **Assignee validation** | Must be an *active* member of the same workspace. |
| **Mass assignment** | `workspace_id`, `user_id`, `role`, `project_id` and `assignee_id` are fillable **nowhere**. |
| **Last-admin protection** | Cannot demote or remove the final admin — verified under real concurrency. |
| **Invitation security** | CSPRNG token, hash-only storage, 7-day expiry, one-time acceptance, and the accepting account's email must match. |
| **Soft deletes** | Removing a member revokes access immediately and unassigns their tasks **in that workspace only**; deleted tasks vanish from listings while their history remains. |
| **Credentials** | Password hashes never leave the database; the user resource lists its fields explicitly rather than spreading the model. |
| **Rate limiting** | Login **5/min** per email+IP plus **20/min** per IP; registration **5/min** per IP. |
| **Session hardening** | HTTP-only cookie, `SameSite=Lax`, CSRF on every mutating request, session rotated on login and invalidated on logout. |
| **Enumeration** | Identical messages whether or not an account exists; identical `404`s whether or not a record exists. |

---

## Troubleshooting

| Symptom | Cause | Fix |
| --- | --- | --- |
| **"Port 5173 is already in use"** | Something else holds the port. It is deliberately strict — see below. | `ss -ltnp \| grep ':5173'`, then kill it |
| **Sign-in succeeds, then you're immediately signed out** | `VITE_API_URL`, `FRONTEND_URL` and `SANCTUM_STATEFUL_DOMAINS` disagree | Make all three name the **same origin, including the port** |
| **`419` on every write** | The CSRF cookie is missing | The app calls `/sanctum/csrf-cookie` automatically; a manual client must do it **first** |
| **No invitation email** | Mailpit isn't running, or wrong port | Check `docker ps` and that `MAIL_PORT=1025`. With `MAIL_MAILER=log` the message is in `backend/storage/logs/laravel.log` instead |
| **"This invitation is for someone else"** | You're signed in as a different address from the one invited | Sign out and use the invited address |
| **A seeded account won't sign in** | Wrong password, or the seeder never ran | All seeded accounts use `password`. If you seeded before, `db:seed` refuses to run again — use `php artisan migrate:fresh --seed` |

<details>
<summary><strong>Why port 5173 is strict, and how to free it</strong></summary>

The backend only issues session cookies to origins listed in
`SANCTUM_STATEFUL_DOMAINS`, so a silent move to 5174 would break sign-in
confusingly. Find and stop whatever holds the port:

```bash
ss -ltnp | grep ':5173'
kill $(ss -ltnp | grep ':5173' | grep -oE 'pid=[0-9]+' | cut -d= -f2)
```

**`pkill -f vite` will not match it** — the process reports its name as
`MainThread`.
</details>

---

## Known limitations

**Deliberately out of scope, per the specification:** billing, subscriptions,
chat, file uploads, a notifications system, microservices, cloud infrastructure,
Terraform, Redis, GraphQL, advanced reporting, and any permission model beyond
the two workspace roles.

**Also not built, and worth knowing:**

| Limitation | Detail |
| --- | --- |
| **No CI pipeline** | The spec parks it; the commands in [Testing](#testing) are what a pipeline would run. |
| **No password reset, no email verification** | The specified auth surface is register, login, logout and me. The `password_reset_tokens` table exists unused. |
| **No "remember me"** | The model disables the remember-token mechanism rather than leaving it half-wired. |
| **Invitation details are not readable before sign-in** | The link is public, but there is no public endpoint to preview which workspace it is for, so the invitee sees the workspace name only after accepting. |
| **Tasks cannot be sorted by status or priority** | Both would sort lexically — priority would order *high, low, medium* — so they are left out of the whitelist rather than shipped subtly wrong. |
| **Activity subjects are returned as a type and an id**, not embedded | A feed showing a task's title relies on what the event recorded in its metadata. |
| **Description fields are capped at 5,000 characters** | The specification sets no limit; the bound is a choice. |
| **The demo seeder shares one known password** | Safe locally, unsafe anywhere exposed. |
