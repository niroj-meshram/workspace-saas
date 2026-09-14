import { vi } from 'vitest'
import { AxiosError, AxiosHeaders } from 'axios'
import { api } from '@/lib/api'

/**
 * Stub the Axios client the feature modules use.
 *
 * Mocking at the HTTP boundary rather than at the hook keeps the real query
 * keys, error handling and cache invalidation in the test.
 */
export function mockApi() {
  const get = vi.spyOn(api, 'get')
  const post = vi.spyOn(api, 'post')
  const patch = vi.spyOn(api, 'patch')
  const del = vi.spyOn(api, 'delete')

  return { get, post, patch, delete: del }
}

export function ok<T>(data: T) {
  return Promise.resolve({ data } as never)
}

/** An Axios rejection shaped like a real Laravel error response. */
export function fail(
  status: number,
  body: { message?: string; errors?: Record<string, string[]> },
) {
  const error = new AxiosError('Request failed', String(status), undefined, null, {
    status,
    statusText: '',
    data: body,
    headers: new AxiosHeaders(),
    config: { headers: new AxiosHeaders() },
  })

  return Promise.reject(error)
}
