import { Navigate, Outlet, useLocation } from 'react-router'
import { useAuth } from '@/features/auth/auth-provider'
import { FullPageSpinner } from '@/components/full-page-spinner'

/**
 * Gate for everything behind sign-in (PROJECT_SPEC.md §22).
 *
 * This is navigation, not security: the API refuses an unauthenticated request
 * regardless of what the client renders.
 */
export function ProtectedRoute() {
  const { isAuthenticated, isLoading } = useAuth()
  const location = useLocation()

  if (isLoading) {
    return <FullPageSpinner label="Loading your workspaces" />
  }

  if (!isAuthenticated) {
    // Remember where they were headed so sign-in can finish the journey.
    return <Navigate to="/login" state={{ from: location }} replace />
  }

  return <Outlet />
}

/** Keeps a signed-in user out of the sign-in and sign-up screens. */
export function GuestRoute() {
  const { isAuthenticated, isLoading } = useAuth()
  const location = useLocation()

  if (isLoading) {
    return <FullPageSpinner label="Loading" />
  }

  if (isAuthenticated) {
    // Honour a pending destination, the way the pages themselves do. The
    // moment signing in succeeds this gate re-renders, and it would otherwise
    // race the page's own redirect and win — dropping someone who arrived
    // from an invitation link on the dashboard instead of the invitation.
    const pending = (location.state as { from?: { pathname: string } } | null)?.from?.pathname

    return <Navigate to={pending ?? '/dashboard'} replace />
  }

  return <Outlet />
}
