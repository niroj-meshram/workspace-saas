import { api } from '@/lib/api'
import type { Envelope, Paginated, Project, ProjectStatus } from '@/types/api'

export interface ProjectPayload {
  name?: string
  description?: string | null
  status?: ProjectStatus
}

export async function fetchProjects(
  workspaceId: string,
  params: { page?: number; per_page?: number } = {},
): Promise<Paginated<Project>> {
  const { data } = await api.get<Paginated<Project>>(`/workspaces/${workspaceId}/projects`, {
    params,
  })

  return data
}

export async function fetchProject(workspaceId: string, projectId: string): Promise<Project> {
  const { data } = await api.get<Envelope<Project>>(
    `/workspaces/${workspaceId}/projects/${projectId}`,
  )

  return data.data
}

export async function createProject(
  workspaceId: string,
  payload: ProjectPayload,
): Promise<Project> {
  const { data } = await api.post<Envelope<Project>>(`/workspaces/${workspaceId}/projects`, payload)

  return data.data
}

export async function updateProject(
  workspaceId: string,
  projectId: string,
  payload: ProjectPayload,
): Promise<Project> {
  const { data } = await api.patch<Envelope<Project>>(
    `/workspaces/${workspaceId}/projects/${projectId}`,
    payload,
  )

  return data.data
}

/** The API archives rather than deletes: projects have no soft delete. */
export async function archiveProject(workspaceId: string, projectId: string): Promise<void> {
  await api.delete(`/workspaces/${workspaceId}/projects/${projectId}`)
}
