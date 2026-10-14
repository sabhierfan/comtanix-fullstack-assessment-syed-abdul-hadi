import { describe, expect, it } from 'vitest'
import type { AuditEvent } from '../types'
import { describeEvent } from './AuditTimeline'

const names: Record<number, string> = { 2: 'Sara Staff' }
const userName = (id: unknown) => names[id as number] ?? `User #${String(id)}`

function event(type: string, data: Record<string, unknown> = {}): AuditEvent {
  return { id: 'e1', type, actor: null, data, occurred_at: '2026-10-14T12:00:00Z' }
}

describe('describeEvent()', () => {
  it('describes each event type in plain words', () => {
    expect(describeEvent(event('quote_request.created'), userName)).toBe('Request submitted through the public form')
    expect(describeEvent(event('quote_request.status_changed', { from: 'new', to: 'in_review' }), userName)).toBe(
      'Status changed from New to In review',
    )
    expect(describeEvent(event('quote_request.assigned', { from: null, to: 2 }), userName)).toBe('Assigned to Sara Staff')
    expect(describeEvent(event('quote_request.assigned', { from: 2, to: null }), userName)).toBe('Unassigned')
    expect(describeEvent(event('quote_request.deleted'), userName)).toBe('Request deleted')
  })

  it('falls back to the raw type for unknown events', () => {
    expect(describeEvent(event('quote_request.archived'), userName)).toBe('quote_request.archived')
  })
})
