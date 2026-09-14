import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  createTask,
  deleteTask,
  fetchTasks,
  updateTask,
  type TaskFilters,
  type TaskPayload,
} from '@/features/tasks/api'

export const taskKeys = {
  all: (workspaceId: string) => ['workspace', workspaceId, 'tasks'] as const,
  list: (workspaceId: string, filters: TaskFilters) =>
    ['workspace', workspaceId, 'tasks', filters] as const,
}

export function useTasks(workspaceId: string, filters: TaskFilters = {}) {
  return useQuery({
    queryKey: taskKeys.list(workspaceId, filters),
    queryFn: () => fetchTasks(workspaceId, filters),
    // Keep the previous page on screen while the next one loads, so filtering
    // does not flash an empty table.
    placeholderData: (previous) => previous,
  })
}

/** Anything a task change can invalidate: the list, the board stats, the feed. */
function invalidateTaskViews(workspaceId: string) {
  return (queryClient: ReturnType<typeof useQueryClient>) => {
    queryClient.invalidateQueries({ queryKey: taskKeys.all(workspaceId) })
    queryClient.invalidateQueries({ queryKey: ['workspace', workspaceId, 'dashboard'] })
    queryClient.invalidateQueries({ queryKey: ['workspace', workspaceId, 'activities'] })
  }
}

export function useCreateTask(workspaceId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: TaskPayload) => createTask(workspaceId, payload),
    onSuccess: () => invalidateTaskViews(workspaceId)(queryClient),
  })
}

export function useUpdateTask(workspaceId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ taskId, payload }: { taskId: string; payload: TaskPayload }) =>
      updateTask(workspaceId, taskId, payload),
    onSuccess: () => invalidateTaskViews(workspaceId)(queryClient),
  })
}

export function useDeleteTask(workspaceId: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (taskId: string) => deleteTask(workspaceId, taskId),
    onSuccess: () => invalidateTaskViews(workspaceId)(queryClient),
  })
}
