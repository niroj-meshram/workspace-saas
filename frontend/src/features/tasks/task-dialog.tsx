import { useEffect } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { toast } from 'sonner'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Field, FormError } from '@/components/field'
import { useCreateTask, useUpdateTask } from '@/features/tasks/hooks'
import { useAllProjects } from '@/features/projects/hooks'
import { useMembers } from '@/features/members/hooks'
import {
  TASK_PRIORITY_LABELS,
  TASK_STATUS_LABELS,
  taskSchema,
  type TaskValues,
} from '@/features/tasks/schemas'
import { applyApiErrors } from '@/lib/form'
import type { Task, TaskPriority, TaskStatus } from '@/types/api'

const FIELDS = [
  'title',
  'description',
  'project_id',
  'assignee_id',
  'status',
  'priority',
  'due_date',
] as const

const UNASSIGNED = 'unassigned'

export function TaskDialog({
  workspaceId,
  task,
  defaultProjectId,
  open,
  onOpenChange,
}: {
  workspaceId: string
  task?: Task
  defaultProjectId?: string
  open: boolean
  onOpenChange: (open: boolean) => void
}) {
  const isEditing = Boolean(task)

  const createTask = useCreateTask(workspaceId)
  const updateTask = useUpdateTask(workspaceId)
  const projects = useAllProjects(workspaceId)
  const members = useMembers(workspaceId)

  const form = useForm<TaskValues>({
    resolver: zodResolver(taskSchema),
    defaultValues: {
      title: '',
      description: '',
      project_id: '',
      assignee_id: UNASSIGNED,
      status: 'todo',
      priority: 'medium',
      due_date: '',
    },
  })

  useEffect(() => {
    if (!open) return

    // reset() also clears the form-level error from the previous attempt.
    form.reset({
      title: task?.title ?? '',
      description: task?.description ?? '',
      project_id: task?.project_id ?? defaultProjectId ?? '',
      assignee_id: task?.assignee_id ?? UNASSIGNED,
      status: task?.status ?? 'todo',
      priority: task?.priority ?? 'medium',
      due_date: task?.due_date ?? '',
    })
  }, [open, task, defaultProjectId, form])

  const onSubmit = form.handleSubmit(async (values) => {
    const payload = {
      title: values.title,
      description: values.description?.trim() ? values.description : null,
      project_id: values.project_id,
      assignee_id: values.assignee_id === UNASSIGNED ? null : (values.assignee_id ?? null),
      status: values.status,
      priority: values.priority,
      due_date: values.due_date ? values.due_date : null,
    }

    try {
      if (isEditing && task) {
        await updateTask.mutateAsync({ taskId: task.id, payload })
        toast.success('Task updated')
      } else {
        await createTask.mutateAsync(payload)
        toast.success('Task created')
      }

      onOpenChange(false)
    } catch (error) {
      const message = applyApiErrors(error, form.setError, FIELDS)

      if (message) form.setError('root', { message })
    }
  })

  // Archived projects refuse new tasks, so they are only offered when the task
  // is already in one.
  const selectableProjects = (projects.data ?? []).filter(
    (project) => project.status === 'active' || project.id === task?.project_id,
  )

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{isEditing ? 'Edit task' : 'New task'}</DialogTitle>
          <DialogDescription>
            {isEditing ? 'Update the details of this task.' : 'Add a piece of work to a project.'}
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={onSubmit} className="space-y-4" noValidate>
          <FormError message={form.formState.errors.root?.message} />

          <Field id="task-title" label="Title" error={form.formState.errors.title?.message}>
            <Input
              id="task-title"
              autoFocus
              placeholder="Ship the API"
              {...form.register('title')}
              aria-invalid={Boolean(form.formState.errors.title)}
            />
          </Field>

          <Field
            id="task-project"
            label="Project"
            error={form.formState.errors.project_id?.message}
          >
            <Select
              value={useWatch({ control: form.control, name: 'project_id' })}
              onValueChange={(value) =>
                form.setValue('project_id', value, { shouldValidate: true })
              }
            >
              <SelectTrigger id="task-project" className="w-full">
                <SelectValue
                  placeholder={projects.isPending ? 'Loading projects…' : 'Choose a project'}
                />
              </SelectTrigger>
              <SelectContent>
                {selectableProjects.map((project) => (
                  <SelectItem key={project.id} value={project.id}>
                    {project.name}
                    {project.status === 'archived' ? ' (archived)' : ''}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Field>

          <div className="grid gap-4 sm:grid-cols-2">
            <Field id="task-status" label="Status" error={form.formState.errors.status?.message}>
              <Select
                value={useWatch({ control: form.control, name: 'status' })}
                onValueChange={(value) => form.setValue('status', value as TaskStatus)}
              >
                <SelectTrigger id="task-status" className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {Object.entries(TASK_STATUS_LABELS).map(([value, label]) => (
                    <SelectItem key={value} value={value}>
                      {label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>

            <Field
              id="task-priority"
              label="Priority"
              error={form.formState.errors.priority?.message}
            >
              <Select
                value={useWatch({ control: form.control, name: 'priority' })}
                onValueChange={(value) => form.setValue('priority', value as TaskPriority)}
              >
                <SelectTrigger id="task-priority" className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {Object.entries(TASK_PRIORITY_LABELS).map(([value, label]) => (
                    <SelectItem key={value} value={value}>
                      {label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <Field
              id="task-assignee"
              label="Assignee"
              error={form.formState.errors.assignee_id?.message}
            >
              <Select
                value={useWatch({ control: form.control, name: 'assignee_id' }) || UNASSIGNED}
                onValueChange={(value) => form.setValue('assignee_id', value)}
              >
                <SelectTrigger id="task-assignee" className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={UNASSIGNED}>Unassigned</SelectItem>
                  {(members.data ?? []).map((member) =>
                    member.user ? (
                      <SelectItem key={member.id} value={member.user.id}>
                        {member.user.name}
                      </SelectItem>
                    ) : null,
                  )}
                </SelectContent>
              </Select>
            </Field>

            <Field
              id="task-due-date"
              label="Due date"
              error={form.formState.errors.due_date?.message}
            >
              <Input
                id="task-due-date"
                type="date"
                {...form.register('due_date')}
                aria-invalid={Boolean(form.formState.errors.due_date)}
              />
            </Field>
          </div>

          <Field
            id="task-description"
            label="Description"
            hint="Optional."
            error={form.formState.errors.description?.message}
          >
            <Textarea
              id="task-description"
              rows={3}
              {...form.register('description')}
              aria-invalid={Boolean(form.formState.errors.description)}
            />
          </Field>

          <DialogFooter>
            <Button
              type="button"
              variant="ghost"
              onClick={() => onOpenChange(false)}
              disabled={form.formState.isSubmitting}
            >
              Cancel
            </Button>
            <Button type="submit" disabled={form.formState.isSubmitting}>
              {form.formState.isSubmitting ? 'Saving…' : isEditing ? 'Save changes' : 'Create task'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
