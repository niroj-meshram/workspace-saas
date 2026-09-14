import { beforeEach, describe, expect, it } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { LoginPage } from '@/pages/LoginPage'
import { renderWithProviders } from '@/test/utils'
import { fail, mockApi, ok } from '@/test/mock-api'
import { alice } from '@/test/fixtures'

describe('LoginPage', () => {
  let http: ReturnType<typeof mockApi>

  beforeEach(() => {
    http = mockApi()
    // The signed-out visitor: /auth/me answers 401.
    http.get.mockImplementation(() => fail(401, { message: 'Unauthenticated.' }))
  })

  it('will not submit an empty form', async () => {
    const user = userEvent.setup()
    renderWithProviders(<LoginPage />)

    await user.click(screen.getByRole('button', { name: 'Sign in' }))

    expect(await screen.findByText('Enter your email address.')).toBeInTheDocument()
    expect(screen.getByText('Enter your password.')).toBeInTheDocument()
    expect(http.post).not.toHaveBeenCalled()
  })

  it('rejects a malformed email before calling the API', async () => {
    const user = userEvent.setup()
    renderWithProviders(<LoginPage />)

    await user.type(screen.getByLabelText('Email'), 'not-an-email')
    await user.type(screen.getByLabelText('Password'), 'correct-horse-battery')
    await user.click(screen.getByRole('button', { name: 'Sign in' }))

    expect(await screen.findByText('Enter a valid email address.')).toBeInTheDocument()
    expect(http.post).not.toHaveBeenCalled()
  })

  it('signs in with valid credentials', async () => {
    const user = userEvent.setup()
    http.post.mockImplementation(() => ok({ data: alice }))

    renderWithProviders(<LoginPage />)

    await user.type(screen.getByLabelText('Email'), 'alice@example.com')
    await user.type(screen.getByLabelText('Password'), 'correct-horse-battery')
    await user.click(screen.getByRole('button', { name: 'Sign in' }))

    await waitFor(() =>
      expect(http.post).toHaveBeenCalledWith('/auth/login', {
        email: 'alice@example.com',
        password: 'correct-horse-battery',
      }),
    )
  })

  it("shows Laravel's field error beside the input", async () => {
    const user = userEvent.setup()
    http.post.mockImplementation(() =>
      fail(422, {
        message: 'These credentials do not match our records.',
        errors: { email: ['These credentials do not match our records.'] },
      }),
    )

    renderWithProviders(<LoginPage />)

    await user.type(screen.getByLabelText('Email'), 'alice@example.com')
    await user.type(screen.getByLabelText('Password'), 'wrong-password')
    await user.click(screen.getByRole('button', { name: 'Sign in' }))

    expect(
      await screen.findByText('These credentials do not match our records.'),
    ).toBeInTheDocument()
  })

  it('reports a throttled sign-in above the form', async () => {
    const user = userEvent.setup()
    http.post.mockImplementation(() => fail(429, { message: 'Too Many Attempts.' }))

    renderWithProviders(<LoginPage />)

    await user.type(screen.getByLabelText('Email'), 'alice@example.com')
    await user.type(screen.getByLabelText('Password'), 'correct-horse-battery')
    await user.click(screen.getByRole('button', { name: 'Sign in' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Too Many Attempts.')
  })

  it('disables the button while the request is in flight', async () => {
    const user = userEvent.setup()
    let resolve: (value: unknown) => void = () => {}
    http.post.mockImplementation(() => new Promise((r) => (resolve = r)) as never)

    renderWithProviders(<LoginPage />)

    await user.type(screen.getByLabelText('Email'), 'alice@example.com')
    await user.type(screen.getByLabelText('Password'), 'correct-horse-battery')
    await user.click(screen.getByRole('button', { name: 'Sign in' }))

    expect(await screen.findByRole('button', { name: 'Signing in…' })).toBeDisabled()

    resolve({ data: { data: alice } })
    await waitFor(() => expect(http.post).toHaveBeenCalled())
  })
})
