import { createHmac, timingSafeEqual } from 'node:crypto';
import type { RequestHandler } from 'express';
import { HttpError } from '../errors.js';

/**
 * Compare two strings in "constant time": it always takes the same time,
 * whether the first character is wrong or the last one is.
 * A normal === stops at the first difference, and an attacker could measure
 * that tiny time difference to guess the secret one character at a time.
 */
function safeEqual(a: string, b: string): boolean {
  const left = Buffer.from(a);
  const right = Buffer.from(b);
  return left.length === right.length && timingSafeEqual(left, right);
}

/** HMAC-SHA256 of the body with the shared secret, as hex. Laravel does exactly the same. */
export function sign(body: Buffer | string, secret: string): string {
  return createHmac('sha256', secret).update(body).digest('hex');
}

/**
 * Checks the X-Signature header on POST /events.
 *
 * We make our own signature of the raw body and compare it with the one Laravel sent.
 * Same = the event really came from Laravel and nobody changed it on the way.
 * Different or missing = 401, and nothing is saved.
 *
 * Must run after express.raw(), so req.body is the exact bytes that were sent.
 */
export function verifySignature(secret: string): RequestHandler {
  return (req, _res, next) => {
    const signature = req.get('X-Signature');
    if (!signature) {
      return next(new HttpError(401, 'Missing X-Signature header.'));
    }

    // If the body was not JSON, express.raw() leaves it empty, and the check fails.
    const body: Buffer = Buffer.isBuffer(req.body) ? req.body : Buffer.alloc(0);
    if (!safeEqual(signature.trim().toLowerCase(), sign(body, secret))) {
      return next(new HttpError(401, 'Invalid signature.'));
    }

    next();
  };
}

/**
 * Protects GET /events with a simple API key in the X-Api-Key header.
 * Only Laravel has this key; the browser never calls this service directly.
 */
export function requireApiKey(apiKey: string): RequestHandler {
  return (req, _res, next) => {
    const provided = req.get('X-Api-Key');
    if (!provided || !safeEqual(provided, apiKey)) {
      return next(new HttpError(401, 'Invalid or missing API key.'));
    }
    next();
  };
}
