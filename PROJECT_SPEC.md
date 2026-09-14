Master Project Specification
Multi-Tenant Team Workspace SaaS
1. Goal

Build a production-minded multi-tenant team workspace SaaS where users can:

Create and belong to multiple workspaces
Switch between workspaces
Manage projects
Manage tasks
Invite team members
Assign roles
Track activity/history
View a workspace dashboard

The project should demonstrate strong Laravel + React engineering, tenant isolation, clean modular architecture, validation, authorization, testing, and polished UX.

2. Tech Stack
Backend
Laravel
PHP
PostgreSQL
Laravel Sanctum
REST API
Pest
Laravel API Resources
Frontend
React
TypeScript
Vite
Tailwind CSS
shadcn/ui
TanStack Query
React Router
Axios
React Hook Form
Zod
Vitest
React Testing Library
IDs
Native PostgreSQL uuid
UUID v4
Repository
workspace-saas/
├── backend/
├── frontend/
└── README.md
3. Core Domain
User
  ↓
Workspace Membership
  ↓
Workspace
  ↓
Project
  ↓
Task

A user can belong to multiple workspaces.

Example:

Niraj
 ├── Acme → admin
 └── Beta → user

Roles are workspace-specific, not global.

Roles:

admin
user
4. Roles & Permissions
Admin

Can:

Manage workspace
Invite members
Change member roles
Remove members
Create projects
Edit projects
Archive projects
Create/update tasks
Assign tasks
User

Can:

View workspace
View members
View projects
Create/update tasks
Change task status
Change priority
Assign tasks where permitted by business rules

Cannot:

Manage members
Change roles
Remove members
Create/archive projects
Critical rule

A workspace must always have at least one admin.

Therefore:

Cannot remove the last admin
Cannot demote the last admin
5. Authentication

Use Laravel Sanctum cookie/session authentication.

Endpoints:

POST /api/v1/auth/register
POST /api/v1/auth/login
POST /api/v1/auth/logout
GET  /api/v1/auth/me

Use:

HTTP-only session cookie
CSRF protection
CORS configuration
Sanctum stateful domains
withCredentials: true

No JWT.

6. Workspace Flow
Registration
Register
 ↓
Login/authenticated
 ↓
Create workspace
 ↓
Creator automatically becomes admin
Multiple workspaces

Authenticated user can belong to multiple workspaces and switch between them.

Workspace selection is UI state.

It is NOT a security boundary.

Backend always verifies membership.

7. Multi-Tenancy

Use a shared database.

Tenant-owned records contain:

workspace_id

Tenant resolution:

Authenticate user
      ↓
Resolve workspace from route
      ↓
Verify active membership
      ↓
Establish current tenant context
      ↓
Authorize action
      ↓
Execute business operation

Never trust:

{
  "workspace_id": "..."
}

from user input.

Workspace must come from the authorized route/context.

Do not scatter raw:

where('workspace_id', ...)

through every controller.

Use centralized tenant context/scoping.

8. Database Schema
users
id UUID PK
name
email UNIQUE
password
created_at
updated_at
deleted_at
workspaces
id UUID PK
name
created_at
updated_at
deleted_at
workspace_members
id UUID PK
workspace_id FK
user_id FK
role
created_at
updated_at
deleted_at

Constraint:

UNIQUE(workspace_id, user_id)

Roles:

admin
user
projects
id UUID PK
workspace_id FK
name
description nullable
status
created_at
updated_at
deleted_at

Status:

active
archived

Constraint:

UNIQUE(workspace_id, name)

Archived projects:

Remain viewable
Existing tasks remain
Existing activity remains
Cannot receive new tasks
tasks
id UUID PK
workspace_id FK
project_id FK
assignee_id nullable FK users
title
description nullable
status
priority
due_date nullable
created_at
updated_at
deleted_at

Status:

todo
in_progress
blocked
done

Priority:

low
medium
high

Rules:

Task workspace must equal project workspace
Assignee must be an active member of the workspace
Archived projects cannot receive new tasks
Removing a member sets their assigned tasks to NULL
9. Activities

Purpose-built activity/audit system.

activities

id UUID PK
workspace_id FK
user_id nullable FK
type
subject_type
subject_id
metadata JSONB
created_at

Examples:

task.created
task.status_changed
task.assigned
task.priority_changed
project.created
project.archived
member.invited
member.removed
member.role_changed

Metadata can contain:

{
  "old_status": "todo",
  "new_status": "done"
}

