import type { Activity, Invitation, Member, Project, Task, User, Workspace } from '@/types/api'

const NOW = '2026-09-13T10:00:00+00:00'

export const alice: User = {
  id: 'user-alice',
  name: 'Alice Chen',
  email: 'alice@example.com',
  created_at: NOW,
  updated_at: NOW,
}

export const bob: User = {
  id: 'user-bob',
  name: 'Bob Ray',
  email: 'bob@example.com',
  created_at: NOW,
  updated_at: NOW,
}

export const acme: Workspace = {
  id: 'ws-acme',
  name: 'Acme',
  role: 'admin',
  created_at: NOW,
  updated_at: NOW,
}

export const beta: Workspace = {
  id: 'ws-beta',
  name: 'Beta',
  role: 'user',
  created_at: NOW,
  updated_at: NOW,
}

export const launch: Project = {
  id: 'project-launch',
  name: 'Launch',
  description: 'Q4 launch',
  status: 'active',
  created_at: NOW,
  updated_at: NOW,
}

export const archivedProject: Project = {
  ...launch,
  id: 'project-old',
  name: 'Old',
  status: 'archived',
}

export const shipIt: Task = {
  id: 'task-ship',
  title: 'Ship the API',
  description: null,
  status: 'in_progress',
  priority: 'high',
  due_date: '2026-12-24',
  project_id: launch.id,
  assignee_id: alice.id,
  project: launch,
  assignee: alice,
  created_at: NOW,
  updated_at: NOW,
}

export const adminMember: Member = {
  id: 'member-alice',
  role: 'admin',
  user: alice,
  created_at: NOW,
  updated_at: NOW,
}

export const plainMember: Member = {
  id: 'member-bob',
  role: 'user',
  user: bob,
  created_at: NOW,
  updated_at: NOW,
}

export const pendingInvitation: Invitation = {
  id: 'invitation-1',
  email: 'carol@example.com',
  role: 'user',
  status: 'pending',
  expires_at: '2026-09-20T10:00:00+00:00',
  accepted_at: null,
  invited_by: alice,
  created_at: NOW,
  updated_at: NOW,
}

export const projectCreatedActivity: Activity = {
  id: 'activity-1',
  type: 'project.created',
  subject_type: 'project',
  subject_id: launch.id,
  metadata: { name: 'Launch' },
  user_id: alice.id,
  user: alice,
  created_at: NOW,
}

/** Laravel's pagination envelope. */
export function paginate<T>(items: T[], overrides: Partial<{ per_page: number }> = {}) {
  return {
    data: items,
    meta: {
      current_page: 1,
      from: items.length ? 1 : null,
      last_page: 1,
      per_page: overrides.per_page ?? 20,
      to: items.length || null,
      total: items.length,
    },
  }
}
