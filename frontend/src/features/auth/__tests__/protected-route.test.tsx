import { describe, expect, it, beforeEach } from 'vitest'
import { screen } from '@testing-library/react'
import { Route, Routes } from 'react-router'
import { GuestRoute, ProtectedRoute } from '@/components/protected-route'
import { renderWithProviders } from '@/test/utils'
import { fail, mockApi, ok } from '@/test/mock-api'
import { alice } from '@/test/fixtures'

function Guarded() {
  return <p>Private area</p>
}

function SignIn() {
  return <p>Sign in page</p>
}

function renderRoutes(route: string) {
  return renderWithProviders(
    <Routes>
      <Route path="/login" element={<SignIn />} />
      <Route element={<ProtectedRoute />}>
        <Route path="/dashboard" element={<Guarded />} />
      </Route>
    </Routes>,
    { route },
  )
}

describe('ProtectedRoute', () => {
  let http: ReturnType<typeof mockApi>

  beforeEach(() => {
    http = mockApi()
  })

  it('sends a signed-out visitor to sign in', async () => {
    http.get.mockImplementation(() => fail(401, { message: 'Unauthenticated.' }))

    renderRoutes('/dashboard')

    expect(await screen.findByText('Sign in page')).toBeInTheDocument()
    expect(screen.queryByText('Private area')).not.toBeInTheDocument()
  })

  it('lets a signed-in user through', async () => {
    http.get.mockImplementation(() => ok({ data: alice }))

    renderRoutes('/dashboard')

    expect(await screen.findByText('Private area')).toBeInTheDocument()
  })

  it('waits rather than redirecting while the session is still unknown', () => {
    // A request that never settles stands in for the first load.
    http.get.mockImplementation(() => new Promise(() => {}) as never)

    renderRoutes('/dashboard')

    expect(screen.getByRole('status')).toHaveTextContent('Loading your workspaces')
    expect(screen.queryByText('Sign in page')).not.toBeInTheDocument()
    expect(screen.queryByText('Private area')).not.toBeInTheDocument()
  })
})

describe('GuestRoute', () => {
  function renderGuestRoutes(route: string, state?: unknown) {
    return renderWithProviders(
      <Routes>
        <Route element={<GuestRoute />}>
          <Route path="/login" element={<p>Sign in page</p>} />
          <Route path="/register" element={<p>Sign up page</p>} />
        </Route>
        <Route path="/dashboard" element={<p>Dashboard</p>} />
        <Route path="/invitations/:token" element={<p>Invitation page</p>} />
      </Routes>,
      { route, state },
    )
  }

  let http: ReturnType<typeof mockApi>

  beforeEach(() => {
    http = mockApi()
  })

  it('shows the sign-in form to a signed-out visitor', async () => {
    http.get.mockImplementation(() => fail(401, { message: 'Unauthenticated.' }))

    renderGuestRoutes('/login')

    expect(await screen.findByText('Sign in page')).toBeInTheDocument()
  })

  it('sends a signed-in user to the dashboard', async () => {
    http.get.mockImplementation(() => ok({ data: alice }))

    renderGuestRoutes('/login')

    expect(await screen.findByText('Dashboard')).toBeInTheDocument()
  })

  it('returns a signed-in user to where they were headed instead', async () => {
    // Someone who followed an invitation link, signed in, and must land back
    // on the invitation rather than on the dashboard. This gate re-renders the
    // instant authentication succeeds, so it has to agree with the page about
    // the destination or it wins the race and drops them somewhere else.
    http.get.mockImplementation(() => ok({ data: alice }))

    renderGuestRoutes('/register', { from: { pathname: '/invitations/abc123' } })

    expect(await screen.findByText('Invitation page')).toBeInTheDocument()
    expect(screen.queryByText('Dashboard')).not.toBeInTheDocument()
  })
})
