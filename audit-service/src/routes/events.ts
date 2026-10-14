import express, { Router } from 'express';
import { mongo } from 'mongoose';
import type { AppConfig } from '../config.js';
import { HttpError } from '../errors.js';
import { requireApiKey, verifySignature } from '../middleware/auth.js';
import { AuditEvent } from '../models/AuditEvent.js';
import { eventSchema, formatIssues, listQuerySchema } from '../validation.js';

/** MongoDB's error code for "this value already exists in a unique index". */
const DUPLICATE_KEY = 11000;

export function eventsRouter(config: AppConfig): Router {
  const router = Router();

  /**
   * POST /events: Laravel sends one event here.
   * Steps: read raw body -> check signature -> parse JSON -> validate -> save once.
   */
  router.post(
    '/events',
    // Keep the body as raw bytes (not parsed JSON). The signature was made on the
    // exact text Laravel sent, so we must check that exact text. Max size 64 KB.
    express.raw({ type: 'application/json', limit: '64kb' }),
    verifySignature(config.signingSecret),
    async (req, res) => {
      // The signature is OK; now turn the bytes into a JS object.
      let payload: unknown;
      try {
        payload = JSON.parse((req.body as Buffer).toString('utf8'));
      } catch {
        throw new HttpError(400, 'Request body must be valid JSON.');
      }

      // Check the fields (eventId is a UUID, quoteRequestId is a positive number...).
      const parsed = eventSchema.safeParse(payload);
      if (!parsed.success) {
        throw new HttpError(422, 'The event is invalid.', formatIssues(parsed.error));
      }

      const event = parsed.data;

      try {
        await AuditEvent.create({ ...event, occurredAt: new Date(event.occurredAt) });
      } catch (error) {
        // "Idempotent" = sending the same event twice has the same result as once.
        // There is a UNIQUE index on eventId, so MongoDB refuses a second copy.
        // That happens when Laravel retries, or someone replays an old request.
        // We answer "success" (200) so Laravel stops retrying, but nothing new is stored.
        if (error instanceof mongo.MongoServerError && error.code === DUPLICATE_KEY) {
          res.status(200).json({ status: 'duplicate', eventId: event.eventId });
          return;
        }
        throw error;
      }

      res.status(201).json({ status: 'stored', eventId: event.eventId });
    },
  );

  /**
   * GET /events?quoteRequestId=42&type=...&page=1&perPage=20
   * Returns events newest first, one page at a time. Needs the X-Api-Key header.
   */
  router.get('/events', requireApiKey(config.readApiKey), async (req, res) => {
    const parsed = listQuerySchema.safeParse(req.query);
    if (!parsed.success) {
      throw new HttpError(422, 'Invalid query parameters.', formatIssues(parsed.error));
    }

    const { quoteRequestId, type, page, perPage } = parsed.data;

    // Only add the filters that were given.
    const filter: Record<string, unknown> = {};
    if (quoteRequestId !== undefined) filter.quoteRequestId = quoteRequestId;
    if (type !== undefined) filter.type = type;

    // Run both queries at the same time: the page of events, and the total count.
    // Sorting by occurredAt (when it happened) keeps the right order even if a
    // retried event arrived late. _id breaks ties so pages are stable.
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
