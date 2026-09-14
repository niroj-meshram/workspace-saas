import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { changeMemberRole, fetchMembers, removeMember } from '@/features/members/api'
import type { WorkspaceRole } from '@/types/api'

export const memberKeys = {
  all: (workspaceId: string) => ['workspace', workspaceId, 'members'] as const,
}

export function useMembers(workspaceId: string) {
  return useQuery({
    queryKey: memberKeys.all(workspaceId),
    queryFn: () => fetchMembers(workspaceId),
    select: (page) => page.data,
  })
}

function invalidateMemberViews(
  queryClient: ReturnType<typeof useQueryClient>,
  workspaceId: string,
) {
  queryClient.invalidateQueries({ queryKey: memberKeys.all(workspaceId) })
  queryClient.invalidateQueries({ queryKey: ['workspace', workspaceId, 'activities'] })
  // Demoting or removing yourself changes what you may do next.
  queryClient.invalidateQueries({ queryKey: ['workspaces'] })
}

export function useChangeMemberRole(workspaceId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ memberId, role }: { memberId: string; role: WorkspaceRole }) =>
      changeMemberRole(workspaceId, memberId, role),
    onSuccess: () => invalidateMemberViews(queryClient, workspaceId),
  })
}

export function useRemoveMember(workspaceId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (memberId: string) => removeMember(workspaceId, memberId),
    onSuccess: () => invalidateMemberViews(queryClient, workspaceId),
  })
}
