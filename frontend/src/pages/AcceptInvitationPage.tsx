import { useEffect, useRef, useState } from 'react'
import { Navigate, useNavigate, useParams } from 'react-router'
import { CheckCircle2, Clock, MailQuestion, ShieldX } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Panel } from '@/components/panel'
import { EmptyState } from '@/components/states'
import { FullPageSpinner } from '@/components/full-page-spinner'
import { useAuth } from '@/features/auth/auth-provider'
import { useAcceptInvitation } from '@/features/invitations/hooks'
import { rememberWorkspace } from '@/features/workspaces/workspace-provider'
import { toApiError } from '@/lib/api-error'

/**
 * Accepting an invitation (PROJECT_SPEC.md §10).
 *
 * The link itself is public, so an unauthenticated visitor is sent to sign in
 * with this page remembered as the destination; the token stays in the URL and
 * they land back here afterwards. Acceptance itself always needs a session.
 */
export function AcceptInvitationPage() {
  const { token = '' } = useParams()
  const { isAuthenticated, isLoading, user } = useAuth()
  const navigate = useNavigate()
  const acceptInvitation = useAcceptInvitation()
  const [outcome, setOutcome] = useState<{
    kind: 'error'
    status: number | null
    message: string
  } | null>(null)
  const attempted = useRef(false)

  useEffect(() => {
    if (!isAuthenticated || attempted.current || !token) return

    attempted.current = true

    acceptInvitation
      .mutateAsync(token)
      .then((workspace) => {
        // Land in the workspace they just joined, not whichever was last open.
        rememberWorkspace(workspace.id)
        toast.success(`You've joined ${workspace.name}`)
        navigate('/dashboard', { replace: true })
      })
      .catch((error) => {
        const apiError = toApiError(error)
        setOutcome({ kind: 'error', status: apiError.status, message: apiError.message })
      })
  }, [isAuthenticated, token, acceptInvitation, navigate])

  if (isLoading) {
    return <FullPageSpinner label="Checking your invitation" />
  }

  if (!isAuthenticated) {
    // Preserve the token by remembering this exact URL as the destination.
    return <Navigate to="/login" state={{ from: { pathname: `/invitations/${token}` } }} replace />
  }

  if (!outcome) {
    return <FullPageSpinner label="Accepting your invitation" />
  }

  const { status, message } = outcome

  // 404 unknown or revoked, 403 wrong account, 409 already used or expired.
  const view =
    status === 403
      ? {
          icon: ShieldX,
          title: 'This invitation is for someone else',
          description: `It was sent to a different email address. You're signed in as ${user?.email}. Sign in with the invited address to accept it.`,
        }
      : status === 409
        ? {
            icon: message.toLowerCase().includes('expired') ? Clock : CheckCircle2,
            title: message.toLowerCase().includes('expired')
              ? 'This invitation has expired'
              : 'This invitation has already been used',
            description: message.toLowerCase().includes('expired')
              ? 'Invitations last seven days. Ask an admin of the workspace to send a new one.'
              : 'Nothing more to do here. If you should have access, ask an admin to check your membership.',
          }
        : {
            icon: MailQuestion,
            title: "We couldn't find that invitation",
            description:
              'The link may have been withdrawn or mistyped. Ask an admin of the workspace to send a new one.',
          }

  return (
    <div className="grid min-h-dvh place-items-center px-4 py-10">
      <div className="w-full max-w-md">
        <Panel>
          <EmptyState
            icon={view.icon}
            title={view.title}
            description={view.description}
            action={<Button onClick={() => navigate('/dashboard')}>Go to your workspaces</Button>}
          />
        </Panel>
      </div>
    </div>
  )
}
