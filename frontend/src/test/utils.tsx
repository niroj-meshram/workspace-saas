import type { ReactElement, ReactNode } from 'react'
import { render, type RenderOptions } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router'
import { AuthProvider } from '@/features/auth/auth-provider'
import { WorkspaceProvider } from '@/features/workspaces/workspace-provider'
import { WorkspaceGate } from '@/features/workspaces/workspace-gate'

/** A query client that fails fast and stays quiet, for tests. */
export function testQueryClient() {
  return new QueryClient({
    defaultOptions: {
      queries: { retry: false, gcTime: 0, staleTime: 0 },
      mutations: { retry: false },
    },
  })
}

interface Options extends Omit<RenderOptions, 'wrapper'> {
  route?: string
  /** Router state for the initial entry, as a <Navigate state={…}> would set. */
  state?: unknown
  withAuth?: boolean
  withWorkspace?: boolean
  queryClient?: QueryClient
}

/**
 * Render a component with the providers the app gives it.
 *
 * Tests drive the real hooks against a mocked HTTP layer rather than stubbing
 * the hooks, so what they exercise is what ships.
 */
export function renderWithProviders(
  ui: ReactElement,
  {
    route = '/',
    state,
    withAuth = true,
    withWorkspace = false,
    queryClient,
    ...options
  }: Options = {},
) {
  const client = queryClient ?? testQueryClient()

  function Wrapper({ children }: { children: ReactNode }) {
    let tree = children

    if (withWorkspace) {
      // The same gate the app layout uses, so a screen under test gets the
      // selected workspace it is written to rely on.
      tree = (
        <WorkspaceProvider>
          <WorkspaceGate>{tree}</WorkspaceGate>
        </WorkspaceProvider>
      )
    }
    if (withAuth) tree = <AuthProvider>{tree}</AuthProvider>

    return (
      <QueryClientProvider client={client}>
        <MemoryRouter initialEntries={[{ pathname: route, state }]}>{tree}</MemoryRouter>
      </QueryClientProvider>
    )
  }

  return { client, ...render(ui, { wrapper: Wrapper, ...options }) }
}
