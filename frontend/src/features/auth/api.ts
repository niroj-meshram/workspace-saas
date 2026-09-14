import { api, resetCsrfCookie } from '@/lib/api'
import type { Envelope, User } from '@/types/api'

export interface LoginPayload {
  email: string
  password: string
}

export interface RegisterPayload {
  name: string
  email: string
  password: string
  password_confirmation: string
}

export async function fetchCurrentUser(): Promise<User> {
  const { data } = await api.get<Envelope<User>>('/auth/me')

  return data.data
}

export async function login(payload: LoginPayload): Promise<User> {
  const { data } = await api.post<Envelope<User>>('/auth/login', payload)

  return data.data
}

export async function register(payload: RegisterPayload): Promise<User> {
  const { data } = await api.post<Envelope<User>>('/auth/register', payload)

  return data.data
}

export async function logout(): Promise<void> {
  await api.post('/auth/logout')
  // The session is gone, so the CSRF token that belonged to it is too.
  resetCsrfCookie()
}
