import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Panel, PanelHeader } from '@/components/panel'
import { PageHeader } from '@/components/page-header'
import { Field, FormError } from '@/components/field'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { RoleBadge } from '@/components/role-badge'
import { useDeleteWorkspace, useUpdateWorkspace } from '@/features/workspaces/hooks'
import { useWorkspace } from '@/features/workspaces/workspace-provider'
import { useAuth } from '@/features/auth/auth-provider'
import { applyApiErrors } from '@/lib/form'
import { toApiError } from '@/lib/api-error'

const schema = z.object({
  name: z.string().min(1, 'Give the workspace a name.').max(255, 'Keep this under 255 characters.'),
})

type Values = z.infer<typeof schema>

export function SettingsPage() {
  const { workspace, isAdmin, role } = useWorkspace()
  const { user } = useAuth()
  const workspaceId = workspace!.id

  const updateWorkspace = useUpdateWorkspace(workspaceId)
  const deleteWorkspace = useDeleteWorkspace()
  const [formError, setFormError] = useState<string | null>(null)
  const [deleting, setDeleting] = useState(false)

  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: { name: workspace!.name },
  })

  useEffect(() => {
    form.reset({ name: workspace!.name })
  }, [workspace, form])

  const onSubmit = form.handleSubmit(async (values) => {
    setFormError(null)

    try {
      await updateWorkspace.mutateAsync(values.name)
      toast.success('Workspace renamed')
    } catch (error) {
      setFormError(applyApiErrors(error, form.setError, ['name']))
    }
  })

  const confirmDelete = async () => {
    try {
      await deleteWorkspace.mutateAsync(workspaceId)
      toast.success('Workspace deleted')
      setDeleting(false)
    } catch (error) {
      toast.error(toApiError(error).message)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader title="Settings" description="Details for this workspace and your account." />

      <Panel>
        <PanelHeader title="Workspace" />

        <div className="px-5 py-4">
          {isAdmin ? (
            <form onSubmit={onSubmit} className="max-w-sm space-y-4" noValidate>
              <FormError message={formError} />

              <Field id="settings-name" label="Name" error={form.formState.errors.name?.message}>
                <Input
                  id="settings-name"
                  {...form.register('name')}
                  aria-invalid={Boolean(form.formState.errors.name)}
                />
              </Field>

              <Button type="submit" disabled={form.formState.isSubmitting}>
                {form.formState.isSubmitting ? 'Saving…' : 'Save changes'}
              </Button>
            </form>
          ) : (
            <div className="space-y-1">
              <p className="text-sm font-medium">{workspace!.name}</p>
              <p className="text-muted-foreground text-sm">
                Only admins can change the workspace name.
              </p>
            </div>
          )}
        </div>
      </Panel>

      <Panel>
        <PanelHeader title="You" />

        <dl className="divide-border divide-y text-sm">
          <div className="flex items-center justify-between gap-4 px-5 py-3">
            <dt className="text-muted-foreground">Name</dt>
            <dd className="font-medium">{user?.name}</dd>
          </div>
          <div className="flex items-center justify-between gap-4 px-5 py-3">
            <dt className="text-muted-foreground">Email</dt>
            <dd className="truncate font-medium">{user?.email}</dd>
          </div>
          <div className="flex items-center justify-between gap-4 px-5 py-3">
            <dt className="text-muted-foreground">Role here</dt>
            <dd>{role ? <RoleBadge role={role} /> : null}</dd>
          </div>
        </dl>
      </Panel>

      {isAdmin ? (
        <Panel className="border-destructive/30">
          <PanelHeader title="Delete workspace" className="border-destructive/30" />

          <div className="flex flex-wrap items-center justify-between gap-4 px-5 py-4">
            <p className="text-muted-foreground max-w-md text-sm text-pretty">
              Everyone loses access to {workspace!.name}. Projects, tasks and history are kept, and
              nothing is erased.
            </p>
            <Button variant="destructive" onClick={() => setDeleting(true)}>
              Delete workspace
            </Button>
          </div>
        </Panel>
      ) : null}

      <ConfirmDialog
        open={deleting}
        onOpenChange={setDeleting}
        title={`Delete ${workspace!.name}?`}
        description="It disappears for every member, including you. The underlying records are retained but nobody will be able to reach them from the app."
        confirmLabel="Delete workspace"
        onConfirm={confirmDelete}
        isPending={deleteWorkspace.isPending}
      />
    </div>
  )
}
