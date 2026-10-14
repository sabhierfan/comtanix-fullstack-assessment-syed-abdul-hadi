import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { AuditEventPage, Paginated, QuoteRequest, QuoteStatus, User } from '../types'
import { api } from './api'

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

export function useAuditEvents(id: number) {
  return useQuery({
    queryKey: ['audit-events', id],
    queryFn: () => api<AuditEventPage>(`/quote-requests/${id}/audit-events`),
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
