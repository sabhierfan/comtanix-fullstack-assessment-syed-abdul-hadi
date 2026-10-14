import type { ReactNode } from 'react'
import { Navigate, useLocation } from 'react-router'
import { useAuth } from './AuthContext'

/**
 * A guard around pages that need login.
 * Logged in -> show the page. Not logged in -> go to /login.
 * (This is only for the screen. The real protection is on the server: every API call checks the token.)
 */
export function RequireAuth({ children }: { children: ReactNode }) {
  const { user } = useAuth()
  const location = useLocation()

  if (!user) {
    // Remember where the user was going (including filters) so login can send them back.
    return <Navigate to="/login" replace state={{ from: location.pathname + location.search }} />
  }

  return children
}
