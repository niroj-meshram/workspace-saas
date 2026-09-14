import { describe, expect, it, beforeEach } from 'vitest'
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { ProjectsPage } from '@/pages/ProjectsPage'
import { renderWithProviders } from '@/test/utils'
import { fail, mockApi, ok } from '@/test/mock-api'
import { acme, alice, archivedProject, beta, launch, paginate } from '@/test/fixtures'

function stubApi(http: ReturnType<typeof mockApi>, workspaces = [acme]) {
  http.get.mockImplementation((url: string) => {
    if (url === '/auth/me') return ok({ data: alice })
    if (url === '/workspaces') return ok({ data: workspaces })
    if (url.endsWith('/projects')) return ok(paginate([launch, archivedProject]))

    return ok(paginate([]))
  })
}

describe('ProjectsPage', () => {
  let http: ReturnType<typeof mockApi>

  beforeEach(() => {
    http = mockApi()
  })

  it('lists projects with their state', async () => {
    stubApi(http)
    renderWithProviders(<ProjectsPage />, { withWorkspace: true })

    expect(await screen.findByRole('link', { name: 'Launch' })).toBeInTheDocument()
    expect(screen.getByText('Active')).toBeInTheDocument()
    expect(screen.getByText('Archived')).toBeInTheDocument()
  })

  it('offers admin controls to an admin', async () => {
    stubApi(http)
    renderWithProviders(<ProjectsPage />, { withWorkspace: true })

    // Wait for the list, not just the header: the header renders first.
    await screen.findByRole('link', { name: 'Launch' })

    expect(screen.getByRole('button', { name: /New project/ })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Actions for Launch' })).toBeInTheDocument()
  })

  it('hides admin controls from a member', async () => {
    // beta is the workspace where this user is a plain member.
    stubApi(http, [beta])
    renderWithProviders(<ProjectsPage />, { withWorkspace: true })

    await screen.findByRole('link', { name: 'Launch' })

    expect(screen.queryByRole('button', { name: /New project/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Actions for Launch' })).not.toBeInTheDocument()
  })

  it('creates a project', async () => {
    const user = userEvent.setup()
    stubApi(http)
    http.post.mockImplementation(() => ok({ data: { ...launch, name: 'Roadmap' } }))

    renderWithProviders(<ProjectsPage />, { withWorkspace: true })

    await user.click(await screen.findByRole('button', { name: /New project/ }))

    const dialog = await screen.findByRole('dialog')
    await user.type(within(dialog).getByLabelText('Name'), 'Roadmap')
    await user.click(within(dialog).getByRole('button', { name: 'Create project' }))

    await waitFor(() =>
      expect(http.post).toHaveBeenCalledWith(`/workspaces/${acme.id}/projects`, {
        name: 'Roadmap',
        description: null,
      }),
    )
  })

  it('shows a duplicate name error beside the field', async () => {
    const user = userEvent.setup()
    stubApi(http)
    http.post.mockImplementation(() =>
      fail(422, {
        message: 'A project with this name already exists in this workspace.',
        errors: { name: ['A project with this name already exists in this workspace.'] },
      }),
    )

    renderWithProviders(<ProjectsPage />, { withWorkspace: true })

    await user.click(await screen.findByRole('button', { name: /New project/ }))

    const dialog = await screen.findByRole('dialog')
    await user.type(within(dialog).getByLabelText('Name'), 'Launch')
    await user.click(within(dialog).getByRole('button', { name: 'Create project' }))

    expect(
      await within(dialog).findByText('A project with this name already exists in this workspace.'),
    ).toBeInTheDocument()
  })

  it('confirms before archiving', async () => {
    const user = userEvent.setup()
    stubApi(http)
    http.delete.mockImplementation(() => ok(null))

    renderWithProviders(<ProjectsPage />, { withWorkspace: true })

    await screen.findByRole('link', { name: 'Launch' })
    await user.click(screen.getByRole('button', { name: 'Actions for Launch' }))
    await user.click(await screen.findByRole('menuitem', { name: /Archive/ }))

    const confirm = await screen.findByRole('alertdialog')
    expect(within(confirm).getByText(/Archive Launch\?/)).toBeInTheDocument()
    expect(http.delete).not.toHaveBeenCalled()

    await user.click(within(confirm).getByRole('button', { name: 'Archive project' }))

    await waitFor(() =>
      expect(http.delete).toHaveBeenCalledWith(`/workspaces/${acme.id}/projects/${launch.id}`),
    )
  })

  it('invites the admin to act when there are no projects', async () => {
    http.get.mockImplementation((url: string) => {
      if (url === '/auth/me') return ok({ data: alice })
      if (url === '/workspaces') return ok({ data: [acme] })

      return ok(paginate([]))
    })

    renderWithProviders(<ProjectsPage />, { withWorkspace: true })

    expect(await screen.findByText('No projects yet')).toBeInTheDocument()
    expect(screen.getByText('Create a project to start grouping tasks.')).toBeInTheDocument()
  })

  it('offers a retry when the list fails', async () => {
    http.get.mockImplementation((url: string) => {
      if (url === '/auth/me') return ok({ data: alice })
      if (url === '/workspaces') return ok({ data: [acme] })

      return fail(500, { message: 'Server error' })
    })

    renderWithProviders(<ProjectsPage />, { withWorkspace: true })

    expect(await screen.findByRole('alert')).toHaveTextContent("That didn't load")
    expect(screen.getByRole('button', { name: 'Try again' })).toBeInTheDocument()
  })
})
