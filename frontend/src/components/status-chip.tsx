import { cn } from '@/lib/utils'
import { TASK_STATUS_LABELS } from '@/features/tasks/schemas'
import type { ProjectStatus, TaskStatus } from '@/types/api'

/**
 * Work state is the only thing in this interface allowed to carry saturated
 * colour, so a coloured pixel anywhere always means something about the work.
 */
const TASK_TONES: Record<TaskStatus, string> = {
  todo: 'bg-state-todo-surface text-state-todo',
  in_progress: 'bg-state-progress-surface text-state-progress',
  blocked: 'bg-state-blocked-surface text-state-blocked',
  done: 'bg-state-done-surface text-state-done',
}

const chip =
  'inline-flex shrink-0 items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap'

export function TaskStatusChip({ status, className }: { status: TaskStatus; className?: string }) {
  return (
    <span className={cn(chip, TASK_TONES[status], className)}>
      <span className="size-1.5 rounded-full bg-current" aria-hidden />
      {TASK_STATUS_LABELS[status]}
    </span>
  )
}

export function ProjectStatusChip({
  status,
  className,
}: {
  status: ProjectStatus
  className?: string
}) {
  const archived = status === 'archived'

  return (
    <span
      className={cn(
        chip,
        archived ? 'bg-muted text-muted-foreground' : 'bg-state-done-surface text-state-done',
        className,
      )}
    >
      <span className="size-1.5 rounded-full bg-current" aria-hidden />
      {archived ? 'Archived' : 'Active'}
    </span>
  )
}
