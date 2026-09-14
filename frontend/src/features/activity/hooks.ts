import { useQuery } from '@tanstack/react-query'
import { fetchActivities, type ActivityFilters } from '@/features/activity/api'

export const activityKeys = {
  all: (workspaceId: string) => ['workspace', workspaceId, 'activities'] as const,
  list: (workspaceId: string, filters: ActivityFilters) =>
    ['workspace', workspaceId, 'activities', filters] as const,
}

export function useActivities(workspaceId: string, filters: ActivityFilters = {}) {
  return useQuery({
    queryKey: activityKeys.list(workspaceId, filters),
    queryFn: () => fetchActivities(workspaceId, filters),
    placeholderData: (previous) => previous,
  })
}
