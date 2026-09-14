/**
 * Shapes returned by the Laravel API. These mirror the API resources in
 * backend/app/Http/Resources — if one of those changes, this changes with it.
 */

export type WorkspaceRole = 'admin' | 'user'
export type ProjectStatus = 'active' | 'archived'
export type TaskStatus = 'todo' | 'in_progress' | 'blocked' | 'done'
export type TaskPriority = 'low' | 'medium' | 'high'
export type InvitationStatus = 'pending' | 'accepted' | 'expired'

export interface User {
  id: string
  name: string
  email: string
  created_at: string | null
  updated_at: string | null
}

export interface Workspace {
  id: string
  name: string
  /** The signed-in user's role here. Drives what the UI offers, not what it may do. */
  role?: WorkspaceRole
  created_at: string | null
  updated_at: string | null
}

export interface Project {
  id: string
  name: string
  description: string | null
  status: ProjectStatus
  created_at: string | null
  updated_at: string | null
}

export interface Task {
  id: string
  title: string
  description: string | null
  status: TaskStatus
  priority: TaskPriority
  due_date: string | null
  project_id: string
  assignee_id: string | null
  project?: Project
  assignee?: User
  created_at: string | null
  updated_at: string | null
}

export interface Member {
  id: string
  role: WorkspaceRole
  user?: User
  created_at: string | null
  updated_at: string | null
}

export interface Invitation {
  id: string
  email: string
  role: WorkspaceRole
  status: InvitationStatus
  expires_at: string
  accepted_at: string | null
  invited_by?: User
  created_at: string | null
  updated_at: string | null
}

export type ActivityType =
  | 'task.created'
  | 'task.status_changed'
  | 'task.assigned'
  | 'task.priority_changed'
  | 'task.updated'
  | 'task.deleted'
  | 'project.created'
  | 'project.archived'
  | 'member.invited'
  | 'invitation.accepted'
  | 'member.removed'
  | 'member.role_changed'

export interface Activity {
  id: string
  type: ActivityType
  subject_type: string
  subject_id: string
  metadata: Record<string, unknown>
  user_id: string | null
  user?: User
  created_at: string | null
}

export interface DashboardStats {
  projects: number
  todo: number
  in_progress: number
  completed: number
}

export interface Dashboard {
  stats: DashboardStats
  my_tasks: Task[]
  recent_activity: Activity[]
}

/** Laravel wraps every single resource in a data key. */
export interface Envelope<T> {
  data: T
}

/** Laravel's standard pagination metadata. */
export interface Paginated<T> {
  data: T[]
  meta: {
    current_page: number
    from: number | null
    last_page: number
    per_page: number
    to: number | null
    total: number
  }
}
