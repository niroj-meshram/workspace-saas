import { useLocation } from 'react-router'
import { MailCheck } from 'lucide-react'

/**
 * Explains why someone who clicked "Accept invitation" is looking at a sign-in
 * form.
 *
 * Without it the journey is silent: the email says accept, the app says sign
 * in, and nothing connects the two or says which address to use — which is the
 * one thing that decides whether accepting works.
 */
export function InvitationNotice({ mode }: { mode: 'login' | 'register' }) {
  const location = useLocation()
  const from = (location.state as { from?: { pathname: string } } | null)?.from?.pathname

  if (!from?.startsWith('/invitations/')) {
    return null
  }

  return (
    <div className="border-border bg-muted/50 mb-6 flex gap-3 rounded-lg border p-3">
      <MailCheck className="text-muted-foreground mt-0.5 size-4 shrink-0" aria-hidden />
      <p className="text-[0.8125rem] text-pretty">
        <span className="font-medium">You've been invited to a workspace.</span>{' '}
        <span className="text-muted-foreground">
          {mode === 'register'
            ? 'Sign up with the email address the invitation was sent to and you’ll join as soon as your account exists.'
            : 'Sign in — or create an account — with the email address the invitation was sent to, and you’ll join straight away.'}
        </span>
      </p>
    </div>
  )
}
