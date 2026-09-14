import { api } from '@/lib/api'
import type { Activity, ActivityType, Paginated } from '@/types/api'

export interface ActivityFilters {
  type?: ActivityType
  user_id?: string
  page?: number
  per_page?: number
}

export async function fetchActivities(
  workspaceId: string,
  filters: ActivityFilters = {},
): Promise<Paginated<Activity>> {
  const params = Object.fromEntries(
    Object.entries(filters).filter(([, value]) => value !== undefined && value !== ''),
  )

  const { data } = await api.get<Paginated<Activity>>(`/workspaces/${workspaceId}/activities`, {
    params,
  })

  return data
}
