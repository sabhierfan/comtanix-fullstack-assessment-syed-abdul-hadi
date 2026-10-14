import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { AuthProvider } from '../auth/AuthContext'
import { RequireAuth } from '../auth/RequireAuth'
import { setAuthToken } from '../lib/api'
import { LoginPage } from './LoginPage'

/** Render the app's routes starting at a given URL, like the real app does. */
function renderAt(url: string) {
  return render(
    <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
      <MemoryRouter initialEntries={[url]}>
        <AuthProvider>
          <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route
              path="/requests"
              element={
                <RequireAuth>
                  <p>Requests list</p>
                </RequireAuth>
              }
            />
          </Routes>
        </AuthProvider>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

function jsonResponse(status: number, body: unknown) {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

afterEach(() => {
  setAuthToken(null)
  vi.unstubAllGlobals()
})

describe('Login flow', () => {
  it('sends a logged-out visitor to the login page', () => {
    renderAt('/requests?status=new')

    expect(screen.getByRole('heading', { name: 'Quote Request Manager' })).toBeInTheDocument()
    expect(screen.queryByText('Requests list')).not.toBeInTheDocument()
  })

  it('shows the API validation message under the email field', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(422, { message: 'Invalid', errors: { email: ['The provided credentials are incorrect.'] } })))
    renderAt('/login')

    await userEvent.type(screen.getByLabelText('Email'), 'staff@comtanix.test')
    await userEvent.type(screen.getByLabelText('Password'), 'wrong')
    await userEvent.click(screen.getByRole('button', { name: 'Sign in' }))

    expect(await screen.findByText('The provided credentials are incorrect.')).toBeInTheDocument()
    expect(screen.getByLabelText('Email')).toHaveAttribute('aria-invalid', 'true')
  })

  it('logs in and returns to the page the user wanted, saving the session', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockImplementation(async (url: URL) =>
        String(url).endsWith('/login')
          ? jsonResponse(200, {
              token: 't-123',
              token_type: 'Bearer',
              expires_at: new Date(Date.now() + 3_600_000).toISOString(),
              user: { id: 2, name: 'Sara Staff', email: 'staff1@comtanix.test', role: 'staff' },
            })
          : jsonResponse(200, { id: 2, name: 'Sara Staff', email: 'staff1@comtanix.test', role: 'staff' }),
      ),
    )
    renderAt('/requests?status=new')

    await userEvent.type(screen.getByLabelText('Email'), 'staff1@comtanix.test')
    await userEvent.type(screen.getByLabelText('Password'), 'password')
    await userEvent.click(screen.getByRole('button', { name: 'Sign in' }))

    expect(await screen.findByText('Requests list')).toBeInTheDocument()
    expect(JSON.parse(sessionStorage.getItem('qrm.session')!).token).toBe('t-123')
  })
})
