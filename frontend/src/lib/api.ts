const API_URL = (import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api').replace(/\/$/, '')

/** Error thrown for every failed request; `errors` holds Laravel's field-level validation messages. */
export class ApiError extends Error {
  readonly status: number
  readonly errors: Record<string, string[]>

  constructor(status: number, message: string, errors: Record<string, string[]> = {}) {
    super(message)
    this.status = status
    this.errors = errors
  }

  /** First message for a field, if the API rejected it. */
  field(name: string): string | undefined {
    return this.errors[name]?.[0]
  }
}

let authToken: string | null = null
let onUnauthorized: (() => void) | null = null

export function setAuthToken(token: string | null): void {
  authToken = token
}

/** Called when the API answers 401 to an authenticated request (expired or revoked token). */
export function setUnauthorizedHandler(handler: (() => void) | null): void {
  onUnauthorized = handler
}

type Query = Record<string, string | number | undefined | null>

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
    throw new ApiError(0, 'Cannot reach the server. Check your connection and try again.')
  }

  if (response.status === 204) return undefined as T

  const payload = await response.json().catch(() => ({}))

  if (!response.ok) {
    if (response.status === 401 && authToken) onUnauthorized?.()

    const message =
      response.status === 429
        ? 'Too many requests. Please wait a moment and try again.'
        : (payload.message as string | undefined) ?? `Request failed (${response.status}).`
    throw new ApiError(response.status, message, payload.errors ?? {})
  }

  return payload as T
}
