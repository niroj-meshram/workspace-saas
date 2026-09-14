import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
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
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Field, FormError } from '@/components/field'
import { useCreateProject, useUpdateProject } from '@/features/projects/hooks'
import { projectSchema, type ProjectValues } from '@/features/projects/schemas'
import { applyApiErrors } from '@/lib/form'
import type { Project } from '@/types/api'

const FIELDS = ['name', 'description'] as const

/** Creates when given no project, edits when given one. */
export function ProjectDialog({
  workspaceId,
  project,
  open,
  onOpenChange,
}: {
  workspaceId: string
  project?: Project
  open: boolean
  onOpenChange: (open: boolean) => void
}) {
  const isEditing = Boolean(project)

  const createProject = useCreateProject(workspaceId)
  const updateProject = useUpdateProject(workspaceId, project?.id ?? '')

  const form = useForm<ProjectValues>({
    resolver: zodResolver(projectSchema),
    defaultValues: { name: '', description: '' },
  })

  useEffect(() => {
    if (!open) return

    // reset() also clears the form-level error from the previous attempt.
    form.reset({ name: project?.name ?? '', description: project?.description ?? '' })
  }, [open, project, form])

  const onSubmit = form.handleSubmit(async (values) => {
    const payload = {
      name: values.name,
      description: values.description?.trim() ? values.description : null,
    }

    try {
      if (isEditing) {
        await updateProject.mutateAsync(payload)
        toast.success('Project updated')
      } else {
        await createProject.mutateAsync(payload)
        toast.success('Project created')
      }

      onOpenChange(false)
    } catch (error) {
      const message = applyApiErrors(error, form.setError, FIELDS)

      if (message) form.setError('root', { message })
    }
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{isEditing ? 'Edit project' : 'New project'}</DialogTitle>
          <DialogDescription>
            {isEditing
              ? 'Rename the project or update what it covers.'
              : 'Group related work. Project names are unique inside a workspace.'}
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={onSubmit} className="space-y-4" noValidate>
          <FormError message={form.formState.errors.root?.message} />

          <Field id="project-name" label="Name" error={form.formState.errors.name?.message}>
            <Input
              id="project-name"
              autoFocus
              placeholder="Launch"
              {...form.register('name')}
              aria-invalid={Boolean(form.formState.errors.name)}
            />
          </Field>

          <Field
            id="project-description"
            label="Description"
            hint="Optional."
            error={form.formState.errors.description?.message}
          >
            <Textarea
              id="project-description"
              rows={3}
              placeholder="What this project covers"
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
              {form.formState.isSubmitting
                ? 'Saving…'
                : isEditing
                  ? 'Save changes'
                  : 'Create project'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
