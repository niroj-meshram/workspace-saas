import { cn } from '@/lib/utils'
import { TASK_PRIORITY_LABELS } from '@/features/tasks/schemas'
import type { TaskPriority } from '@/types/api'

const FILLED: Record<TaskPriority, number> = { low: 1, medium: 2, high: 3 }

/**
 * Priority as three rising bars rather than another coloured badge.
 *
 * A task row already carries a status colour; a second colour ramp beside it
 * competes for the same attention and stops either reading as signal. Height
 * encodes magnitude on its own, and stays legible for anyone who cannot
 * separate the two hues.
 */
export function PrioritySignal({
  priority,
  className,
}: {
  priority: TaskPriority
  className?: string
}) {
  const filled = FILLED[priority]

  return (
    <span
      className={cn('inline-flex items-end gap-[2px]', className)}
      title={`${TASK_PRIORITY_LABELS[priority]} priority`}
    >
      <span className="sr-only">{TASK_PRIORITY_LABELS[priority]} priority</span>
      {[1, 2, 3].map((level) => (
        <span
          key={level}
          aria-hidden
          className={cn(
            'w-[3px] rounded-[1px] transition-colors',
            level === 1 && 'h-1.5',
            level === 2 && 'h-2.5',
            level === 3 && 'h-3.5',
            level <= filled
              ? priority === 'high'
                ? 'bg-state-blocked'
                : 'bg-foreground/70'
              : 'bg-foreground/15',
          )}
        />
      ))}
    </span>
  )
}
