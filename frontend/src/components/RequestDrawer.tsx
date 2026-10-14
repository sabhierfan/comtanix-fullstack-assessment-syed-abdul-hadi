import { useEffect, useState } from 'react'
import { useNavigate, useParams, useSearchParams } from 'react-router'
import { ApiError } from '../lib/api'
import { BUDGET_LABELS, formatDateTime, SERVICE_LABELS, STATUS_LABELS } from '../lib/labels'
import { useAssign, useDeleteRequest, useQuoteRequest, useUpdateStatus, useUsers } from '../lib/queries'
import type { QuoteRequest } from '../types'
import { AuditTimeline } from './AuditTimeline'
import { Button, ErrorState, inputClass, Spinner, StatusBadge } from './ui'

export function RequestDrawer() {
  const { id } = useParams()
  const requestId = Number(id)
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const query = useQuoteRequest(requestId)

  const close = () => navigate({ pathname: '/requests', search: searchParams.toString() })

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && close()
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  })

  return (
    <div className="fixed inset-0 z-40" role="dialog" aria-modal="true" aria-labelledby="drawer-title">
      <div className="absolute inset-0 bg-slate-900/30" onClick={close} />
      <aside className="absolute inset-y-0 right-0 flex w-full max-w-xl flex-col bg-white shadow-xl">
        <div className="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
          <div>
            <h2 id="drawer-title" className="text-lg font-semibold text-slate-900">
              {query.data?.client_name ?? `Request #${requestId}`}
            </h2>
            {query.data && <p className="text-sm text-slate-500">Request #{query.data.id}</p>}
          </div>
          <button type="button" onClick={close} className="rounded-md p-1 text-slate-400 hover:text-slate-600" aria-label="Close">
            ✕
          </button>
        </div>

        <div className="flex-1 space-y-6 overflow-y-auto px-5 py-5">
          {query.isPending ? (
            <Spinner />
          ) : query.isError ? (
            <ErrorState
              message={query.error instanceof ApiError && query.error.status === 404 ? 'This request no longer exists.' : query.error.message}
              onRetry={() => void query.refetch()}
            />
          ) : (
            <Details request={query.data} onDeleted={close} />
          )}
        </div>
      </aside>
    </div>
  )
}

function Details({ request, onDeleted }: { request: QuoteRequest; onDeleted: () => void }) {
  return (
    <>
      <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
        <Field label="Status">
          <StatusBadge status={request.status} />
        </Field>
        <Field label="Assignee">{request.assignee?.name ?? <span className="text-slate-400">Unassigned</span>}</Field>
        <Field label="Email">
          <a href={`mailto:${request.email}`} className="text-indigo-600 hover:underline">
            {request.email}
          </a>
        </Field>
        <Field label="Company">{request.company ?? '—'}</Field>
        <Field label="Service">{SERVICE_LABELS[request.service_type]}</Field>
        <Field label="Budget">{BUDGET_LABELS[request.budget_range]}</Field>
        <Field label="Received">{formatDateTime(request.created_at)}</Field>
        <Field label="Updated">{formatDateTime(request.updated_at)}</Field>
      </dl>

      <div>
        <h3 className="text-sm font-semibold text-slate-900">Message</h3>
        <p className="mt-1 whitespace-pre-line rounded-md bg-slate-50 p-3 text-sm text-slate-700">{request.message}</p>
      </div>

      <StatusActions request={request} />
      {/* Keyed by assignee so the select resets after a successful save. */}
      {request.can.assign && <AssignControl key={request.assigned_to ?? 'none'} request={request} />}

      <AuditTimeline quoteRequestId={request.id} />

      {request.can.delete && <DeleteAction request={request} onDeleted={onDeleted} />}
    </>
  )
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <dt className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</dt>
      <dd className="mt-0.5 text-slate-900">{children}</dd>
    </div>
  )
}

function StatusActions({ request }: { request: QuoteRequest }) {
  const mutation = useUpdateStatus(request.id)
  const error = mutation.error instanceof ApiError ? mutation.error : null

  if (!request.can.update_status) {
    return (
      <p className="rounded-md bg-slate-50 p-3 text-sm text-slate-500">
        {request.assigned_to ? 'Only the assigned staff member or an admin can change this request.' : 'Only an admin can work on unassigned requests.'}
      </p>
    )
  }

  return (
    <div>
      <h3 className="text-sm font-semibold text-slate-900">Change status</h3>
      {request.allowed_transitions.length === 0 ? (
        <p className="mt-1 text-sm text-slate-500">This request is closed. No further changes are possible.</p>
      ) : (
        <div className="mt-2 flex flex-wrap gap-2">
          {request.allowed_transitions.map((status) => (
            <Button key={status} variant={status === 'closed' ? 'secondary' : 'primary'} disabled={mutation.isPending} onClick={() => mutation.mutate(status)}>
              {status === 'closed' ? 'Close request' : `Move to ${STATUS_LABELS[status]}`}
            </Button>
          ))}
        </div>
      )}
      {error && (
        <p role="alert" className="mt-2 text-sm text-red-600">
          {error.field('status') ?? error.message}
        </p>
      )}
    </div>
  )
}

function AssignControl({ request }: { request: QuoteRequest }) {
  const users = useUsers()
  const mutation = useAssign(request.id)
  const [selected, setSelected] = useState(request.assigned_to ? String(request.assigned_to) : '')
  const error = mutation.error instanceof ApiError ? mutation.error : null
  const unchanged = selected === (request.assigned_to ? String(request.assigned_to) : '')

  return (
    <div>
      <h3 className="text-sm font-semibold text-slate-900">Assign</h3>
      <div className="mt-2 flex gap-2">
        <select aria-label="Assign to" value={selected} onChange={(e) => setSelected(e.target.value)} className={inputClass} disabled={users.isPending}>
          <option value="">Unassigned</option>
          {users.data?.map((u) => (
            <option key={u.id} value={String(u.id)}>
              {u.name} ({u.role})
            </option>
          ))}
        </select>
        <Button disabled={unchanged || mutation.isPending} onClick={() => mutation.mutate(selected ? Number(selected) : null)}>
          {mutation.isPending ? 'Saving…' : 'Save'}
        </Button>
      </div>
      {error && (
        <p role="alert" className="mt-2 text-sm text-red-600">
          {error.field('assigned_to') ?? error.message}
        </p>
      )}
    </div>
  )
}

function DeleteAction({ request, onDeleted }: { request: QuoteRequest; onDeleted: () => void }) {
  const mutation = useDeleteRequest(request.id)

  return (
    <div className="border-t border-slate-200 pt-4">
      <Button
        variant="danger"
        disabled={mutation.isPending}
        onClick={() => {
          if (window.confirm(`Delete the request from ${request.client_name}? It is soft-deleted and kept in the audit trail.`)) {
            mutation.mutate(undefined, { onSuccess: onDeleted })
          }
        }}
      >
        {mutation.isPending ? 'Deleting…' : 'Delete request'}
      </Button>
      {mutation.error && <p className="mt-2 text-sm text-red-600">{mutation.error.message}</p>}
    </div>
  )
}
