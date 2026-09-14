import { useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Field, FormError } from '@/components/field'
import { Panel } from '@/components/panel'
import { InvitationNotice } from '@/features/invitations/invitation-notice'
import { useAuth } from '@/features/auth/auth-provider'
import { loginSchema, type LoginValues } from '@/features/auth/schemas'
import { applyApiErrors } from '@/lib/form'

export function LoginPage() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [formError, setFormError] = useState<string | null>(null)

  const redirectTo =
    (location.state as { from?: { pathname: string } } | null)?.from?.pathname ?? '/dashboard'

  const form = useForm<LoginValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: '', password: '' },
  })

  const onSubmit = form.handleSubmit(async (values) => {
    setFormError(null)

    try {
      await login(values)
      navigate(redirectTo, { replace: true })
    } catch (error) {
      setFormError(applyApiErrors(error, form.setError, ['email', 'password']))
    }
  })

  return (
    <Panel className="p-6">
      <InvitationNotice mode="login" />

      <h1 className="text-lg font-semibold tracking-tight">Sign in</h1>
      <p className="text-muted-foreground mt-1 text-sm">Pick up where your team left off.</p>

      <form onSubmit={onSubmit} className="mt-6 space-y-4" noValidate>
        <FormError message={formError} />

        <Field id="email" label="Email" error={form.formState.errors.email?.message}>
          <Input
            id="email"
            type="email"
            autoComplete="email"
            autoFocus
            {...form.register('email')}
            aria-invalid={Boolean(form.formState.errors.email)}
          />
        </Field>

        <Field id="password" label="Password" error={form.formState.errors.password?.message}>
          <Input
            id="password"
            type="password"
            autoComplete="current-password"
            {...form.register('password')}
            aria-invalid={Boolean(form.formState.errors.password)}
          />
        </Field>

        <Button type="submit" className="w-full" disabled={form.formState.isSubmitting}>
          {form.formState.isSubmitting ? 'Signing in…' : 'Sign in'}
        </Button>
      </form>

      <p className="text-muted-foreground mt-6 text-sm">
        New here?{' '}
        <Link
          to="/register"
          state={location.state}
          className="text-foreground font-medium underline underline-offset-4"
        >
          Create an account
        </Link>
      </p>
    </Panel>
  )
}
