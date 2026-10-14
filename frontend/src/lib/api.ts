/**
 * The ONE place where the dashboard talks to the Laravel API.
 * Every screen calls api('/some-path') instead of using fetch() directly, so
 * adding the token, reading errors and handling 401 happen the same way everywhere.
 */

// Laravel's address, from frontend/.env (VITE_API_URL).
const API_URL = (import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api').replace(/\/$/, '')

/** Error thrown for every failed request; `errors` holds Laravel's field-level validation messages. */
export class ApiError extends Error {
  readonly status: number
  readonly errors: Record<string, string[]>
  /** The server's X-Request-Id, to find the matching log lines. */
  readonly requestId: string | null

  constructor(status: number, message: string, errors: Record<string, string[]> = {}, requestId: string | null = null) {
    super(message)
    this.status = status
    this.errors = errors
    this.requestId = requestId
  }

  /** First message for a field, if the API rejected it. */
  field(name: string): string | undefined {
    return this.errors[name]?.[0]
  }
}

// The current login token (set by AuthContext after login / page load).
let authToken: string | null = null
// What to do when the API says 401 (AuthContext sets this to "sign out").
let onUnauthorized: (() => void) | null = null

export function setAuthToken(token: string | null): void {
  authToken = token
}

/** Called when the API answers 401 to an authenticated request (expired or revoked token). */
export function setUnauthorizedHandler(handler: (() => void) | null): void {
  onUnauthorized = handler
}

type Query = Record<string, string | number | undefined | null>

/**
 * Send a request to the API and return the JSON answer.
 * On any error it throws an ApiError, so screens can show error.message
 * or a field error like error.field('status').
 */
export async function api<T>(path: string, options: { method?: string; body?: unknown; query?: Query } = {}): Promise<T> {
  const url = new URL(API_URL + path)
  for (const [key, value] of Object.entries(options.query ?? {})) {
    if (value !== undefined && value !== null && value !== '') url.searchParams.set(key, String(value))
  }

  const headers: Record<string, string> = { Accept: 'application/json' }
  if (options.body !== undefined) headers['Content-Type'] = 'application/json'
  if (authToken) headers.Authorization = `Bearer ${authToken}`

  let response: Response
  try {
    response = await fetch(url, {
      method: options.method ?? 'GET',
      headers,
      body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
    })
  } catch {
    // fetch() only throws when there is no answer at all (server down, no internet).
    throw new ApiError(0, 'Cannot reach the server. Check your connection and try again.')
  }

  // 204 = success with no body (e.g. delete, logout).
  if (response.status === 204) return undefined as T

  const payload = await response.json().catch(() => ({}))

  if (!response.ok) {
    // 401 while logged in = our token expired or was revoked -> sign out.
    if (response.status === 401 && authToken) onUnauthorized?.()

    const requestId = response.headers.get('X-Request-Id')
    let message =
      response.status === 429
        ? 'Too many requests. Please wait a moment and try again.'
        : (payload.message as string | undefined) ?? `Request failed (${response.status}).`

    // For server errors, show the request id so support can find the log lines.
    if (response.status >= 500 && requestId) message += ` (Reference: ${requestId.slice(0, 8)})`

    throw new ApiError(response.status, message, payload.errors ?? {}, requestId)
  }

  return payload as T
}
