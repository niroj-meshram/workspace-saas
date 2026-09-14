import { beforeEach, describe, expect, it } from 'vitest'
import { screen } from '@testing-library/react'
import { LoginPage } from '@/pages/LoginPage'
import { RegisterPage } from '@/pages/RegisterPage'
import { renderWithProviders } from '@/test/utils'
import { fail, mockApi } from '@/test/mock-api'

const FROM_INVITATION = { from: { pathname: '/invitations/' + 'a'.repeat(64) } }

describe('invitation notice', () => {
  beforeEach(() => {
    const http = mockApi()
    http.get.mockImplementation(() => fail(401, { message: 'Unauthenticated.' }))
  })

  it('explains why an invitee is looking at the sign-in form', async () => {
    renderWithProviders(<LoginPage />, { route: '/login', state: FROM_INVITATION })

    expect(await screen.findByText("You've been invited to a workspace.")).toBeInTheDocument()
    expect(screen.getByText(/email address the invitation was sent to/)).toBeInTheDocument()
  })

  it('tells an invitee signing up which address to use', async () => {
    renderWithProviders(<RegisterPage />, { route: '/register', state: FROM_INVITATION })

    expect(await screen.findByText("You've been invited to a workspace.")).toBeInTheDocument()
    expect(screen.getByText(/Sign up with the email address/)).toBeInTheDocument()
  })

  it('stays out of the way for an ordinary visitor', async () => {
    renderWithProviders(<LoginPage />, { route: '/login' })

    await screen.findByRole('button', { name: 'Sign in' })

    expect(screen.queryByText("You've been invited to a workspace.")).not.toBeInTheDocument()
  })

  it('stays out of the way when the destination is not an invitation', async () => {
    renderWithProviders(<LoginPage />, {
      route: '/login',
      state: { from: { pathname: '/tasks' } },
    })

    await screen.findByRole('button', { name: 'Sign in' })

    expect(screen.queryByText("You've been invited to a workspace.")).not.toBeInTheDocument()
  })
})
