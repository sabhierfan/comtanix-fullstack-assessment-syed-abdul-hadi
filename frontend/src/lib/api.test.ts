import { afterEach, describe, expect, it, vi } from 'vitest'
import { api, ApiError, setAuthToken, setUnauthorizedHandler } from './api'

/** Make fetch() return this response once. */
function mockFetch(status: number, body: unknown, headers: Record<string, string> = {}) {
  const fetchMock = vi.fn().mockResolvedValue(
    new Response(status === 204 ? null : JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json', ...headers } }),
  )
  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

afterEach(() => {
  setAuthToken(null)
  setUnauthorizedHandler(null)
  vi.unstubAllGlobals()
})

describe('api()', () => {
  it('sends the bearer token and query parameters, and skips empty filters', async () => {
    const fetchMock = mockFetch(200, { ok: true })
    setAuthToken('secret-token')

    await api('/quote-requests', { query: { status: 'new', search: '', page: 2 } })

    const [url, init] = fetchMock.mock.calls[0]
    expect(String(url)).toMatch(/\/quote-requests\?status=new&page=2$/)
    expect(init.headers.Authorization).toBe('Bearer secret-token')
  })

  it('turns Laravel validation errors into field messages', async () => {
    mockFetch(422, { message: 'Invalid.', errors: { status: ['Cannot change status from "new" to "quoted".'] } })

    const error = await api('/x').catch((e: unknown) => e)

    expect(error).toBeInstanceOf(ApiError)
    expect((error as ApiError).status).toBe(422)
    expect((error as ApiError).field('status')).toBe('Cannot change status from "new" to "quoted".')
  })

  it('signs the user out when a logged-in request gets 401', async () => {
    mockFetch(401, { message: 'Unauthenticated.' })
    const onUnauthorized = vi.fn()
    setAuthToken('expired-token')
    setUnauthorizedHandler(onUnauthorized)

    await expect(api('/me')).rejects.toBeInstanceOf(ApiError)
    expect(onUnauthorized).toHaveBeenCalledOnce()
  })

  it('adds a short request reference to server errors', async () => {
    mockFetch(503, { message: 'The audit trail is temporarily unavailable.' }, { 'X-Request-Id': 'abcdef12-3456-7890' })

    const error = (await api('/x').catch((e: unknown) => e)) as ApiError

    expect(error.message).toBe('The audit trail is temporarily unavailable. (Reference: abcdef12)')
    expect(error.requestId).toBe('abcdef12-3456-7890')
  })

  it('reports a friendly message when the server cannot be reached', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')))

    const error = (await api('/x').catch((e: unknown) => e)) as ApiError

    expect(error.status).toBe(0)
    expect(error.message).toMatch(/Cannot reach the server/)
  })
})
