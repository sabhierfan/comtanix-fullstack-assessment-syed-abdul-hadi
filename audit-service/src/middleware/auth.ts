import { createHmac, timingSafeEqual } from 'node:crypto';
import type { RequestHandler } from 'express';
import { HttpError } from '../errors.js';

function safeEqual(a: string, b: string): boolean {
  const left = Buffer.from(a);
  const right = Buffer.from(b);
  return left.length === right.length && timingSafeEqual(left, right);
}

export function sign(body: Buffer | string, secret: string): string {
  return createHmac('sha256', secret).update(body).digest('hex');
}

/**
 * Verifies X-Signature: hex HMAC-SHA256 of the exact raw request body.
 * Must run after express.raw() so req.body is the untouched Buffer.
 */
export function verifySignature(secret: string): RequestHandler {
  return (req, _res, next) => {
    const signature = req.get('X-Signature');
    if (!signature) {
      return next(new HttpError(401, 'Missing X-Signature header.'));
    }

    const body: Buffer = Buffer.isBuffer(req.body) ? req.body : Buffer.alloc(0);
    if (!safeEqual(signature.trim().toLowerCase(), sign(body, secret))) {
      return next(new HttpError(401, 'Invalid signature.'));
    }

    next();
  };
}

/** Protects the read endpoint with a static API key (X-Api-Key header). */
export function requireApiKey(apiKey: string): RequestHandler {
  return (req, _res, next) => {
    const provided = req.get('X-Api-Key');
    if (!provided || !safeEqual(provided, apiKey)) {
      return next(new HttpError(401, 'Invalid or missing API key.'));
    }
    next();
  };
}
