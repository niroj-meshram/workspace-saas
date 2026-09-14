import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  acceptInvitation,
  fetchInvitations,
  inviteMember,
  revokeInvitation,
} from '@/features/invitations/api'
import type { WorkspaceRole } from '@/types/api'

export const invitationKeys = {
  all: (workspaceId: string) => ['workspace', workspaceId, 'invitations'] as const,
}

export function useInvitations(workspaceId: string, enabled = true) {
  return useQuery({
    queryKey: invitationKeys.all(workspaceId),
    queryFn: () => fetchInvitations(workspaceId),
    select: (page) => page.data,
    enabled,
  })
}

export function useInviteMember(workspaceId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: { email: string; role: WorkspaceRole }) =>
      inviteMember(workspaceId, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: invitationKeys.all(workspaceId) })
      queryClient.invalidateQueries({ queryKey: ['workspace', workspaceId, 'activities'] })
    },
  })
}

export function useRevokeInvitation(workspaceId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (invitationId: string) => revokeInvitation(workspaceId, invitationId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: invitationKeys.all(workspaceId) }),
  })
}

export function useAcceptInvitation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: acceptInvitation,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['workspaces'] }),
  })
}
