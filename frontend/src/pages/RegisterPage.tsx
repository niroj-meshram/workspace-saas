import { useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Field, FormError } from '@/components/field'
import { Panel } from '@/components/panel'
import { InvitationNotice } from '@/features/invitations/invitation-notice'
import { useAuth } from '@/features/auth/auth-provider'
import { registerSchema, type RegisterValues } from '@/features/auth/schemas'
import { applyApiErrors } from '@/lib/form'

export function RegisterPage() {
  const { register: registerAccount, login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [formError, setFormError] = useState<string | null>(null)

  const redirectTo =
    (location.state as { from?: { pathname: string } } | null)?.from?.pathname ?? '/dashboard'

  const form = useForm<RegisterValues>({
    resolver: zodResolver(registerSchema),
    defaultValues: { name: '', email: '', password: '', password_confirmation: '' },
  })

  const onSubmit = form.handleSubmit(async (values) => {
    setFormError(null)

    try {
      await registerAccount(values)
      // The API creates the account without a session, so sign in to continue.
      await login({ email: values.email, password: values.password })
      toast.success('Welcome aboard')
      navigate(redirectTo, { replace: true })
    } catch (error) {
      setFormError(
        applyApiErrors(error, form.setError, [
          'name',
          'email',
          'password',
          'password_confirmation',
        ]),
      )
    }
  })

  return (
    <Panel className="p-6">
      <InvitationNotice mode="register" />

      <h1 className="text-lg font-semibold tracking-tight">Create your account</h1>
      <p className="text-muted-foreground mt-1 text-sm">
        Takes a moment. You can set up a workspace straight after.
      </p>

      <form onSubmit={onSubmit} className="mt-6 space-y-4" noValidate>
        <FormError message={formError} />

        <Field id="name" label="Name" error={form.formState.errors.name?.message}>
          <Input
            id="name"
            autoComplete="name"
            autoFocus
            {...form.register('name')}
            aria-invalid={Boolean(form.formState.errors.name)}
          />
        </Field>

        <Field id="email" label="Email" error={form.formState.errors.email?.message}>
          <Input
            id="email"
            type="email"
            autoComplete="email"
            {...form.register('email')}
            aria-invalid={Boolean(form.formState.errors.email)}
          />
        </Field>

        <Field
          id="password"
          label="Password"
          hint="At least 8 characters."
          error={form.formState.errors.password?.message}
        >
          <Input
            id="password"
            type="password"
            autoComplete="new-password"
            {...form.register('password')}
            aria-invalid={Boolean(form.formState.errors.password)}
          />
        </Field>

        <Field
          id="password_confirmation"
          label="Confirm password"
          error={form.formState.errors.password_confirmation?.message}
        >
          <Input
            id="password_confirmation"
            type="password"
            autoComplete="new-password"
            {...form.register('password_confirmation')}
            aria-invalid={Boolean(form.formState.errors.password_confirmation)}
          />
        </Field>

        <Button type="submit" className="w-full" disabled={form.formState.isSubmitting}>
          {form.formState.isSubmitting ? 'Creating account…' : 'Create account'}
        </Button>
      </form>

      <p className="text-muted-foreground mt-6 text-sm">
        Already have an account?{' '}
        <Link
          to="/login"
          state={location.state}
          className="text-foreground font-medium underline underline-offset-4"
        >
          Sign in
        </Link>
      </p>
    </Panel>
  )
}
