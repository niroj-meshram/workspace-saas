import { beforeEach, describe, expect, it } from 'vitest'
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MembersPage } from '@/pages/MembersPage'
import { renderWithProviders } from '@/test/utils'
import { fail, mockApi, ok } from '@/test/mock-api'
import {
  acme,
  adminMember,
  alice,
  beta,
  paginate,
  pendingInvitation,
  plainMember,
} from '@/test/fixtures'

function stubApi(
  http: ReturnType<typeof mockApi>,
  { workspaces = [acme], members = [adminMember, plainMember] } = {},
) {
  http.get.mockImplementation((url: string) => {
    if (url === '/auth/me') return ok({ data: alice })
    if (url === '/workspaces') return ok({ data: workspaces })
    if (url.endsWith('/members')) return ok(paginate(members))
    if (url.endsWith('/invitations')) return ok(paginate([pendingInvitation]))

    return ok(paginate([]))
  })
}

describe('MembersPage', () => {
  let http: ReturnType<typeof mockApi>

  beforeEach(() => {
    http = mockApi()
  })

  it('lists members with their role', async () => {
    stubApi(http)
    renderWithProviders(<MembersPage />, { withWorkspace: true })

    expect(await screen.findByText('Bob Ray')).toBeInTheDocument()
    // The signed-in member is marked so nobody has to work out which row is theirs.
    expect(screen.getByText('(you)')).toBeInTheDocument()
    expect(screen.getByText('Admin')).toBeInTheDocument()
    expect(screen.getByText('Member')).toBeInTheDocument()
  })

  it('lets an admin promote a member', async () => {
    const user = userEvent.setup()
    stubApi(http)
    http.patch.mockImplementation(() => ok({ data: { ...plainMember, role: 'admin' } }))

    renderWithProviders(<MembersPage />, { withWorkspace: true })

    await screen.findByText('Bob Ray')
    await user.click(screen.getByRole('button', { name: 'Actions for Bob Ray' }))
    await user.click(await screen.findByRole('menuitem', { name: 'Make admin' }))

    await waitFor(() =>
      expect(http.patch).toHaveBeenCalledWith(`/workspaces/${acme.id}/members/${plainMember.id}`, {
        role: 'admin',
      }),
    )
  })

  it('confirms before removing a member', async () => {
    const user = userEvent.setup()
    stubApi(http)
    http.delete.mockImplementation(() => ok(null))

    renderWithProviders(<MembersPage />, { withWorkspace: true })

    await screen.findByText('Bob Ray')
    await user.click(screen.getByRole('button', { name: 'Actions for Bob Ray' }))
    await user.click(await screen.findByRole('menuitem', { name: /Remove from workspace/ }))

    const confirm = await screen.findByRole('alertdialog')
    expect(http.delete).not.toHaveBeenCalled()

    await user.click(within(confirm).getByRole('button', { name: 'Remove member' }))

    await waitFor(() =>
      expect(http.delete).toHaveBeenCalledWith(`/workspaces/${acme.id}/members/${plainMember.id}`),
    )
  })

  it('will not offer to demote or remove the only admin', async () => {
    const user = userEvent.setup()
    stubApi(http)

    renderWithProviders(<MembersPage />, { withWorkspace: true })

    await screen.findByText('Bob Ray')
    await user.click(screen.getByRole('button', { name: 'Actions for Alice Chen' }))

    expect(await screen.findByRole('menuitem', { name: /keep as admin/ })).toHaveAttribute(
      'aria-disabled',
      'true',
    )
    expect(screen.getByRole('menuitem', { name: /cannot remove/ })).toHaveAttribute(
      'aria-disabled',
      'true',
    )
  })

  it('allows demoting an admin once a second one exists', async () => {
    const user = userEvent.setup()
    stubApi(http, { members: [adminMember, { ...plainMember, role: 'admin' as const }] })

    renderWithProviders(<MembersPage />, { withWorkspace: true })

    await screen.findByText('Bob Ray')
    await user.click(screen.getByRole('button', { name: 'Actions for Alice Chen' }))

    const demote = await screen.findByRole('menuitem', { name: 'Change to member' })
    expect(demote).not.toHaveAttribute('aria-disabled', 'true')
  })

  it('hides every management control from a plain member', async () => {
    stubApi(http, { workspaces: [beta] })

    renderWithProviders(<MembersPage />, { withWorkspace: true })

    await screen.findByText('Bob Ray')

    expect(screen.queryByRole('button', { name: /Invite/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Actions for Bob Ray' })).not.toBeInTheDocument()
    expect(screen.queryByText('Pending invitations')).not.toBeInTheDocument()
  })

  it('invites someone', async () => {
    const user = userEvent.setup()
    stubApi(http)
    http.post.mockImplementation(() => ok({ data: pendingInvitation }))

    renderWithProviders(<MembersPage />, { withWorkspace: true })

    await user.click(await screen.findByRole('button', { name: /Invite/ }))

    const dialog = await screen.findByRole('dialog')
    await user.type(within(dialog).getByLabelText('Email'), 'carol@example.com')
    await user.click(within(dialog).getByRole('button', { name: 'Send invitation' }))

    await waitFor(() =>
      expect(http.post).toHaveBeenCalledWith(`/workspaces/${acme.id}/invitations`, {
        email: 'carol@example.com',
        role: 'user',
      }),
    )
  })

  it('explains a conflict when the address is already a member', async () => {
    const user = userEvent.setup()
    stubApi(http)
    http.post.mockImplementation(() =>
      fail(409, { message: 'That person is already a member of this workspace.' }),
    )

    renderWithProviders(<MembersPage />, { withWorkspace: true })

    await user.click(await screen.findByRole('button', { name: /Invite/ }))

    const dialog = await screen.findByRole('dialog')
    await user.type(within(dialog).getByLabelText('Email'), 'bob@example.com')
    await user.click(within(dialog).getByRole('button', { name: 'Send invitation' }))

    expect(
      await within(dialog).findByText('That person is already a member of this workspace.'),
    ).toBeInTheDocument()
  })

  it('shows pending invitations to an admin', async () => {
    stubApi(http)
    renderWithProviders(<MembersPage />, { withWorkspace: true })

    expect(await screen.findByText('carol@example.com')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Withdraw/ })).toBeInTheDocument()
  })
})
