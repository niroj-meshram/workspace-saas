import { z } from 'zod'

export const taskSchema = z.object({
  title: z.string().min(1, 'Give the task a title.').max(255, 'Keep this under 255 characters.'),
  description: z.string().max(5000, 'Keep this under 5000 characters.').optional(),
  project_id: z.string().min(1, 'Choose a project.'),
  assignee_id: z.string().optional(),
  status: z.enum(['todo', 'in_progress', 'blocked', 'done']),
  priority: z.enum(['low', 'medium', 'high']),
  due_date: z
    .string()
    .regex(/^\d{4}-\d{2}-\d{2}$/, 'Use the date picker.')
    .optional()
    .or(z.literal('')),
})

export type TaskValues = z.infer<typeof taskSchema>

export const TASK_STATUS_LABELS = {
  todo: 'To do',
  in_progress: 'In progress',
  blocked: 'Blocked',
  done: 'Done',
} as const

export const TASK_PRIORITY_LABELS = {
  low: 'Low',
  medium: 'Medium',
  high: 'High',
} as const

export const TASK_SORT_OPTIONS = [
  { value: '-created_at', label: 'Newest first' },
  { value: 'created_at', label: 'Oldest first' },
  { value: 'due_date', label: 'Due soonest' },
  { value: '-due_date', label: 'Due latest' },
  { value: 'title', label: 'Title A–Z' },
  { value: '-title', label: 'Title Z–A' },
] as const
