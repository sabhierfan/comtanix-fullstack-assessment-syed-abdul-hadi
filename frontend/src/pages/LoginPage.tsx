import { useState, type FormEvent } from 'react'
import { Navigate, useLocation } from 'react-router'
import { useAuth } from '../auth/AuthContext'
import { Button, inputClass } from '../components/ui'
import { ApiError } from '../lib/api'

export function LoginPage() {
  const { user, login, notice } = useAuth()
  const location = useLocation()
  const from = (location.state as { from?: string } | null)?.from ?? '/requests'

  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<ApiError | null>(null)
  const [submitting, setSubmitting] = useState(false)

  if (user) return <Navigate to={from} replace />

  async function onSubmit(event: FormEvent) {
    event.preventDefault()
    setSubmitting(true)
    setError(null)
    try {
      await login(email, password)
    } catch (err) {
      setError(err instanceof ApiError ? err : new ApiError(0, 'Something went wrong.'))
    } finally {
      setSubmitting(false)
    }
  }

  const emailError = error?.field('email')
  const passwordError = error?.field('password')
  const generalError = error && !emailError && !passwordError ? error.message : null

  return (
    <main className="flex min-h-screen items-center justify-center bg-slate-50 px-4">
      <div className="w-full max-w-sm">
        <h1 className="text-center text-2xl font-semibold text-slate-900">Quote Request Manager</h1>
        <p className="mt-1 text-center text-sm text-slate-500">Sign in to the staff dashboard</p>

        <form onSubmit={onSubmit} noValidate className="mt-8 space-y-5 rounded-lg bg-white p-6 shadow-sm ring-1 ring-slate-200">
          {notice && <p className="rounded-md bg-amber-50 p-3 text-sm text-amber-800">{notice}</p>}
          {generalError && (
            <p role="alert" className="rounded-md bg-red-50 p-3 text-sm text-red-700">
              {generalError}
            </p>
          )}

          <div>
            <label htmlFor="email" className="block text-sm font-medium text-slate-700">
              Email
            </label>
            <input
              id="email"
              type="email"
              autoComplete="username"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className={`${inputClass} mt-1`}
              aria-invalid={!!emailError}
              aria-describedby={emailError ? 'email-error' : undefined}
            />
            {emailError && (
              <p id="email-error" className="mt-1 text-sm text-red-600">
                {emailError}
              </p>
            )}
          </div>

          <div>
            <label htmlFor="password" className="block text-sm font-medium text-slate-700">
              Password
            </label>
            <input
              id="password"
              type="password"
              autoComplete="current-password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className={`${inputClass} mt-1`}
              aria-invalid={!!passwordError}
            />
            {passwordError && <p className="mt-1 text-sm text-red-600">{passwordError}</p>}
          </div>

          <Button type="submit" disabled={submitting} className="w-full">
            {submitting ? 'Signing in…' : 'Sign in'}
          </Button>
        </form>
      </div>
    </main>
  )
}
