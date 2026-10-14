import { formatDateTime, STATUS_LABELS } from '../lib/labels'
import { useAuditEvents, useUsers } from '../lib/queries'
import type { AuditEvent, QuoteStatus } from '../types'
import { EmptyState, ErrorState, Spinner } from './ui'

export function AuditTimeline({ quoteRequestId }: { quoteRequestId: number }) {
  const events = useAuditEvents(quoteRequestId)
  const users = useUsers()
  const userName = (id: unknown) => users.data?.find((u) => u.id === id)?.name ?? (id ? `User #${String(id)}` : 'nobody')

  function describe(event: AuditEvent): string {
    const d = event.data
    switch (event.type) {
      case 'quote_request.created':
        return 'Request submitted through the public form'
      case 'quote_request.status_changed':
        return `Status changed from ${STATUS_LABELS[d.from as QuoteStatus] ?? d.from} to ${STATUS_LABELS[d.to as QuoteStatus] ?? d.to}`
      case 'quote_request.assigned':
        return d.to ? `Assigned to ${userName(d.to)}` : 'Unassigned'
      case 'quote_request.deleted':
        return 'Request deleted'
      default:
        return event.type
    }
  }

  return (
    <section>
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-semibold text-slate-900">Audit trail</h3>
        <button
          type="button"
          onClick={() => void events.refetch()}
          disabled={events.isFetching}
          className="text-xs font-medium text-indigo-600 hover:text-indigo-500 disabled:opacity-50"
        >
          {events.isFetching ? 'Refreshing…' : 'Refresh'}
        </button>
      </div>

      <div className="mt-3">
        {events.isPending ? (
          <Spinner label="Loading audit trail…" />
        ) : events.isError ? (
          <ErrorState message={events.error.message} onRetry={() => void events.refetch()} />
        ) : events.data.data.length === 0 ? (
          <EmptyState title="No audit events yet">Events appear here once the audit service has received them.</EmptyState>
        ) : (
          <ol className="relative space-y-4 border-l border-slate-200 pl-5">
            {events.data.data.map((event) => (
              <li key={event.id} className="relative">
                <span className="absolute -left-[25px] top-1.5 size-2.5 rounded-full bg-indigo-500 ring-4 ring-white" />
                <p className="text-sm text-slate-800">{describe(event)}</p>
                <p className="text-xs text-slate-500">
                  {event.actor ? event.actor.name : 'Public submission'} · {formatDateTime(event.occurred_at)}
                </p>
              </li>
            ))}
          </ol>
        )}
      </div>
    </section>
  )
}
