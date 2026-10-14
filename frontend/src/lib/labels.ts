import type { BudgetRange, QuoteStatus, ServiceType } from '../types'

export const STATUS_LABELS: Record<QuoteStatus, string> = {
  new: 'New',
  in_review: 'In review',
  quoted: 'Quoted',
  closed: 'Closed',
}

export const STATUS_STYLES: Record<QuoteStatus, string> = {
  new: 'bg-sky-50 text-sky-700 ring-sky-600/20',
  in_review: 'bg-amber-50 text-amber-800 ring-amber-600/20',
  quoted: 'bg-violet-50 text-violet-700 ring-violet-600/20',
  closed: 'bg-slate-100 text-slate-600 ring-slate-500/20',
}

export const SERVICE_LABELS: Record<ServiceType, string> = {
  web_development: 'Web development',
  mobile_app: 'Mobile app',
  ui_ux_design: 'UI/UX design',
  cloud_devops: 'Cloud & DevOps',
  consulting: 'Consulting',
}

export const BUDGET_LABELS: Record<BudgetRange, string> = {
  under_5k: 'Under $5k',
  '5k_15k': '$5k – $15k',
  '15k_50k': '$15k – $50k',
  over_50k: 'Over $50k',
}

export const SORT_OPTIONS = [
  { value: '-created_at', label: 'Newest first' },
  { value: 'created_at', label: 'Oldest first' },
  { value: '-updated_at', label: 'Recently updated' },
  { value: 'client_name', label: 'Client A–Z' },
  { value: 'status', label: 'Status' },
] as const

export function formatDateTime(iso: string): string {
  return new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })
}
