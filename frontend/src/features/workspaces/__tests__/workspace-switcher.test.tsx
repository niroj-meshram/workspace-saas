import { describe, expect, it, beforeEach } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { WorkspaceSwitcher } from '@/features/workspaces/workspace-switcher'
import { useWorkspace } from '@/features/workspaces/workspace-provider'
import { renderWithProviders } from '@/test/utils'
import { mockApi, ok } from '@/test/mock-api'
import { acme, alice, beta } from '@/test/fixtures'

function CurrentWorkspace() {
  const { workspace, isAdmin } = useWorkspace()

  return (
    <p>
      Viewing {workspace?.name ?? 'nothing'} as {isAdmin ? 'admin' : 'member'}
    </p>
  )
}

function route(path: string) {
  return path
}

describe('workspace switching', () => {
  let http: ReturnType<typeof mockApi>

  beforeEach(() => {
    http = mockApi()
    http.get.mockImplementation((url: string) => {
      if (url === '/auth/me') return ok({ data: alice })
      if (url === '/workspaces') return ok({ data: [acme, beta] })

      return ok({ data: [] })
    })
  })

  it('opens on the first workspace when nothing is remembered', async () => {
    renderWithProviders(
      <>
        <WorkspaceSwitcher />
        <CurrentWorkspace />
      </>,
      { withWorkspace: true, route: route('/dashboard') },
    )

    expect(await screen.findByText('Viewing Acme as admin')).toBeInTheDocument()
  })

  it('switches workspace and reports the new role', async () => {
    const user = userEvent.setup()

    renderWithProviders(
      <>
        <WorkspaceSwitcher />
        <CurrentWorkspace />
      </>,
      { withWorkspace: true },
    )

    await screen.findByText('Viewing Acme as admin')

    await user.click(screen.getByRole('button', { name: 'Switch workspace' }))
    await user.click(await screen.findByRole('menuitem', { name: /Beta/ }))

    expect(await screen.findByText('Viewing Beta as member')).toBeInTheDocument()
  })

  it('remembers the choice for the next visit', async () => {
    const user = userEvent.setup()

    const { unmount } = renderWithProviders(
      <>
        <WorkspaceSwitcher />
        <CurrentWorkspace />
      </>,
      { withWorkspace: true },
    )

    await screen.findByText('Viewing Acme as admin')
    await user.click(screen.getByRole('button', { name: 'Switch workspace' }))
    await user.click(await screen.findByRole('menuitem', { name: /Beta/ }))
    await screen.findByText('Viewing Beta as member')

    unmount()

    renderWithProviders(<CurrentWorkspace />, { withWorkspace: true })

    expect(await screen.findByText('Viewing Beta as member')).toBeInTheDocument()
  })

  it('falls back to a real workspace when the remembered one is gone', async () => {
    localStorage.setItem('workspace-saas:selected-workspace', 'ws-removed')

    renderWithProviders(<CurrentWorkspace />, { withWorkspace: true })

    expect(await screen.findByText('Viewing Acme as admin')).toBeInTheDocument()
  })

  it('drops cached tenant data when the workspace changes', async () => {
    const user = userEvent.setup()

    const { client } = renderWithProviders(
      <>
        <WorkspaceSwitcher />
        <CurrentWorkspace />
      </>,
      { withWorkspace: true },
    )

    client.setQueryData(['workspace', acme.id, 'tasks'], { data: [] })

    await screen.findByText('Viewing Acme as admin')
    await user.click(screen.getByRole('button', { name: 'Switch workspace' }))
    await user.click(await screen.findByRole('menuitem', { name: /Beta/ }))

    await waitFor(() =>
      expect(client.getQueryData(['workspace', acme.id, 'tasks'])).toBeUndefined(),
    )
  })
})
