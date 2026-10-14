export type Role = 'admin' | 'staff'
export type QuoteStatus = 'new' | 'in_review' | 'quoted' | 'closed'
export type ServiceType = 'web_development' | 'mobile_app' | 'ui_ux_design' | 'cloud_devops' | 'consulting'
export type BudgetRange = 'under_5k' | '5k_15k' | '15k_50k' | 'over_50k'

export interface User {
  id: number
  name: string
  email: string
  role: Role
}

export interface QuoteRequest {
  id: number
  client_name: string
  email: string
  company: string | null
  service_type: ServiceType
  budget_range: BudgetRange
  message: string
  status: QuoteStatus
  allowed_transitions: QuoteStatus[]
  assigned_to: number | null
  assignee: User | null
  can: { update_status: boolean; assign: boolean; delete: boolean }
  created_at: string
  updated_at: string
}

export interface Paginated<T> {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null }
}

export interface AuditEvent {
  id: string
  type: string
  actor: { id: number; name: string } | null
  data: Record<string, unknown>
  occurred_at: string
}

export interface AuditEventPage {
  data: AuditEvent[]
  meta: { page: number; perPage: number; total: number; lastPage: number }
}

export interface LoginResponse {
  token: string
  token_type: 'Bearer'
  expires_at: string | null
  user: User
}
