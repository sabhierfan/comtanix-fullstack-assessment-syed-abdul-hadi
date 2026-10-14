import { formatDateTime, STATUS_LABELS } from '../lib/labels'
import { useAuditEvents, useUsers } from '../lib/queries'
import type { AuditEvent, QuoteStatus } from '../types'
import { Button, EmptyState, ErrorState, Spinner } from './ui'

/**
 * Turn one audit event into a sentence a person can read.
 * Kept outside the component (a "pure function") so it is easy to unit test.
 */
// eslint-disable-next-line react-refresh/only-export-components
export function describeEvent(event: AuditEvent, userName: (id: unknown) => string): string {
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

/**
 * The history list for one request. Loaded from Laravel's proxy endpoint
 * (/api/quote-requests/:id/audit-events), which reads from the Node audit service.
 * Events are delivered by a background worker, so a new event can take a few seconds to show.
 * Shows 20 events at a time; "Show older events" loads the next 20.
 */
export function AuditTimeline({ quoteRequestId }: { quoteRequestId: number }) {
  const events = useAuditEvents(quoteRequestId)
  const users = useUsers()
  const userName = (id: unknown) => users.data?.find((u) => u.id === id)?.name ?? (id ? `User #${String(id)}` : 'nobody')

  // All loaded pages joined into one list (newest first).
  const items = events.data?.pages.flatMap((page) => page.data) ?? []
  const total = events.data?.pages[0]?.meta.total ?? 0

  return (
    <section>
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-semibold text-slate-900">
          Audit trail {total > 0 && <span className="font-normal text-slate-500">({total})</span>}
        </h3>
        <button
          type="button"
          onClick={() => void events.refetch()}
          disabled={events.isFetching}
          className="text-xs font-medium text-indigo-600 hover:text-indigo-500 disabled:opacity-50"
        >
          {events.isFetching && !events.isFetchingNextPage ? 'Refreshing…' : 'Refresh'}
        </button>
      </div>

      <div className="mt-3">
        {events.isPending ? (
          <Spinner label="Loading audit trail…" />
        ) : events.isError && items.length === 0 ? (
          <ErrorState message={events.error.message} onRetry={() => void events.refetch()} />
        ) : items.length === 0 ? (
          <EmptyState title="No audit events yet">Events appear here once the audit service has received them.</EmptyState>
        ) : (
          <>
            <ol className="relative space-y-4 border-l border-slate-200 pl-5">
              {items.map((event) => (
                <li key={event.id} className="relative">
                  <span className="absolute -left-[25px] top-1.5 size-2.5 rounded-full bg-indigo-500 ring-4 ring-white" />
                  <p className="text-sm text-slate-800">{describeEvent(event, userName)}</p>
                  <p className="text-xs text-slate-500">
                    {event.actor ? event.actor.name : 'Public submission'} · {formatDateTime(event.occurred_at)}
                  </p>
                </li>
              ))}
            </ol>

            {events.isError && <p className="mt-3 text-sm text-red-600">{events.error.message}</p>}

            {events.hasNextPage && (
              <Button variant="secondary" className="mt-4 w-full" disabled={events.isFetchingNextPage} onClick={() => void events.fetchNextPage()}>
                {events.isFetchingNextPage ? 'Loading…' : `Show older events (${total - items.length} more)`}
              </Button>
            )}
          </>
        )}
      </div>
    </section>
  )
}
