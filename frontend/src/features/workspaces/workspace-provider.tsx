import {
  createContext,
  use,
  useCallback,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { useWorkspaces } from '@/features/workspaces/hooks'
import type { Workspace, WorkspaceRole } from '@/types/api'

const STORAGE_KEY = 'workspace-saas:selected-workspace'

interface WorkspaceContextValue {
  workspaces: Workspace[]
  workspace: Workspace | null
  workspaceId: string | null
  role: WorkspaceRole | null
  /** UX only. Every permission is re-checked by Laravel. */
  isAdmin: boolean
  isLoading: boolean
  isError: boolean
  refetch: () => void
  select: (workspaceId: string) => void
}

const WorkspaceContext = createContext<WorkspaceContextValue | null>(null)

function readStored(): string | null {
  try {
    return localStorage.getItem(STORAGE_KEY)
  } catch {
    // Private browsing, blocked storage: fall back to no preference.
    return null
  }
}

/**
 * Remember a workspace as the next one to open.
 *
 * Exported because the invitation screen runs outside this provider — the
 * visitor may not have been a member of anything yet — and still needs the
 * workspace it just joined to be the one that loads.
 */
export function rememberWorkspace(workspaceId: string): void {
  writeStored(workspaceId)
}

function writeStored(workspaceId: string | null): void {
  try {
    if (workspaceId === null) localStorage.removeItem(STORAGE_KEY)
    else localStorage.setItem(STORAGE_KEY, workspaceId)
  } catch {
    // Remembering the choice is a convenience, never a requirement.
  }
}

/**
 * Which workspace the user is looking at (PROJECT_SPEC.md §20).
 *
 * This is interface state. It decides which workspace id the client puts in a
 * URL; it decides nothing about access. The server resolves the tenant from
 * that route and verifies membership on every request.
 */
export function WorkspaceProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient()
  const { data, isLoading, isError, refetch } = useWorkspaces()
  const [selectedId, setSelectedId] = useState<string | null>(readStored)

  const workspaces = useMemo(() => data ?? [], [data])

  const workspace = useMemo(() => {
    if (workspaces.length === 0) return null

    // A remembered id is only honoured if it is still one of ours: membership
    // can be revoked between visits.
    return workspaces.find((candidate) => candidate.id === selectedId) ?? workspaces[0]
  }, [workspaces, selectedId])

  // Persist whatever ended up on screen. The fallback to the first workspace
  // already happens during render, so there is no state to sync here.
  useEffect(() => {
    if (workspace) {
      writeStored(workspace.id)

      return
    }

    if (!isLoading && workspaces.length === 0) {
      writeStored(null)
    }
  }, [workspace, workspaces.length, isLoading])

  const select = useCallback(
    (workspaceId: string) => {
      if (workspaceId === workspace?.id) return

      setSelectedId(workspaceId)
      writeStored(workspaceId)
      // Everything cached under the workspace key belongs to the tenant we
      // are leaving, so it is dropped rather than shown to the next one.
      queryClient.removeQueries({ queryKey: ['workspace'] })
    },
    [queryClient, workspace?.id],
  )

  return (
    <WorkspaceContext
      value={{
        workspaces,
        workspace,
        workspaceId: workspace?.id ?? null,
        role: workspace?.role ?? null,
        isAdmin: workspace?.role === 'admin',
        isLoading,
        isError,
        refetch,
        select,
      }}
    >
      {children}
    </WorkspaceContext>
  )
}

export function useWorkspace(): WorkspaceContextValue {
  const context = use(WorkspaceContext)

  if (!context) {
    throw new Error('useWorkspace must be used inside a WorkspaceProvider.')
  }

  return context
}

/**
 * Same context, but tolerated when absent.
 *
 * The invitation screen lives outside the workspace shell — the visitor may
 * not be a member of anything yet — and only wants to pre-select the workspace
 * it just joined.
 */
export function useOptionalWorkspace(): WorkspaceContextValue | null {
  return use(WorkspaceContext)
}

/**
 * The current workspace id, for hooks that cannot run without one.
 * Components under the app layout always have one.
 */
export function useWorkspaceId(): string {
  const { workspaceId } = useWorkspace()

  if (!workspaceId) {
    throw new Error('No workspace selected.')
  }

  return workspaceId
}
