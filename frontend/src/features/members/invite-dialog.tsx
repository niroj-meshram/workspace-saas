import { useEffect } from 'react'
import { useForm, useWatch } from 'react-hook-form'
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Field, FormError } from '@/components/field'
import { useInviteMember } from '@/features/invitations/hooks'
import { applyApiErrors } from '@/lib/form'
import type { WorkspaceRole } from '@/types/api'

const schema = z.object({
  email: z.string().min(1, 'Enter an email address.').email('Enter a valid email address.'),
  role: z.enum(['admin', 'user']),
})

type Values = z.infer<typeof schema>

export function InviteDialog({
  workspaceId,
  open,
  onOpenChange,
}: {
  workspaceId: string
  open: boolean
  onOpenChange: (open: boolean) => void
}) {
  const inviteMember = useInviteMember(workspaceId)

  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: { email: '', role: 'user' },
  })

  useEffect(() => {
    if (!open) return

    // reset() also clears the form-level error from the previous attempt.
    form.reset({ email: '', role: 'user' })
  }, [open, form])

  const onSubmit = form.handleSubmit(async (values) => {
    try {
      await inviteMember.mutateAsync(values)
      toast.success(`Invitation sent to ${values.email}`)
      onOpenChange(false)
    } catch (error) {
      // Already a member, or already invited, comes back as a 409 with no
      // field attached, so it shows above the form.
      const message = applyApiErrors(error, form.setError, ['email', 'role'])

      if (message) form.setError('root', { message })
    }
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Invite someone</DialogTitle>
          <DialogDescription>
            They'll get an email with a link. It works for seven days.
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={onSubmit} className="space-y-4" noValidate>
          <FormError message={form.formState.errors.root?.message} />

          <Field id="invite-email" label="Email" error={form.formState.errors.email?.message}>
            <Input
              id="invite-email"
              type="email"
              autoFocus
              placeholder="teammate@example.com"
              {...form.register('email')}
              aria-invalid={Boolean(form.formState.errors.email)}
            />
          </Field>

          <Field
            id="invite-role"
            label="Role"
            hint="Admins manage projects, members and invitations."
            error={form.formState.errors.role?.message}
          >
            <Select
              value={useWatch({ control: form.control, name: 'role' })}
              onValueChange={(value) => form.setValue('role', value as WorkspaceRole)}
            >
              <SelectTrigger id="invite-role" className="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="user">Member</SelectItem>
                <SelectItem value="admin">Admin</SelectItem>
              </SelectContent>
            </Select>
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
              {form.formState.isSubmitting ? 'Sending…' : 'Send invitation'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
