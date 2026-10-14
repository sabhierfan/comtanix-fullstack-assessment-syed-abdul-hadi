import { keepPreviousData, useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { AuditEventPage, Paginated, QuoteRequest, QuoteStatus, User } from '../types'
import { api } from './api'

/**
 * All data loading and saving for the dashboard, using TanStack Query (React Query).
 *
 * useQuery    = load data. It gives us isPending / isError / data, caches the result,
 *               and reloads when the "queryKey" changes (e.g. a filter changed).
 * useMutation = change data (status, assign, delete). After success we update or
 *               reload the cached data so every screen shows the new values.
 */

export interface ListParams {
  search?: string
  status?: string
  assigned_to?: string
  service_type?: string
  sort?: string
  page?: number
  per_page?: number
}

export function useQuoteRequests(params: ListParams) {
  return useQuery({
    // The filters are part of the key, so each filter combination is cached separately.
    queryKey: ['quote-requests', params],
    queryFn: () => api<Paginated<QuoteRequest>>('/quote-requests', { query: { ...params } }),
    // Keep the current page visible while the next one loads.
    placeholderData: keepPreviousData,
  })
}

export function useQuoteRequest(id: number) {
  return useQuery({
    queryKey: ['quote-request', id],
    queryFn: () => api<QuoteRequest>(`/quote-requests/${id}`),
  })
}

export function useUsers() {
  return useQuery({
    queryKey: ['users'],
    queryFn: () => api<User[]>('/users'),
    staleTime: 5 * 60_000,
  })
}

/**
 * The audit trail, loaded one page (20 events) at a time, newest first.
 * useInfiniteQuery keeps all loaded pages; fetchNextPage() loads the next (older) page.
 * Without this, a request with more than 20 events would hide its oldest history.
 */
export function useAuditEvents(id: number) {
  return useInfiniteQuery({
    queryKey: ['audit-events', id],
    queryFn: ({ pageParam }) =>
      api<AuditEventPage>(`/quote-requests/${id}/audit-events`, { query: { page: pageParam, per_page: 20 } }),
    initialPageParam: 1,
    // Return the next page number, or undefined when we are on the last page.
    getNextPageParam: (last) => (last.meta.page < last.meta.lastPage ? last.meta.page + 1 : undefined),
    retry: 1,
  })
}

/** Shared cache updates after any change to one request. */
function useAfterChange() {
  const queryClient = useQueryClient()

  return (updated: QuoteRequest) => {
    queryClient.setQueryData(['quote-request', updated.id], updated)
    void queryClient.invalidateQueries({ queryKey: ['quote-requests'] })
    // Audit events are delivered by a queue worker, so refresh now and once more shortly after.
    void queryClient.invalidateQueries({ queryKey: ['audit-events', updated.id] })
    setTimeout(() => void queryClient.invalidateQueries({ queryKey: ['audit-events', updated.id] }), 2500)
  }
}

export function useUpdateStatus(id: number) {
  const afterChange = useAfterChange()
  return useMutation({
    mutationFn: (status: QuoteStatus) => api<QuoteRequest>(`/quote-requests/${id}/status`, { method: 'PATCH', body: { status } }),
    onSuccess: afterChange,
  })
}

export function useAssign(id: number) {
  const afterChange = useAfterChange()
  return useMutation({
    mutationFn: (assignedTo: number | null) =>
      api<QuoteRequest>(`/quote-requests/${id}/assign`, { method: 'PATCH', body: { assigned_to: assignedTo } }),
    onSuccess: afterChange,
  })
}

export function useDeleteRequest(id: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: () => api<void>(`/quote-requests/${id}`, { method: 'DELETE' }),
    onSuccess: () => {
      queryClient.removeQueries({ queryKey: ['quote-request', id] })
      void queryClient.invalidateQueries({ queryKey: ['quote-requests'] })
    },
  })
}
