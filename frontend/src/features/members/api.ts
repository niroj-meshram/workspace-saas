import { api } from '@/lib/api'
import type { Envelope, Member, Paginated, WorkspaceRole } from '@/types/api'

export async function fetchMembers(workspaceId: string): Promise<Paginated<Member>> {
  // Workspaces are small teams; one generous page keeps the admin count the
  // UI reasons about honest rather than page-local.
  const { data } = await api.get<Paginated<Member>>(`/workspaces/${workspaceId}/members`, {
    params: { per_page: 100 },
  })

  return data
}

export async function changeMemberRole(
  workspaceId: string,
  memberId: string,
  role: WorkspaceRole,
): Promise<Member> {
  const { data } = await api.patch<Envelope<Member>>(
    `/workspaces/${workspaceId}/members/${memberId}`,
    { role },
  )

  return data.data
}

export async function removeMember(workspaceId: string, memberId: string): Promise<void> {
  await api.delete(`/workspaces/${workspaceId}/members/${memberId}`)
}
