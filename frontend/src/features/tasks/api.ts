import { api } from '@/lib/api'
import type { Envelope, Paginated, Task, TaskPriority, TaskStatus } from '@/types/api'

/** Mirrors the whitelisted filters the API accepts (PROJECT_SPEC.md §12). */
export interface TaskFilters {
  project_id?: string
  status?: TaskStatus
  priority?: TaskPriority
  assignee_id?: string
  search?: string
  sort?: string
  page?: number
  per_page?: number
}

export interface TaskPayload {
  title?: string
  description?: string | null
  project_id?: string
  assignee_id?: string | null
  status?: TaskStatus
  priority?: TaskPriority
  due_date?: string | null
}

export async function fetchTasks(
  workspaceId: string,
  filters: TaskFilters = {},
): Promise<Paginated<Task>> {
  // Drop empty values so the API never sees `?status=` and rejects it.
  const params = Object.fromEntries(
    Object.entries(filters).filter(([, value]) => value !== undefined && value !== ''),
  )

  const { data } = await api.get<Paginated<Task>>(`/workspaces/${workspaceId}/tasks`, { params })

  return data
}

export async function createTask(workspaceId: string, payload: TaskPayload): Promise<Task> {
  const { data } = await api.post<Envelope<Task>>(`/workspaces/${workspaceId}/tasks`, payload)

  return data.data
}

export async function updateTask(
  workspaceId: string,
  taskId: string,
  payload: TaskPayload,
): Promise<Task> {
  const { data } = await api.patch<Envelope<Task>>(
    `/workspaces/${workspaceId}/tasks/${taskId}`,
    payload,
  )

  return data.data
}

export async function deleteTask(workspaceId: string, taskId: string): Promise<void> {
  await api.delete(`/workspaces/${workspaceId}/tasks/${taskId}`)
}
