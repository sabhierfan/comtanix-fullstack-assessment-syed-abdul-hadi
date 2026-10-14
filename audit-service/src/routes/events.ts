import express, { Router } from 'express';
import { mongo } from 'mongoose';
import type { AppConfig } from '../config.js';
import { HttpError } from '../errors.js';
import { requireApiKey, verifySignature } from '../middleware/auth.js';
import { AuditEvent } from '../models/AuditEvent.js';
import { eventSchema, formatIssues, listQuerySchema } from '../validation.js';

const DUPLICATE_KEY = 11000;

export function eventsRouter(config: AppConfig): Router {
  const router = Router();

  router.post(
    '/events',
    // Keep the raw bytes: the signature is computed over the body exactly as sent.
    express.raw({ type: 'application/json', limit: '64kb' }),
    verifySignature(config.signingSecret),
    async (req, res) => {
      let payload: unknown;
      try {
        payload = JSON.parse((req.body as Buffer).toString('utf8'));
      } catch {
        throw new HttpError(400, 'Request body must be valid JSON.');
      }

      const parsed = eventSchema.safeParse(payload);
      if (!parsed.success) {
        throw new HttpError(422, 'The event is invalid.', formatIssues(parsed.error));
      }

      const event = parsed.data;

      try {
        await AuditEvent.create({ ...event, occurredAt: new Date(event.occurredAt) });
      } catch (error) {
        // Same eventId already stored (a retry or a replay): acknowledge without storing again.
        if (error instanceof mongo.MongoServerError && error.code === DUPLICATE_KEY) {
          res.status(200).json({ status: 'duplicate', eventId: event.eventId });
          return;
        }
        throw error;
      }

      res.status(201).json({ status: 'stored', eventId: event.eventId });
    },
  );

  router.get('/events', requireApiKey(config.readApiKey), async (req, res) => {
    const parsed = listQuerySchema.safeParse(req.query);
    if (!parsed.success) {
      throw new HttpError(422, 'Invalid query parameters.', formatIssues(parsed.error));
    }

    const { quoteRequestId, type, page, perPage } = parsed.data;
    const filter: Record<string, unknown> = {};
    if (quoteRequestId !== undefined) filter.quoteRequestId = quoteRequestId;
    if (type !== undefined) filter.type = type;

    const [events, total] = await Promise.all([
      AuditEvent.find(filter)
        .sort({ occurredAt: -1, _id: -1 })
        .skip((page - 1) * perPage)
        .limit(perPage),
      AuditEvent.countDocuments(filter),
    ]);

    res.json({
      data: events,
      meta: { page, perPage, total, lastPage: Math.max(1, Math.ceil(total / perPage)) },
    });
  });

  return router;
}
