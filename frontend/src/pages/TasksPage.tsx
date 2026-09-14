import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router'
import { ListChecks, MoreHorizontal, Pencil, Plus, Search, Trash2, X } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Panel } from '@/components/panel'
import { PageHeader } from '@/components/page-header'
import { EmptyState, ErrorState, RowsSkeleton } from '@/components/states'
import { TaskStatusChip } from '@/components/status-chip'
import { PrioritySignal } from '@/components/priority-signal'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { Assignee, DueDate } from '@/features/tasks/task-row'
import { TaskDialog } from '@/features/tasks/task-dialog'
import { useDeleteTask, useTasks } from '@/features/tasks/hooks'
import { useAllProjects } from '@/features/projects/hooks'
import { useMembers } from '@/features/members/hooks'
import { TASK_SORT_OPTIONS, TASK_STATUS_LABELS } from '@/features/tasks/schemas'
import { useWorkspace } from '@/features/workspaces/workspace-provider'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import { toApiError } from '@/lib/api-error'
import type { Task, TaskPriority, TaskStatus } from '@/types/api'

const ANY = 'any'

export function TasksPage() {
  const { workspace } = useWorkspace()
  const workspaceId = workspace!.id

  // Filters live in the URL so a filtered view can be shared or reloaded.
  const [params, setParams] = useSearchParams()
  const [searchInput, setSearchInput] = useState(params.get('search') ?? '')
  const search = useDebouncedValue(searchInput, 300)

  const projectId = params.get('project_id') ?? ''
  const status = params.get('status') ?? ''
  const priority = params.get('priority') ?? ''
  const assigneeId = params.get('assignee_id') ?? ''
  const sort = params.get('sort') ?? '-created_at'
  const page = Number(params.get('page') ?? 1)

  const setParam = (key: string, value: string) => {
    setParams((current) => {
      const next = new URLSearchParams(current)

      if (!value || value === ANY) next.delete(key)
      else next.set(key, value)

      // Any filter change starts from the first page again.
      if (key !== 'page') next.delete('page')

      return next
    })
  }

  useEffect(() => {
    setParams((current) => {
      const next = new URLSearchParams(current)

      if (search) next.set('search', search)
      else next.delete('search')

      if ((current.get('search') ?? '') !== search) next.delete('page')

      return next
    })
  }, [search, setParams])

  const filters = {
    project_id: projectId || undefined,
    status: (status || undefined) as TaskStatus | undefined,
    priority: (priority || undefined) as TaskPriority | undefined,
    assignee_id: assigneeId || undefined,
    search: search || undefined,
    sort,
    page,
  }

  const { data, isPending, isError, isFetching, refetch } = useTasks(workspaceId, filters)
  const projects = useAllProjects(workspaceId)
  const members = useMembers(workspaceId)
  const deleteTask = useDeleteTask(workspaceId)

  const [creating, setCreating] = useState(false)
  const [editing, setEditing] = useState<Task | null>(null)
  const [deleting, setDeleting] = useState<Task | null>(null)

  const hasFilters = Boolean(projectId || status || priority || assigneeId || search)

  const confirmDelete = async () => {
    if (!deleting) return

    try {
      await deleteTask.mutateAsync(deleting.id)
      toast.success('Task deleted')
      setDeleting(null)
    } catch (error) {
      toast.error(toApiError(error).message)
    }
  }

  const tasks = data?.data ?? []
  const meta = data?.meta

  return (
    <div className="space-y-6">
      <PageHeader
        title="Tasks"
        description="Everything in flight across this workspace."
        action={
          <Button onClick={() => setCreating(true)}>
            <Plus className="size-4" />
            New task
          </Button>
        }
      />

      <div className="flex flex-wrap items-center gap-2">
        <div className="relative min-w-56 flex-1">
          <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
          <Input
            value={searchInput}
            onChange={(event) => setSearchInput(event.target.value)}
            placeholder="Search titles and descriptions"
            className="pl-8"
            aria-label="Search tasks"
          />
        </div>

        <Select value={projectId || ANY} onValueChange={(value) => setParam('project_id', value)}>
          <SelectTrigger className="w-auto min-w-36" aria-label="Filter by project">
            <SelectValue placeholder="Project" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={ANY}>All projects</SelectItem>
            {(projects.data ?? []).map((project) => (
              <SelectItem key={project.id} value={project.id}>
                {project.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Select value={status || ANY} onValueChange={(value) => setParam('status', value)}>
          <SelectTrigger className="w-auto min-w-32" aria-label="Filter by status">
            <SelectValue placeholder="Status" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={ANY}>Any status</SelectItem>
            {Object.entries(TASK_STATUS_LABELS).map(([value, label]) => (
              <SelectItem key={value} value={value}>
                {label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Select value={priority || ANY} onValueChange={(value) => setParam('priority', value)}>
          <SelectTrigger className="w-auto min-w-32" aria-label="Filter by priority">
            <SelectValue placeholder="Priority" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={ANY}>Any priority</SelectItem>
            <SelectItem value="high">High</SelectItem>
            <SelectItem value="medium">Medium</SelectItem>
            <SelectItem value="low">Low</SelectItem>
          </SelectContent>
        </Select>

        <Select value={assigneeId || ANY} onValueChange={(value) => setParam('assignee_id', value)}>
          <SelectTrigger className="w-auto min-w-36" aria-label="Filter by assignee">
            <SelectValue placeholder="Assignee" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={ANY}>Anyone</SelectItem>
            {(members.data ?? []).map((member) =>
              member.user ? (
                <SelectItem key={member.id} value={member.user.id}>
                  {member.user.name}
                </SelectItem>
              ) : null,
            )}
          </SelectContent>
        </Select>

        <Select value={sort} onValueChange={(value) => setParam('sort', value)}>
          <SelectTrigger className="w-auto min-w-36" aria-label="Sort tasks">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {TASK_SORT_OPTIONS.map((option) => (
              <SelectItem key={option.value} value={option.value}>
                {option.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        {hasFilters ? (
          <Button
            variant="ghost"
            size="sm"
            onClick={() => {
              setSearchInput('')
              setParams(new URLSearchParams())
            }}
          >
            <X className="size-4" />
            Clear
          </Button>
        ) : null}
      </div>

      <Panel>
        {isPending ? (
          <RowsSkeleton />
        ) : isError ? (
          <ErrorState description="Tasks didn't load." onRetry={refetch} />
        ) : tasks.length === 0 ? (
          <EmptyState
            icon={ListChecks}
            title={hasFilters ? 'Nothing matches those filters' : 'No tasks yet'}
            description={
              hasFilters
                ? 'Try widening the search or clearing a filter.'
                : 'Add the first task to get the board moving.'
            }
            action={
              hasFilters ? (
                <Button
                  variant="outline"
                  onClick={() => {
                    setSearchInput('')
                    setParams(new URLSearchParams())
                  }}
                >
                  Clear filters
                </Button>
              ) : (
                <Button onClick={() => setCreating(true)}>New task</Button>
              )
            }
          />
        ) : (
          <ul className="divide-border divide-y" aria-busy={isFetching} data-testid="task-list">
            {tasks.map((task) => (
              <li
                key={task.id}
                className="hover:bg-accent/40 flex items-center gap-3 px-5 py-3.5 transition-colors"
              >
                <PrioritySignal priority={task.priority} />

                <div className="min-w-0 flex-1">
                  <button
                    type="button"
                    onClick={() => setEditing(task)}
                    className="block max-w-full truncate text-left text-sm font-medium underline-offset-4 hover:underline"
                  >
                    {task.title}
                  </button>
                  {task.project ? (
                    <p className="text-muted-foreground mt-0.5 truncate text-xs">
                      {task.project.name}
                    </p>
                  ) : null}
                </div>

                {/* Fixed-width slots: a missing value must not shift the
                    columns of every other row. */}
                <div className="hidden w-32 shrink-0 md:block">
                  <Assignee name={task.assignee?.name} />
                </div>

                <div className="hidden w-28 shrink-0 text-right sm:block">
                  <DueDate value={task.due_date} />
                </div>

                <div className="flex w-28 shrink-0 justify-end">
                  <TaskStatusChip status={task.status} />
                </div>

                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="ghost" size="icon" aria-label={`Actions for ${task.title}`}>
                      <MoreHorizontal className="size-4" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end">
                    <DropdownMenuItem onSelect={() => setEditing(task)} className="gap-2">
                      <Pencil className="size-4" />
                      Edit
                    </DropdownMenuItem>
                    <DropdownMenuItem
                      onSelect={() => setDeleting(task)}
                      className="text-destructive gap-2"
                    >
                      <Trash2 className="size-4" />
                      Delete
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </li>
            ))}
          </ul>
        )}
      </Panel>

      {meta && meta.last_page > 1 ? (
        <div className="flex items-center justify-between">
          <p className="text-muted-foreground text-sm">
            {meta.from}–{meta.to} of {meta.total}
          </p>
          <div className="flex gap-2">
            <Button
              variant="outline"
              size="sm"
              disabled={meta.current_page <= 1}
              onClick={() => setParam('page', String(meta.current_page - 1))}
            >
              Previous
            </Button>
            <Button
              variant="outline"
              size="sm"
              disabled={meta.current_page >= meta.last_page}
              onClick={() => setParam('page', String(meta.current_page + 1))}
            >
              Next
            </Button>
          </div>
        </div>
      ) : null}

      <TaskDialog
        workspaceId={workspaceId}
        defaultProjectId={projectId || undefined}
        open={creating}
        onOpenChange={setCreating}
      />

      {editing ? (
        <TaskDialog
          workspaceId={workspaceId}
          task={editing}
          open
          onOpenChange={(open) => !open && setEditing(null)}
        />
      ) : null}

      <ConfirmDialog
        open={Boolean(deleting)}
        onOpenChange={(open) => !open && setDeleting(null)}
        title={`Delete ${deleting?.title ?? 'this task'}?`}
        description="The task disappears from lists. Its history stays in the activity feed."
        confirmLabel="Delete task"
        onConfirm={confirmDelete}
        isPending={deleteTask.isPending}
      />
    </div>
  )
}
