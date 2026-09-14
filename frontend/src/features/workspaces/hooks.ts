import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  createWorkspace,
  deleteWorkspace,
  fetchWorkspaces,
  updateWorkspace,
} from '@/features/workspaces/api'

export const workspacesKey = ['workspaces'] as const

export function useWorkspaces() {
  return useQuery({ queryKey: workspacesKey, queryFn: fetchWorkspaces })
}

export function useCreateWorkspace() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: createWorkspace,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: workspacesKey }),
  })
}

export function useUpdateWorkspace(workspaceId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (name: string) => updateWorkspace(workspaceId, name),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: workspacesKey }),
  })
}

export function useDeleteWorkspace() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: deleteWorkspace,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: workspacesKey }),
  })
}
