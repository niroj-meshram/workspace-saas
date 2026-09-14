import { api } from '@/lib/api'
import type { Envelope, Invitation, Paginated, Workspace, WorkspaceRole } from '@/types/api'

export async function fetchInvitations(workspaceId: string): Promise<Paginated<Invitation>> {
  const { data } = await api.get<Paginated<Invitation>>(`/workspaces/${workspaceId}/invitations`, {
    params: { per_page: 100 },
  })

  return data
}

export async function inviteMember(
  workspaceId: string,
  payload: { email: string; role: WorkspaceRole },
): Promise<Invitation> {
  const { data } = await api.post<Envelope<Invitation>>(
    `/workspaces/${workspaceId}/invitations`,
    payload,
  )

  return data.data
}

export async function revokeInvitation(workspaceId: string, invitationId: string): Promise<void> {
  await api.delete(`/workspaces/${workspaceId}/invitations/${invitationId}`)
}

/** Exchange an invitation token for membership. Returns the joined workspace. */
export async function acceptInvitation(token: string): Promise<Workspace> {
  const { data } = await api.post<Envelope<Workspace>>(`/invitations/${token}/accept`)

  return data.data
}
