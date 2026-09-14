import axios from 'axios'

/** Origin of the Laravel backend (no trailing slash). */
export const API_ORIGIN = import.meta.env.VITE_API_URL ?? 'http://localhost:8000'

/**
 * Shared Axios client for the versioned API.
 *
 * Sanctum authentication is cookie based, so every request sends credentials
 * and mirrors the XSRF-TOKEN cookie back as a header. Feature API modules
 * import this client; components never call it directly.
 */
export const api = axios.create({
  baseURL: `${API_ORIGIN}/api/v1`,
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json' },
})

let csrfRequest: Promise<unknown> | null = null

/**
 * Ask Sanctum to set the CSRF cookie, once per page load.
 *
 * Laravel rejects a mutating request that arrives without the token, so this
 * runs before the first one. Concurrent callers share a single in-flight
 * request rather than each triggering their own.
 */
export function ensureCsrfCookie(): Promise<unknown> {
  csrfRequest ??= axios
    .get(`${API_ORIGIN}/sanctum/csrf-cookie`, { withCredentials: true })
    .catch((error) => {
      // Let the next mutation try again rather than wedging the app.
      csrfRequest = null
      throw error
    })

  return csrfRequest
}

/** Forget the cached CSRF request, so the next mutation primes a fresh token. */
export function resetCsrfCookie(): void {
  csrfRequest = null
}

api.interceptors.request.use(async (config) => {
  const method = config.method?.toUpperCase() ?? 'GET'

  if (method !== 'GET' && method !== 'HEAD') {
    await ensureCsrfCookie()
  }

  return config
})
