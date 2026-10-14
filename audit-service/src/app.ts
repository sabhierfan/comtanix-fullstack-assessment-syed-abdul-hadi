import express, { type ErrorRequestHandler, type Express } from 'express';
import type { AppConfig } from './config.js';
import { HttpError } from './errors.js';
import { eventsRouter } from './routes/events.js';

export function createApp(config: AppConfig): Express {
  const app = express();

  app.disable('x-powered-by');

  app.get('/health', (_req, res) => {
    res.json({ status: 'ok' });
  });

  app.use(eventsRouter(config));

  app.use((_req, res) => {
    res.status(404).json({ message: 'Not found.' });
  });

  app.use(errorHandler);

  return app;
}

const errorHandler: ErrorRequestHandler = (error, _req, res, _next) => {
  if (error instanceof HttpError) {
    res.status(error.status).json({ message: error.message, ...(error.errors && { errors: error.errors }) });
    return;
  }

  // Errors raised by express.raw() (body too large, wrong encoding, ...).
  const status = (error as { status?: number }).status;
  if (status && status >= 400 && status < 500) {
    res.status(status).json({ message: (error as Error).message });
    return;
  }

  console.error('Unhandled error', error);
  res.status(500).json({ message: 'Internal server error.' });
};
