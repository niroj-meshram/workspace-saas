import { QueryClient } from '@tanstack/react-query'
import { isApiStatus } from '@/lib/api-error'

export function createQueryClient() {
  return new QueryClient({
    defaultOptions: {
      queries: {
        staleTime: 30_000,
        refetchOnWindowFocus: false,
        retry: (failureCount, error) => {
          // Retrying an auth, permission or validation failure just delays the
          // error the user needs to see.
          const terminal = [401, 403, 404, 409, 422].some((status) => isApiStatus(error, status))

          return !terminal && failureCount < 2
        },
      },
      mutations: { retry: false },
    },
  })
}

export const queryClient = createQueryClient()
