import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
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
import { Field, FormError } from '@/components/field'
import { useCreateWorkspace } from '@/features/workspaces/hooks'
import { useWorkspace } from '@/features/workspaces/workspace-provider'
import { toApiError } from '@/lib/api-error'

const schema = z.object({
  name: z.string().min(1, 'Give the workspace a name.').max(255, 'Keep this under 255 characters.'),
})

type Values = z.infer<typeof schema>

export function CreateWorkspaceDialog({
  open,
  onOpenChange,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
}) {
  const createWorkspace = useCreateWorkspace()
  const { select } = useWorkspace()

  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: { name: '' },
  })

  useEffect(() => {
    if (open) form.reset({ name: '' })
  }, [open, form])

  const onSubmit = form.handleSubmit(async (values) => {
    try {
      const workspace = await createWorkspace.mutateAsync(values.name)
      select(workspace.id)
      toast.success(`${workspace.name} created`)
      onOpenChange(false)
    } catch (error) {
      const apiError = toApiError(error)

      // Laravel's field errors replace whatever Zod said.
      Object.entries(apiError.errors).forEach(([field, messages]) => {
        form.setError(field as keyof Values, { message: messages[0] })
      })

      if (Object.keys(apiError.errors).length === 0) {
        form.setError('root', { message: apiError.message })
      }
    }
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>New workspace</DialogTitle>
          <DialogDescription>
            You'll be its first admin. Invite the rest of the team once it exists.
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={onSubmit} className="space-y-4" noValidate>
          <FormError message={form.formState.errors.root?.message} />

          <Field id="workspace-name" label="Name" error={form.formState.errors.name?.message}>
            <Input
              id="workspace-name"
              autoFocus
              placeholder="Acme"
              {...form.register('name')}
              aria-invalid={Boolean(form.formState.errors.name)}
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
              {form.formState.isSubmitting ? 'Creating…' : 'Create workspace'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
