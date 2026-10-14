import type { ReactNode } from 'react'
import { useAuth } from '../auth/AuthContext'

export function AppLayout({ children }: { children: ReactNode }) {
  const { user, logout } = useAuth()

  return (
    <div className="min-h-screen bg-slate-50">
      <header className="border-b border-slate-200 bg-white">
        <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
          <span className="font-semibold text-slate-900">Quote Request Manager</span>
          {user && (
            <div className="flex items-center gap-3 text-sm">
              <span className="hidden text-slate-600 sm:inline">
                {user.name} <span className="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs font-medium uppercase text-slate-600">{user.role}</span>
              </span>
              <button type="button" onClick={() => void logout()} className="font-medium text-indigo-600 hover:text-indigo-500">
                Sign out
              </button>
            </div>
          )}
        </div>
      </header>
      <main className="mx-auto max-w-7xl px-4 py-6 sm:px-6">{children}</main>
    </div>
  )
}