Activities are never modified or deleted through the API.

Historical activity must remain even if the related resource is soft-deleted.

10. Invitations
workspace_invitations

id UUID PK
workspace_id FK
email
role
token_hash
expires_at
accepted_at nullable
invited_by FK users
created_at
updated_at
deleted_at

Rules:

Invitation valid for 7 days
Only admins can invite
Cannot invite existing active member
Cannot have multiple active invitations for same email/workspace
Invitation can only be accepted once
Store hash of token, never raw token
Preserve accepted/expired invitations for history

Flow:

Admin invites email
       ↓
Invitation created
       ↓
Email sent
       ↓
User opens invitation
       ↓
Login/Register
       ↓
Accept invitation
       ↓
Membership created
       ↓
Invitation marked accepted
11. Soft Deletes

Use Laravel SoftDeletes for deletable business entities.

Generally:

users
workspaces
workspace_members
projects? → no, use active/archived instead
tasks
invitations

Important distinction:

archived ≠ deleted

Projects use:

active / archived

Activities are retained permanently.

No cascading physical deletes.

Related data is preserved.

12. API Conventions

Base:

/api/v1

Workspace:

/api/v1/workspaces/{workspace}

Workspace identifier is UUID.

Workspace
GET    /api/v1/workspaces
POST   /api/v1/workspaces
GET    /api/v1/workspaces/{workspace}
PATCH  /api/v1/workspaces/{workspace}
DELETE /api/v1/workspaces/{workspace}
Projects
GET    /api/v1/workspaces/{workspace}/projects
POST   /api/v1/workspaces/{workspace}/projects
GET    /api/v1/workspaces/{workspace}/projects/{project}
PATCH  /api/v1/workspaces/{workspace}/projects/{project}
DELETE /api/v1/workspaces/{workspace}/projects/{project}
Tasks
GET    /api/v1/workspaces/{workspace}/tasks
POST   /api/v1/workspaces/{workspace}/tasks
GET    /api/v1/workspaces/{workspace}/tasks/{task}
PATCH  /api/v1/workspaces/{workspace}/tasks/{task}
DELETE /api/v1/workspaces/{workspace}/tasks/{task}

Filters:

?project_id=
?status=
?priority=
?assignee_id=
?search=
?sort=

Only whitelist valid filters/sorts.

Members
GET    /api/v1/workspaces/{workspace}/members
PATCH  /api/v1/workspaces/{workspace}/members/{member}
DELETE /api/v1/workspaces/{workspace}/members/{member}
Invitations
POST   /api/v1/workspaces/{workspace}/invitations
GET    /api/v1/workspaces/{workspace}/invitations
DELETE /api/v1/workspaces/{workspace}/invitations/{invitation}

POST   /api/v1/invitations/{token}/accept
Activity
GET /api/v1/workspaces/{workspace}/activities

Read-only.

Dashboard
GET /api/v1/workspaces/{workspace}/dashboard

Returns:

stats
my_tasks
recent_activity
13. API Responses

Use Laravel API Resources.

Single resource:

{
  "data": {}
}

Collection:

{
  "data": []
}

Paginated responses use Laravel's standard pagination metadata.

Default:

20 items

Maximum:

100 items
14. HTTP Status Codes
200 → successful read/update
201 → created
204 → successful delete/no content
401 → unauthenticated
403 → authenticated but forbidden
404 → not found / inaccessible tenant resource
409 → conflict
422 → validation/business-rule failure
15. Laravel Architecture

Use a modular monolith.

Modules:

Tenancy
Projects
Tasks
Invitations
Activity

Do not create unnecessary repositories/interfaces/services.

Preferred flow:

Request
 ↓
Controller
 ↓
Form Request
 ↓
Policy/Auth
 ↓
Action
 ↓
Model/DB
 ↓
Activity
 ↓
API Resource
 ↓
React

Actions represent meaningful business operations:

CreateWorkspace
InviteMember
AcceptInvitation
ChangeMemberRole
RemoveMember

CreateProject
ArchiveProject

CreateTask
UpdateTask
16. Authorization

Use Laravel Policies.

Authorization must happen server-side.

Examples:

Admin:
  invite member        ✓
  change role          ✓
  remove member        ✓
  create project       ✓
  archive project      ✓

User:
  invite member        ✗
  change role          ✗
  remove member        ✗
  create project       ✗
  create task          ✓
  update task          ✓
17. Critical Security Rules

Must test:

Cross-tenant access

User in Workspace A must never access Workspace B resources.

