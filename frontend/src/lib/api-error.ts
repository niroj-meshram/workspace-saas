import { AxiosError } from 'axios'

/**
 * A normalised view of a failed request, so components never have to reach
 * into Axios internals to find out what went wrong.
 */
export interface ApiError {
  status: number | null
  message: string
  /** Laravel's 422 payload: field name to the messages for that field. */
  errors: Record<string, string[]>
}

const FALLBACK_MESSAGE = 'Something went wrong. Try again.'

export function toApiError(error: unknown): ApiError {
  if (error instanceof AxiosError) {
    const data = error.response?.data as
      { message?: string; errors?: Record<string, string[]> } | undefined

    return {
      status: error.response?.status ?? null,
      message:
        data?.message ??
        (error.code === 'ERR_NETWORK'
          ? "Can't reach the server. Check your connection and try again."
          : FALLBACK_MESSAGE),
      errors: data?.errors ?? {},
    }
  }

  return { status: null, message: FALLBACK_MESSAGE, errors: {} }
}

export function isApiStatus(error: unknown, status: number): boolean {
  return toApiError(error).status === status
}
