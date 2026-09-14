import { beforeEach, describe, expect, it } from 'vitest'
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { TasksPage } from '@/pages/TasksPage'
import { renderWithProviders } from '@/test/utils'
import { fail, mockApi, ok } from '@/test/mock-api'
import { acme, adminMember, alice, launch, paginate, plainMember, shipIt } from '@/test/fixtures'

function stubApi(http: ReturnType<typeof mockApi>, tasks = [shipIt]) {
  http.get.mockImplementation((url: string) => {
    if (url === '/auth/me') return ok({ data: alice })
    if (url === '/workspaces') return ok({ data: [acme] })
    if (url.endsWith('/tasks')) return ok(paginate(tasks))
    if (url.endsWith('/projects')) return ok(paginate([launch]))
    if (url.endsWith('/members')) return ok(paginate([adminMember, plainMember]))

    return ok(paginate([]))
  })
}

describe('TasksPage', () => {
  let http: ReturnType<typeof mockApi>

  beforeEach(() => {
    http = mockApi()
  })

  it('lists tasks with their state', async () => {
    stubApi(http)
    renderWithProviders(<TasksPage />, { withWorkspace: true })

    expect(await screen.findByRole('button', { name: 'Ship the API' })).toBeInTheDocument()
    expect(screen.getByText('In progress')).toBeInTheDocument()
    expect(screen.getByText('High priority')).toBeInTheDocument()
  })

  it('creates a task', async () => {
    const user = userEvent.setup()
    stubApi(http)
    http.post.mockImplementation(() => ok({ data: shipIt }))

    renderWithProviders(<TasksPage />, { withWorkspace: true })

    await user.click(await screen.findByRole('button', { name: /New task/ }))

    const dialog = await screen.findByRole('dialog')
    await user.type(within(dialog).getByLabelText('Title'), 'Write the docs')

    // The project select has to be chosen; the API requires it.
    await user.click(within(dialog).getByLabelText('Project'))
    await user.click(await screen.findByRole('option', { name: 'Launch' }))

    await user.click(within(dialog).getByRole('button', { name: 'Create task' }))

    await waitFor(() =>
      expect(http.post).toHaveBeenCalledWith(
        `/workspaces/${acme.id}/tasks`,
        expect.objectContaining({
          title: 'Write the docs',
          project_id: launch.id,
          status: 'todo',
          priority: 'medium',
          assignee_id: null,
          due_date: null,
        }),
      ),
    )
  })

  it('requires a title and a project', async () => {
    const user = userEvent.setup()
    stubApi(http)

    renderWithProviders(<TasksPage />, { withWorkspace: true })

    await user.click(await screen.findByRole('button', { name: /New task/ }))

    const dialog = await screen.findByRole('dialog')
    await user.click(within(dialog).getByRole('button', { name: 'Create task' }))

    expect(await within(dialog).findByText('Give the task a title.')).toBeInTheDocument()
    expect(within(dialog).getByText('Choose a project.')).toBeInTheDocument()
    expect(http.post).not.toHaveBeenCalled()
  })

  it('edits a task by opening it from the list', async () => {
    const user = userEvent.setup()
    stubApi(http)
    http.patch.mockImplementation(() => ok({ data: shipIt }))

    renderWithProviders(<TasksPage />, { withWorkspace: true })

    await user.click(await screen.findByRole('button', { name: 'Ship the API' }))

    const dialog = await screen.findByRole('dialog')
    const title = within(dialog).getByLabelText('Title')
    expect(title).toHaveValue('Ship the API')

    await user.clear(title)
    await user.type(title, 'Ship the API v2')
    await user.click(within(dialog).getByRole('button', { name: 'Save changes' }))

    await waitFor(() =>
      expect(http.patch).toHaveBeenCalledWith(
        `/workspaces/${acme.id}/tasks/${shipIt.id}`,
        expect.objectContaining({ title: 'Ship the API v2' }),
      ),
    )
  })

  it("surfaces the API's archived-project rule on the project field", async () => {
    const user = userEvent.setup()
    stubApi(http)
    http.post.mockImplementation(() =>
      fail(422, {
        message: 'An archived project cannot receive new tasks.',
        errors: { project_id: ['An archived project cannot receive new tasks.'] },
      }),
    )

    renderWithProviders(<TasksPage />, { withWorkspace: true })

    await user.click(await screen.findByRole('button', { name: /New task/ }))

    const dialog = await screen.findByRole('dialog')
    await user.type(within(dialog).getByLabelText('Title'), 'Late work')
    await user.click(within(dialog).getByLabelText('Project'))
    await user.click(await screen.findByRole('option', { name: 'Launch' }))
    await user.click(within(dialog).getByRole('button', { name: 'Create task' }))

    expect(
      await within(dialog).findByText('An archived project cannot receive new tasks.'),
    ).toBeInTheDocument()
  })

  it('filters by status through the API', async () => {
    const user = userEvent.setup()
    stubApi(http)

    renderWithProviders(<TasksPage />, { withWorkspace: true })

    await screen.findByRole('button', { name: 'Ship the API' })

    await user.click(screen.getByLabelText('Filter by status'))
    await user.click(await screen.findByRole('option', { name: 'Done' }))

    await waitFor(() =>
      expect(http.get).toHaveBeenCalledWith(
        `/workspaces/${acme.id}/tasks`,
        expect.objectContaining({ params: expect.objectContaining({ status: 'done' }) }),
      ),
    )
  })

  it('searches through the API', async () => {
    const user = userEvent.setup()
    stubApi(http)

    renderWithProviders(<TasksPage />, { withWorkspace: true })

    await screen.findByRole('button', { name: 'Ship the API' })
    await user.type(screen.getByLabelText('Search tasks'), 'api')

    await waitFor(
      () =>
        expect(http.get).toHaveBeenCalledWith(
          `/workspaces/${acme.id}/tasks`,
          expect.objectContaining({ params: expect.objectContaining({ search: 'api' }) }),
        ),
      { timeout: 2000 },
    )
  })

  it('confirms before deleting', async () => {
    const user = userEvent.setup()
    stubApi(http)
    http.delete.mockImplementation(() => ok(null))

    renderWithProviders(<TasksPage />, { withWorkspace: true })

    await screen.findByRole('button', { name: 'Ship the API' })
    await user.click(screen.getByRole('button', { name: 'Actions for Ship the API' }))
    await user.click(await screen.findByRole('menuitem', { name: /Delete/ }))

    const confirm = await screen.findByRole('alertdialog')
    expect(http.delete).not.toHaveBeenCalled()

    await user.click(within(confirm).getByRole('button', { name: 'Delete task' }))

    await waitFor(() =>
      expect(http.delete).toHaveBeenCalledWith(`/workspaces/${acme.id}/tasks/${shipIt.id}`),
    )
  })

  it('explains an empty list differently when filters are on', async () => {
    const user = userEvent.setup()
    stubApi(http, [])

    renderWithProviders(<TasksPage />, { withWorkspace: true })

    expect(await screen.findByText('No tasks yet')).toBeInTheDocument()

    await user.click(screen.getByLabelText('Filter by status'))
    await user.click(await screen.findByRole('option', { name: 'Done' }))

    expect(await screen.findByText('Nothing matches those filters')).toBeInTheDocument()
  })
})