Cross-tenant relationships

Cannot create:

Workspace A
Task
Project from Workspace B
Assignee validation

Assignee must be an active member of the same workspace.

Mass assignment

Users cannot submit sensitive fields such as:

workspace_id
role
user_id

and manipulate authorization/ownership.

Use explicit fields through Form Requests/Actions.

Invitation security
Secure random token
Store token hash
Expiration
One-time acceptance
Email matching
18. Transactions

Use database transactions for critical multi-step operations:

Create workspace + creator membership
Accept invitation + membership
Remove member + unassign tasks
Change last-admin-sensitive role

Use appropriate locking where concurrent requests could violate business rules.

19. React Architecture
src/
├── components/
│   └── ui/
├── features/
│   ├── auth/
│   ├── workspaces/
│   ├── projects/
│   ├── tasks/
│   ├── members/
│   ├── invitations/
│   └── activity/
├── layouts/
├── pages/
├── lib/
├── hooks/
└── types/

Feature APIs:

features/tasks/api.ts
features/tasks/hooks.ts

Components should not contain raw Axios calls.

20. React State
Server state

Use:

TanStack Query

For:

API fetching
caching
mutations
invalidation
loading/error states
Auth state

Use:

AuthProvider

with:

GET /api/v1/auth/me
Workspace state

Use:

WorkspaceContext

and persist selected workspace for UX.

Backend authorization remains the real security boundary.

21. Frontend Forms

Use:

React Hook Form
+
Zod

Frontend validation improves UX.

Laravel validation remains authoritative.

22. Routing

Use React Router.

/login
/register
/invitations/:token

/dashboard
/projects
/projects/:project
/tasks
/members
/settings

Protected application routes require authentication.

23. UI

Use:

Tailwind CSS
shadcn/ui

Build reusable:

buttons
inputs
dialogs
dropdowns
tables
badges
toasts
skeletons
empty states

No Material UI.

24. UX Requirements

Don't make it look like a raw CRUD demo.

Include:

Loading states
Skeletons
Empty states
Error states
Retry actions
Toast confirmations
Confirmation dialogs for destructive actions
Disabled states during mutations
Clear form validation
Responsive layout

Dashboard:

Workspace Switcher

Stats
├── Projects
├── To Do
├── In Progress
└── Completed

My Tasks

Recent Activity

No unnecessary charts.

25. Testing
Backend

Use:

Pest

Organize:

tests/
├── Feature/
│   ├── Auth/
│   ├── Tenancy/
│   ├── Projects/
│   ├── Tasks/
│   ├── Invitations/
│   └── Members/
└── Unit/

Highest priority:

Tenant isolation
Authorization
Auth
Task/project rules
Invitation flow
Last-admin protection
Validation

Don't chase arbitrary coverage percentages.

Test important behavior.

26. Frontend Testing

Use:

Vitest
React Testing Library

Feature-oriented tests:

features/tasks/__tests__
features/projects/__tests__
features/auth/__tests__

Test user behavior rather than implementation details.

27. Database Testing

Local tests:

SQLite in-memory

CI/integration:

PostgreSQL

CI is intentionally parked for now and can be added later.

28. API Documentation

Add OpenAPI/Swagger documentation for the important API endpoints.

Document:

Authentication
Workspaces
Projects
Tasks
Members
Invitations
Activities
Dashboard

Don't waste time documenting irrelevant internals.

29. Out of Scope

Do not build:

Billing
Stripe
Subscription plans
Chat
File uploads
Notifications system
Microservices
AWS infrastructure
Terraform
Redis
GraphQL
Advanced reporting
Complex permission system

Only add extras after the core is complete.

30. Implementation Priority

Build in this order:

1. Project setup
2. Database + migrations
3. Authentication
4. Tenancy/workspaces
5. Roles + authorization
6. Projects
7. Tasks
8. Invitations
9. Members
10. Activity
11. Dashboard
12. React polish
13. Tests
14. API documentation
15. Final README

Do not jump ahead if an earlier foundation isn't working.

31. Definition of Done

The project is complete when:

User can register/login
User can create workspace
Creator becomes admin
User can belong to multiple workspaces
Workspace switching works
Admin can invite members
Invitation acceptance works
Admin/user permissions work
Projects can be created/archived
Tasks can be created/updated/assigned
Task filtering works
Activity history works
Dashboard works
Cross-tenant access is blocked
Important business rules are tested
UI has proper loading/error/empty states
API is documented
README explains architecture and decisions