import { useQueryClient } from '@tanstack/react-query'
import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react'
import { api, setAuthToken, setUnauthorizedHandler } from '../lib/api'
import type { LoginResponse, User } from '../types'

/**
 * Keeps track of who is logged in, for the whole app.
 * Any component can call useAuth() to get { user, login, logout }.
 *
 * Where is the token kept? In sessionStorage:
 *   - it survives a page refresh,
 *   - it is deleted when the tab is closed,
 *   - it is not shared with other tabs.
 * It is still readable by JavaScript, so XSS is the main risk (see README).
 * The token also expires on the server after 8 hours.
 */
const STORAGE_KEY = 'qrm.session'

interface Session {
  token: string
  expiresAt: string | null
  user: User
}

interface AuthContextValue {
  user: User | null
  /** Why the user was signed out, shown on the login page. */
  notice: string | null
  login: (email: string, password: string) => Promise<void>
  logout: () => Promise<void>
}

const AuthContext = createContext<AuthContextValue | null>(null)

function isExpired(session: Session): boolean {
  return session.expiresAt !== null && new Date(session.expiresAt).getTime() <= Date.now()
}

function readSession(): Session | null {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY)
    if (!raw) return null
    const session = JSON.parse(raw) as Session
    return isExpired(session) ? null : session
  } catch {
    return null
  }
}

function writeSession(session: Session | null): void {
  try {
    if (session) sessionStorage.setItem(STORAGE_KEY, JSON.stringify(session))
    else sessionStorage.removeItem(STORAGE_KEY)
  } catch {
    // Storage unavailable (private mode): the session simply won't survive a refresh.
  }
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient()
  const [session, setSession] = useState<Session | null>(() => {
    const restored = readSession()
    setAuthToken(restored?.token ?? null)
    return restored
  })
  const [notice, setNotice] = useState<string | null>(null)

  // Sign out on this browser: forget the token, clear saved data, remember why (for the login page).
  const endSession = useCallback(
    (reason: string | null) => {
      setAuthToken(null)
      writeSession(null)
      setSession(null)
      setNotice(reason)
      // Throw away all cached API data, so the next user never sees the previous user's data.
      queryClient.clear()
    },
    [queryClient],
  )

  // Any 401 from the API means the token expired or was revoked.
  useEffect(() => {
    setUnauthorizedHandler(() => endSession('Your session has expired. Please sign in again.'))
    return () => setUnauthorizedHandler(null)
  }, [endSession])

  // Sign out on the client exactly when the token expires.
  useEffect(() => {
    if (!session?.expiresAt) return
    const ms = new Date(session.expiresAt).getTime() - Date.now()
    const timer = setTimeout(() => endSession('Your session has expired. Please sign in again.'), Math.max(ms, 0))
    return () => clearTimeout(timer)
  }, [session, endSession])

  // Re-validate a restored token and refresh the user's role.
  useEffect(() => {
    if (!session) return
    api<User>('/me')
      .then((user) => {
        setSession((current) => {
          if (!current) return current
          const updated = { ...current, user }
          writeSession(updated)
          return updated
        })
      })
      .catch(() => {
        /* 401s are handled globally; other errors keep the cached user */
      })
    // Only on first mount.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  // Ask Laravel for a token, then save it (memory + sessionStorage).
  const login = useCallback(async (email: string, password: string) => {
    const response = await api<LoginResponse>('/login', { method: 'POST', body: { email, password } })
    const next: Session = { token: response.token, expiresAt: response.expires_at, user: response.user }
    setAuthToken(next.token)
    writeSession(next)
    setNotice(null)
    setSession(next)
  }, [])

  // Tell Laravel to delete the token, then forget it here too.
  const logout = useCallback(async () => {
    try {
      await api('/logout', { method: 'POST' })
    } catch {
      // Even if revoking fails (e.g. offline), drop the token locally.
    }
    endSession(null)
  }, [endSession])

  const value = useMemo(() => ({ user: session?.user ?? null, notice, login, logout }), [session, notice, login, logout])

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

// eslint-disable-next-line react-refresh/only-export-components
export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext)
  if (!context) throw new Error('useAuth must be used inside <AuthProvider>')
  return context
}
