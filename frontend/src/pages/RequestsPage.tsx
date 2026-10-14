import { useEffect, useState } from 'react'
import { Link, Outlet, useParams, useSearchParams } from 'react-router'
import { EmptyState, ErrorState, inputClass, Spinner, StatusBadge } from '../components/ui'
import { BUDGET_LABELS, formatDateTime, SERVICE_LABELS, SORT_OPTIONS, STATUS_LABELS } from '../lib/labels'
import { useQuoteRequests, useUsers, type ListParams } from '../lib/queries'
import { useDebouncedValue } from '../lib/useDebouncedValue'

/** Every filter lives in the URL, so refresh, back/forward and shared links keep the view. */
const FILTER_KEYS = ['search', 'status', 'assigned_to', 'service_type', 'sort'] as const

export function RequestsPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const { id: openId } = useParams()

  const params: ListParams = {
    search: searchParams.get('search') ?? undefined,
    status: searchParams.get('status') ?? undefined,
    assigned_to: searchParams.get('assigned_to') ?? undefined,
    service_type: searchParams.get('service_type') ?? undefined,
    sort: searchParams.get('sort') ?? undefined,
    page: Number(searchParams.get('page')) || 1,
    per_page: 15,
  }

  const list = useQuoteRequests(params)
  const users = useUsers()

  // Search box: type freely, update the URL (and query) 400ms after the last keystroke.
  const [searchInput, setSearchInput] = useState(params.search ?? '')
  const debouncedSearch = useDebouncedValue(searchInput.trim(), 400)
  useEffect(() => {
    if (debouncedSearch !== (searchParams.get('search') ?? '')) {
      updateParams({ search: debouncedSearch })
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [debouncedSearch])

  function updateParams(changes: Record<string, string | null>, resetPage = true) {
    setSearchParams(
      (current) => {
        const next = new URLSearchParams(current)
        for (const [key, value] of Object.entries(changes)) {
          if (value) next.set(key, value)
          else next.delete(key)
        }
        if (resetPage) next.delete('page')
        return next
      },
      { replace: true },
    )
  }

  const hasFilters = FILTER_KEYS.some((key) => key !== 'sort' && searchParams.get(key))
  const queryString = searchParams.toString() ? `?${searchParams.toString()}` : ''

  return (
    <>
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-xl font-semibold text-slate-900">Quote requests</h1>
          <p className="text-sm text-slate-500">{list.data ? `${list.data.meta.total} matching requests` : ' '}</p>
        </div>
      </div>

      {/* Filters */}
      <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <label className="lg:col-span-2">
          <span className="sr-only">Search</span>
          <input
            type="search"
            placeholder="Search client, email or company…"
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            className={inputClass}
          />
        </label>

        <select aria-label="Status" value={params.status ?? ''} onChange={(e) => updateParams({ status: e.target.value })} className={inputClass}>
          <option value="">All statuses</option>
          {Object.entries(STATUS_LABELS).map(([value, label]) => (
            <option key={value} value={value}>
              {label}
            </option>
          ))}
        </select>

        <select
          aria-label="Assignee"
          value={params.assigned_to ?? ''}
          onChange={(e) => updateParams({ assigned_to: e.target.value })}
          className={inputClass}
        >
          <option value="">All assignees</option>
          <option value="unassigned">Unassigned</option>
          {users.data?.map((u) => (
            <option key={u.id} value={String(u.id)}>
              {u.name}
            </option>
          ))}
        </select>

        <select
          aria-label="Service type"
          value={params.service_type ?? ''}
          onChange={(e) => updateParams({ service_type: e.target.value })}
          className={inputClass}
        >
          <option value="">All services</option>
          {Object.entries(SERVICE_LABELS).map(([value, label]) => (
            <option key={value} value={value}>
              {label}
            </option>
          ))}
        </select>
      </div>

      <div className="mt-3 flex flex-wrap items-center justify-between gap-3 text-sm">
        <label className="flex items-center gap-2 text-slate-600">
          Sort
          <select value={params.sort ?? '-created_at'} onChange={(e) => updateParams({ sort: e.target.value })} className={`${inputClass} w-auto py-1.5`}>
            {SORT_OPTIONS.map((o) => (
              <option key={o.value} value={o.value}>
                {o.label}
              </option>
            ))}
          </select>
        </label>
        {hasFilters && (
          <button
            type="button"
            className="font-medium text-indigo-600 hover:text-indigo-500"
            onClick={() => {
              setSearchInput('')
              updateParams({ search: null, status: null, assigned_to: null, service_type: null })
            }}
          >
            Clear filters
          </button>
        )}
      </div>

      {/* Results */}
      <div className="mt-4">
        {list.isPending ? (
          <Spinner label="Loading requests…" />
        ) : list.isError ? (
          <ErrorState message={list.error.message} onRetry={() => void list.refetch()} />
        ) : list.data.data.length === 0 ? (
          <EmptyState title={hasFilters ? 'No requests match these filters' : 'No quote requests yet'}>
            {hasFilters ? 'Try a different search or clear the filters.' : 'New submissions from the public form will appear here.'}
          </EmptyState>
        ) : (
          <div className={`overflow-x-auto rounded-lg bg-white shadow-sm ring-1 ring-slate-200 transition-opacity ${list.isPlaceholderData ? 'opacity-60' : ''}`}>
            <table className="min-w-full divide-y divide-slate-200 text-sm">
              <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                  <th className="px-4 py-3">Client</th>
                  <th className="px-4 py-3">Service</th>
                  <th className="hidden px-4 py-3 md:table-cell">Budget</th>
                  <th className="px-4 py-3">Status</th>
                  <th className="hidden px-4 py-3 sm:table-cell">Assignee</th>
                  <th className="hidden px-4 py-3 lg:table-cell">Received</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {list.data.data.map((q) => (
                  <tr key={q.id} className={`hover:bg-slate-50 ${String(q.id) === openId ? 'bg-indigo-50/60' : ''}`}>
                    <td className="px-4 py-3">
                      <Link to={`/requests/${q.id}${queryString}`} className="font-medium text-slate-900 hover:text-indigo-600">
                        {q.client_name}
                      </Link>
                      <div className="text-xs text-slate-500">{q.company ?? q.email}</div>
                    </td>
                    <td className="px-4 py-3 text-slate-700">{SERVICE_LABELS[q.service_type]}</td>
                    <td className="hidden px-4 py-3 text-slate-700 md:table-cell">{BUDGET_LABELS[q.budget_range]}</td>
                    <td className="px-4 py-3">
                      <StatusBadge status={q.status} />
                    </td>
                    <td className="hidden px-4 py-3 text-slate-700 sm:table-cell">{q.assignee?.name ?? <span className="text-slate-400">Unassigned</span>}</td>
                    <td className="hidden whitespace-nowrap px-4 py-3 text-slate-500 lg:table-cell">{formatDateTime(q.created_at)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {list.data && list.data.meta.last_page > 1 && (
          <nav aria-label="Pagination" className="mt-4 flex items-center justify-between text-sm text-slate-600">
            <span>
              {list.data.meta.from}–{list.data.meta.to} of {list.data.meta.total}
            </span>
            <div className="flex gap-2">
              <PageButton disabled={params.page! <= 1} onClick={() => updateParams({ page: String(params.page! - 1) }, false)}>
                Previous
              </PageButton>
              <span className="px-2 py-1.5">
                Page {list.data.meta.current_page} of {list.data.meta.last_page}
              </span>
              <PageButton
                disabled={params.page! >= list.data.meta.last_page}
                onClick={() => updateParams({ page: String(params.page! + 1) }, false)}
              >
                Next
              </PageButton>
            </div>
          </nav>
        )}
      </div>

      {/* Detail drawer (/requests/:id) */}
      <Outlet />
    </>
  )
}

function PageButton({ disabled, onClick, children }: { disabled: boolean; onClick: () => void; children: string }) {
  return (
    <button
      type="button"
      disabled={disabled}
      onClick={onClick}
      className="rounded-md bg-white px-3 py-1.5 font-medium ring-1 ring-inset ring-slate-300 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
    >
      {children}
    </button>
  )
}
