import type { ReactNode } from 'react'
import { useWorkspace } from '@/features/workspaces/workspace-provider'
import { NoWorkspaceScreen } from '@/features/workspaces/no-workspace-screen'
import { FullPageSpinner } from '@/components/full-page-spinner'
import { ErrorState } from '@/components/states'

/**
 * Holds back anything that needs a workspace until there is one.
 *
 * Screens below this point can rely on a selected workspace existing, which is
 * what lets them read it without defending against null on every line. The
 * guarantee lives here rather than being repeated, so it holds wherever the
 * gate is used.
 */
export function WorkspaceGate({ children }: { children: ReactNode }) {
  const { workspace, isLoading, isError, refetch } = useWorkspace()

  if (isLoading) {
    return <FullPageSpinner label="Loading your workspaces" />
  }

  if (isError) {
    return (
      <div className="grid min-h-dvh place-items-center px-4">
        <ErrorState
          title="Couldn't load your workspaces"
          description="The server didn't answer. Check your connection and try again."
          onRetry={refetch}
        />
      </div>
    )
  }

  if (!workspace) {
    return <NoWorkspaceScreen />
  }

  return children
}
