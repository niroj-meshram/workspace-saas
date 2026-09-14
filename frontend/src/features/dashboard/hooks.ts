import { useQuery } from '@tanstack/react-query'
import { fetchDashboard } from '@/features/dashboard/api'

export const dashboardKey = (workspaceId: string) =>
  ['workspace', workspaceId, 'dashboard'] as const

export function useDashboard(workspaceId: string) {
  return useQuery({
    queryKey: dashboardKey(workspaceId),
    queryFn: () => fetchDashboard(workspaceId),
  })
}
