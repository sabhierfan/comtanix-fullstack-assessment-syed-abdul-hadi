import { Schema, model, type InferSchemaType } from 'mongoose';

const auditEventSchema = new Schema(
  {
    eventId: { type: String, required: true },
    type: { type: String, required: true },
    quoteRequestId: { type: Number, required: true },
    actorId: { type: Number, default: null },
    data: { type: Schema.Types.Mixed, default: {} },
    occurredAt: { type: Date, required: true },
    receivedAt: { type: Date, required: true, default: () => new Date() },
  },
  {
    collection: 'audit_events',
    versionKey: false,
    minimize: false,
  },
);

// Idempotency: the same eventId can only be stored once, even under concurrent retries.
auditEventSchema.index({ eventId: 1 }, { unique: true });

// GET /events: filter by quote request (optionally by type), newest first.
auditEventSchema.index({ quoteRequestId: 1, occurredAt: -1, _id: -1 });
auditEventSchema.index({ quoteRequestId: 1, type: 1, occurredAt: -1, _id: -1 });
// GET /events filtered by type only, or unfiltered.
auditEventSchema.index({ type: 1, occurredAt: -1, _id: -1 });
auditEventSchema.index({ occurredAt: -1, _id: -1 });

auditEventSchema.set('toJSON', {
  transform: (_doc, ret: Record<string, unknown>) => {
    ret.id = String(ret._id);
    delete ret._id;
    return ret;
  },
});

export type AuditEventDoc = InferSchemaType<typeof auditEventSchema>;

export const AuditEvent = model('AuditEvent', auditEventSchema);
