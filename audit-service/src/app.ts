import express, { type ErrorRequestHandler, type Express } from 'express';
import mongoose from 'mongoose';
import type { AppConfig } from './config.js';
import { HttpError } from './errors.js';
import { eventsRouter } from './routes/events.js';

/**
 * Builds the Express app (routes + error handling) without starting it.
 * server.ts starts it; the tests use it directly with supertest.
 */
export function createApp(config: AppConfig): Express {
  const app = express();

  // Don't tell visitors we run Express (less info for attackers).
  app.disable('x-powered-by');

  // Health check for load balancers / Docker. "ok" only when MongoDB is connected too;
  // otherwise 503, so traffic isn't sent to an instance that can't save events.
  // (readyState 1 = connected)
  app.get('/health', (_req, res) => {
    const dbUp = mongoose.connection.readyState === 1;
    res.status(dbUp ? 200 : 503).json({ status: dbUp ? 'ok' : 'unavailable', mongo: dbUp ? 'up' : 'down' });
  });

  app.use(eventsRouter(config));

  // Any other URL -> 404 in JSON.
  app.use((_req, res) => {
    res.status(404).json({ message: 'Not found.' });
  });

  app.use(errorHandler);

  return app;
}

/**
 * One place that turns every error into a clean JSON answer.
 * Express 5 sends errors thrown in async routes here automatically.
 */
const errorHandler: ErrorRequestHandler = (error, _req, res, _next) => {
  // Errors we threw on purpose (401, 400, 422...).
  if (error instanceof HttpError) {
    res.status(error.status).json({ message: error.message, ...(error.errors && { errors: error.errors }) });
    return;
  }

  // Errors raised by express.raw() (body too large = 413, wrong encoding, ...).
  const status = (error as { status?: number }).status;
  if (status && status >= 400 && status < 500) {
    res.status(status).json({ message: (error as Error).message });
    return;
  }

  // Anything else is a bug or a database problem. Log it, but don't show details to the caller.
  console.error('Unhandled error', error);
  res.status(500).json({ message: 'Internal server error.' });
};
