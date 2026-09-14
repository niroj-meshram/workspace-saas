import { createContext, use, useCallback, type ReactNode } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  fetchCurrentUser,
  login as loginRequest,
  logout as logoutRequest,
  register as registerRequest,
  type LoginPayload,
  type RegisterPayload,
} from '@/features/auth/api'
import { isApiStatus } from '@/lib/api-error'
import type { User } from '@/types/api'

interface AuthContextValue {
  user: User | null
  /** True only while the very first "who am I" request is in flight. */
  isLoading: boolean
  isAuthenticated: boolean
  login: (payload: LoginPayload) => Promise<User>
  register: (payload: RegisterPayload) => Promise<User>
  logout: () => Promise<void>
}

const AuthContext = createContext<AuthContextValue | null>(null)

export const currentUserKey = ['auth', 'me'] as const

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient()

  const { data, isLoading } = useQuery({
    queryKey: currentUserKey,
    queryFn: fetchCurrentUser,
    // A 401 here is the expected answer for a signed-out visitor, not a
    // failure worth retrying or surfacing.
    retry: (failureCount, error) => !isApiStatus(error, 401) && failureCount < 1,
    staleTime: Infinity,
  })

  const loginMutation = useMutation({ mutationFn: loginRequest })
  const registerMutation = useMutation({ mutationFn: registerRequest })
  const logoutMutation = useMutation({ mutationFn: logoutRequest })

  const login = useCallback(
    async (payload: LoginPayload) => {
      const user = await loginMutation.mutateAsync(payload)
      queryClient.setQueryData(currentUserKey, user)

      return user
    },
    [loginMutation, queryClient],
  )

  const register = useCallback(
    (payload: RegisterPayload) => registerMutation.mutateAsync(payload),
    [registerMutation],
  )

  const logout = useCallback(async () => {
    await logoutMutation.mutateAsync()
    // Drop every cached tenant response, not just the user.
    queryClient.clear()
    queryClient.setQueryData(currentUserKey, null)
  }, [logoutMutation, queryClient])

  return (
    <AuthContext
      value={{
        user: data ?? null,
        isLoading,
        isAuthenticated: Boolean(data),
        login,
        register,
        logout,
      }}
    >
      {children}
    </AuthContext>
  )
}

export function useAuth(): AuthContextValue {
  const context = use(AuthContext)

  if (!context) {
    throw new Error('useAuth must be used inside an AuthProvider.')
  }

  return context
}
