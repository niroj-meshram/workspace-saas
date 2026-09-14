import { api } from '@/lib/api'
import type { Envelope, Workspace } from '@/types/api'

export async function fetchWorkspaces(): Promise<Workspace[]> {
  const { data } = await api.get<{ data: Workspace[] }>('/workspaces')

  return data.data
}

export async function createWorkspace(name: string): Promise<Workspace> {
  const { data } = await api.post<Envelope<Workspace>>('/workspaces', { name })

  return data.data
}

export async function updateWorkspace(id: string, name: string): Promise<Workspace> {
  const { data } = await api.patch<Envelope<Workspace>>(`/workspaces/${id}`, { name })

  return data.data
}

export async function deleteWorkspace(id: string): Promise<void> {
  await api.delete(`/workspaces/${id}`)
}
