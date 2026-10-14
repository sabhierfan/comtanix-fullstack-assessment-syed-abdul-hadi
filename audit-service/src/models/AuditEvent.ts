import { Schema, model, type InferSchemaType } from 'mongoose';

/**
 * One audit event = one thing that happened to a quote request,
 * e.g. "user 7 changed request 42 from new to in_review".
 * Stored in the MongoDB collection "audit_events".
 */
const auditEventSchema = new Schema(
  {
    eventId: { type: String, required: true }, // random UUID made by Laravel
    type: { type: String, required: true }, // e.g. quote_request.status_changed
    quoteRequestId: { type: Number, required: true },
    actorId: { type: Number, default: null }, // who did it; null = public form
    data: { type: Schema.Types.Mixed, default: {} }, // extra details, e.g. { from, to }
    occurredAt: { type: Date, required: true }, // when it happened in Laravel
    receivedAt: { type: Date, required: true, default: () => new Date() }, // when we got it
  },
  {
    collection: 'audit_events',
    versionKey: false, // no "__v" field
    minimize: false, // keep empty "data: {}" instead of removing it
  },
);

// UNIQUE index: the same eventId can only be saved once, even if two copies arrive
// at the exact same moment. This is what makes POST /events "idempotent".
auditEventSchema.index({ eventId: 1 }, { unique: true });

// Indexes for GET /events, so MongoDB doesn't scan every document.
// Each one matches a filter + "newest first" sort.
auditEventSchema.index({ quoteRequestId: 1, occurredAt: -1, _id: -1 });
auditEventSchema.index({ quoteRequestId: 1, type: 1, occurredAt: -1, _id: -1 });
auditEventSchema.index({ type: 1, occurredAt: -1, _id: -1 });
auditEventSchema.index({ occurredAt: -1, _id: -1 });

// When turned into JSON: rename MongoDB's "_id" to a plain "id" string.
auditEventSchema.set('toJSON', {
  transform: (_doc, ret: Record<string, unknown>) => {
    ret.id = String(ret._id);
    delete ret._id;
    return ret;
  },
});

export type AuditEventDoc = InferSchemaType<typeof auditEventSchema>;

export const AuditEvent = model('AuditEvent', auditEventSchema);
