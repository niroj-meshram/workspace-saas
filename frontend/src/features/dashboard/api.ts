import { api } from '@/lib/api'
import type { Dashboard, Envelope } from '@/types/api'

export async function fetchDashboard(workspaceId: string): Promise<Dashboard> {
  const { data } = await api.get<Envelope<Dashboard>>(`/workspaces/${workspaceId}/dashboard`)

  return data.data
}
