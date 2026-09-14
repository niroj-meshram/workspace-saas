import { beforeEach, describe, expect, it } from 'vitest'
import { screen } from '@testing-library/react'
import { Route, Routes } from 'react-router'
import { AcceptInvitationPage } from '@/pages/AcceptInvitationPage'
import { renderWithProviders } from '@/test/utils'
import { fail, mockApi, ok } from '@/test/mock-api'
import { acme, alice } from '@/test/fixtures'

const TOKEN = 'a'.repeat(64)

function renderPage() {
  return renderWithProviders(
    <Routes>
      <Route path="/login" element={<p>Sign in page</p>} />
      <Route path="/dashboard" element={<p>Dashboard</p>} />
      <Route path="/invitations/:token" element={<AcceptInvitationPage />} />
    </Routes>,
    { route: `/invitations/${TOKEN}` },
  )
}

describe('AcceptInvitationPage', () => {
  let http: ReturnType<typeof mockApi>

  beforeEach(() => {
    http = mockApi()
  })

  it('sends a signed-out visitor to sign in without losing the token', async () => {
    http.get.mockImplementation(() => fail(401, { message: 'Unauthenticated.' }))

    renderPage()

    expect(await screen.findByText('Sign in page')).toBeInTheDocument()
    // The invitation is never accepted on behalf of a guest.
    expect(http.post).not.toHaveBeenCalled()
    expect(localStorage.getItem('workspace-saas:selected-workspace')).toBeNull()
  })

  it('accepts for a signed-in user and opens the workspace they joined', async () => {
    http.get.mockImplementation(() => ok({ data: alice }))
    http.post.mockImplementation(() => ok({ data: acme }))

    renderPage()

    expect(await screen.findByText('Dashboard')).toBeInTheDocument()
    expect(http.post).toHaveBeenCalledWith(`/invitations/${TOKEN}/accept`)
    expect(localStorage.getItem('workspace-saas:selected-workspace')).toBe(acme.id)
  })

  it('explains an expired invitation', async () => {
    http.get.mockImplementation(() => ok({ data: alice }))
    http.post.mockImplementation(() => fail(409, { message: 'This invitation has expired.' }))

    renderPage()

    expect(await screen.findByText('This invitation has expired')).toBeInTheDocument()
    expect(screen.getByText(/Invitations last seven days/)).toBeInTheDocument()
  })

  it('explains an invitation that was already used', async () => {
    http.get.mockImplementation(() => ok({ data: alice }))
    http.post.mockImplementation(() =>
      fail(409, { message: 'This invitation has already been accepted.' }),
    )

    renderPage()

    expect(await screen.findByText('This invitation has already been used')).toBeInTheDocument()
  })

  it('explains an invitation addressed to a different account', async () => {
    http.get.mockImplementation(() => ok({ data: alice }))
    http.post.mockImplementation(() => fail(403, { message: 'This action is unauthorized.' }))

    renderPage()

    expect(await screen.findByText('This invitation is for someone else')).toBeInTheDocument()
    expect(screen.getByText(/alice@example.com/)).toBeInTheDocument()
  })

  it('explains an unknown or withdrawn invitation', async () => {
    http.get.mockImplementation(() => ok({ data: alice }))
    http.post.mockImplementation(() => fail(404, { message: 'Resource not found.' }))

    renderPage()

    expect(await screen.findByText("We couldn't find that invitation")).toBeInTheDocument()
  })
})
