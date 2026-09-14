import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  archiveProject,
  createProject,
  fetchProject,
  fetchProjects,
  updateProject,
  type ProjectPayload,
} from '@/features/projects/api'

export const projectKeys = {
  all: (workspaceId: string) => ['workspace', workspaceId, 'projects'] as const,
  list: (workspaceId: string, page: number) =>
    ['workspace', workspaceId, 'projects', { page }] as const,
  detail: (workspaceId: string, projectId: string) =>
    ['workspace', workspaceId, 'projects', projectId] as const,
}

export function useProjects(workspaceId: string, page = 1) {
  return useQuery({
    queryKey: projectKeys.list(workspaceId, page),
    queryFn: () => fetchProjects(workspaceId, { page }),
    placeholderData: (previous) => previous,
  })
}

/** Every project in the workspace, for pickers that need the full set. */
export function useAllProjects(workspaceId: string) {
  return useQuery({
    queryKey: [...projectKeys.all(workspaceId), 'picker'],
    queryFn: () => fetchProjects(workspaceId, { per_page: 100 }),
    select: (page) => page.data,
  })
}

export function useProject(workspaceId: string, projectId: string) {
  return useQuery({
    queryKey: projectKeys.detail(workspaceId, projectId),
    queryFn: () => fetchProject(workspaceId, projectId),
  })
}

export function useCreateProject(workspaceId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: ProjectPayload) => createProject(workspaceId, payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: projectKeys.all(workspaceId) }),
  })
}

export function useUpdateProject(workspaceId: string, projectId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: ProjectPayload) => updateProject(workspaceId, projectId, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: projectKeys.all(workspaceId) })
      queryClient.invalidateQueries({ queryKey: ['workspace', workspaceId, 'dashboard'] })
    },
  })
}

export function useArchiveProject(workspaceId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (projectId: string) => archiveProject(workspaceId, projectId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: projectKeys.all(workspaceId) })
      queryClient.invalidateQueries({ queryKey: ['workspace', workspaceId, 'dashboard'] })
    },
  })
}
