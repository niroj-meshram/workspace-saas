import { beforeEach, describe, expect, it } from 'vitest'
import { screen, within } from '@testing-library/react'
import { DashboardPage } from '@/pages/DashboardPage'
import { renderWithProviders } from '@/test/utils'
import { fail, mockApi, ok } from '@/test/mock-api'
import { acme, alice, projectCreatedActivity, shipIt } from '@/test/fixtures'

const dashboard = {
  stats: { projects: 3, todo: 5, in_progress: 2, completed: 7 },
  my_tasks: [shipIt],
  recent_activity: [projectCreatedActivity],
}

function stubApi(http: ReturnType<typeof mockApi>, body: unknown = dashboard) {
  http.get.mockImplementation((url: string) => {
    if (url === '/auth/me') return ok({ data: alice })
    if (url === '/workspaces') return ok({ data: [acme] })
    if (url.endsWith('/dashboard')) return ok({ data: body })

    return ok({ data: [] })
  })
}

describe('DashboardPage', () => {
  let http: ReturnType<typeof mockApi>

  beforeEach(() => {
    http = mockApi()
  })

  it('shows the four stats', async () => {
    stubApi(http)
    renderWithProviders(<DashboardPage />, { withWorkspace: true })

    // Scoped to the stat strip: "In progress" is also a task status chip.
    const stats = within(await screen.findByRole('region', { name: 'Workspace stats' }))

    // The strip renders skeletons first, so wait for a real figure.
    expect(await stats.findByText('3')).toBeInTheDocument()
    expect(stats.getByText('Projects')).toBeInTheDocument()
    expect(stats.getByText('To do')).toBeInTheDocument()
    expect(stats.getByText('5')).toBeInTheDocument()
    expect(stats.getByText('In progress')).toBeInTheDocument()
    expect(stats.getByText('2')).toBeInTheDocument()
    expect(stats.getByText('Completed')).toBeInTheDocument()
    expect(stats.getByText('7')).toBeInTheDocument()
  })

  it('lists my tasks and recent activity', async () => {
    stubApi(http)
    renderWithProviders(<DashboardPage />, { withWorkspace: true })

    expect(await screen.findByRole('link', { name: 'Ship the API' })).toBeInTheDocument()
    expect(screen.getByText('Alice Chen')).toBeInTheDocument()
    expect(screen.getByText('created project Launch')).toBeInTheDocument()
  })

  it('invites action when nothing is assigned', async () => {
    stubApi(http, { ...dashboard, my_tasks: [], recent_activity: [] })
    renderWithProviders(<DashboardPage />, { withWorkspace: true })

    expect(await screen.findByText('Nothing assigned to you')).toBeInTheDocument()
    expect(screen.getByText('No activity yet')).toBeInTheDocument()
  })

  it('offers a retry when the dashboard fails', async () => {
    http.get.mockImplementation((url: string) => {
      if (url === '/auth/me') return ok({ data: alice })
      if (url === '/workspaces') return ok({ data: [acme] })

      return fail(500, { message: 'Server error' })
    })

    renderWithProviders(<DashboardPage />, { withWorkspace: true })

    expect(await screen.findByRole('alert')).toHaveTextContent("That didn't load")
    expect(screen.getByRole('button', { name: 'Try again' })).toBeInTheDocument()
  })
})
