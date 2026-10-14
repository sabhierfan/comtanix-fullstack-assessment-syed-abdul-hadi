import { Component, type ErrorInfo, type ReactNode } from 'react'

/**
 * Catches crashes while drawing the screen (a bug in a component).
 * Without this, one error would leave the user with a blank white page.
 * Instead we show a short message and a "Reload" button.
 * (API errors are not crashes; screens already handle those themselves.)
 */
export class ErrorBoundary extends Component<{ children: ReactNode }, { failed: boolean }> {
  state = { failed: false }

  static getDerivedStateFromError() {
    return { failed: true }
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    // In production this is where you would send the error to Sentry or similar.
    console.error('Dashboard crashed', error, info.componentStack)
  }

  render() {
    if (!this.state.failed) return this.props.children

    return (
      <main className="flex min-h-screen items-center justify-center bg-slate-50 px-4">
        <div role="alert" className="max-w-sm rounded-lg bg-white p-6 text-center shadow-sm ring-1 ring-slate-200">
          <h1 className="text-lg font-semibold text-slate-900">Something went wrong</h1>
          <p className="mt-2 text-sm text-slate-600">The page hit an unexpected error. Reloading usually fixes it.</p>
          <button
            type="button"
            onClick={() => window.location.reload()}
            className="mt-4 rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
          >
            Reload
          </button>
        </div>
      </main>
    )
  }
}
