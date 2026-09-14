import { Link } from 'react-router'
import { TaskStatusChip } from '@/components/status-chip'
import { PrioritySignal } from '@/components/priority-signal'
import { describeDueDate, initials } from '@/lib/format'
import { cn } from '@/lib/utils'
import type { Task } from '@/types/api'

export function DueDate({ value }: { value: string | null }) {
  const due = describeDueDate(value)

  if (!due) return null

  return (
    <span
      className={cn(
        'text-xs whitespace-nowrap',
        due.tone === 'overdue' && 'text-state-blocked font-medium',
        due.tone === 'soon' && 'text-state-progress font-medium',
        due.tone === 'normal' && 'text-muted-foreground',
      )}
    >
      {due.tone === 'overdue' ? `Overdue · ${due.label}` : due.label}
    </span>
  )
}

export function Assignee({ name }: { name?: string }) {
  if (!name) {
    return <span className="text-muted-foreground text-xs">Unassigned</span>
  }

  return (
    <span className="flex items-center gap-1.5">
      <span
        className="bg-accent text-accent-foreground grid size-5 shrink-0 place-items-center rounded-full text-[0.625rem] font-semibold"
        aria-hidden
      >
        {initials(name)}
      </span>
      <span className="truncate text-xs">{name}</span>
    </span>
  )
}

/** A compact task line, used where the full table is too heavy. */
export function TaskRow({ task }: { task: Task }) {
  return (
    <li className="hover:bg-accent/40 flex items-center gap-3 px-5 py-3 transition-colors">
      <PrioritySignal priority={task.priority} className="shrink-0" />

      <span className="min-w-0 flex-1">
        <Link
          to={`/tasks?search=${encodeURIComponent(task.title)}`}
          className="block truncate text-sm font-medium underline-offset-4 hover:underline"
        >
          {task.title}
        </Link>
        {task.project ? (
          <span className="text-muted-foreground block truncate text-xs">{task.project.name}</span>
        ) : null}
      </span>

      <DueDate value={task.due_date} />
      <TaskStatusChip status={task.status} />
    </li>
  )
}
