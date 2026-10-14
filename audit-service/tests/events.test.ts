import 'dotenv/config';
import { randomUUID } from 'node:crypto';
import mongoose from 'mongoose';
import request from 'supertest';
import { afterAll, beforeAll, beforeEach, describe, expect, it } from 'vitest';
import { createApp } from '../src/app.js';
import { sign } from '../src/middleware/auth.js';
import { AuditEvent } from '../src/models/AuditEvent.js';

const config = { signingSecret: 'test-signing-secret', readApiKey: 'test-read-key' };
const app = createApp(config);

const mongoUri =
  process.env.MONGO_TEST_URI ??
  'mongodb://audit_user:audit_password@127.0.0.1:27017/audit_service_test?authSource=admin';

function makeEvent(overrides: Record<string, unknown> = {}) {
  return {
    eventId: randomUUID(),
    type: 'quote_request.status_changed',
    quoteRequestId: 42,
    actorId: 7,
    data: { from: 'new', to: 'in_review' },
    occurredAt: '2026-10-14T12:00:00.000Z',
    ...overrides,
  };
}

function postSigned(body: string, signature = sign(body, config.signingSecret)) {
  return request(app)
    .post('/events')
    .set('Content-Type', 'application/json')
    .set('X-Signature', signature)
    .send(body);
}

beforeAll(async () => {
  await mongoose.connect(mongoUri);
  await AuditEvent.init();
});

beforeEach(async () => {
  await AuditEvent.deleteMany({});
});

afterAll(async () => {
  await mongoose.connection.dropDatabase();
  await mongoose.disconnect();
});

describe('POST /events signature verification', () => {
  it('rejects a request without X-Signature', async () => {
    const res = await request(app).post('/events').set('Content-Type', 'application/json').send(JSON.stringify(makeEvent()));

    expect(res.status).toBe(401);
    expect(res.body.message).toBe('Missing X-Signature header.');
    expect(await AuditEvent.countDocuments()).toBe(0);
  });

  it('rejects a signature made with the wrong secret', async () => {
    const body = JSON.stringify(makeEvent());
    const res = await postSigned(body, sign(body, 'wrong-secret'));

    expect(res.status).toBe(401);
    expect(res.body.message).toBe('Invalid signature.');
    expect(await AuditEvent.countDocuments()).toBe(0);
  });

  it('rejects a body that was changed after signing', async () => {
    const original = JSON.stringify(makeEvent());
    const tampered = original.replace('"in_review"', '"closed"');
    const res = await postSigned(tampered, sign(original, config.signingSecret));

    expect(res.status).toBe(401);
    expect(await AuditEvent.countDocuments()).toBe(0);
  });

  it('stores an event with a valid signature', async () => {
    const event = makeEvent();
    const res = await postSigned(JSON.stringify(event));

    expect(res.status).toBe(201);
    expect(res.body).toEqual({ status: 'stored', eventId: event.eventId });

    const stored = await AuditEvent.findOne({ eventId: event.eventId }).lean();
    expect(stored?.quoteRequestId).toBe(42);
    expect(stored?.data).toEqual({ from: 'new', to: 'in_review' });
  });
});

describe('POST /events idempotency', () => {
  it('stores the same eventId only once and still returns success', async () => {
    const body = JSON.stringify(makeEvent());

    const first = await postSigned(body);
    const second = await postSigned(body);

    expect(first.status).toBe(201);
    expect(second.status).toBe(200);
    expect(second.body.status).toBe('duplicate');
    expect(await AuditEvent.countDocuments()).toBe(1);
  });

  it('stays at one document when the same event arrives concurrently', async () => {
    const body = JSON.stringify(makeEvent());

    const responses = await Promise.all(Array.from({ length: 5 }, () => postSigned(body)));

    expect(responses.every((r) => r.status === 200 || r.status === 201)).toBe(true);
    expect(responses.filter((r) => r.status === 201)).toHaveLength(1);
    expect(await AuditEvent.countDocuments()).toBe(1);
  });
});

describe('POST /events validation', () => {
  it('returns 422 with field errors for an invalid event', async () => {
    const res = await postSigned(JSON.stringify(makeEvent({ eventId: 'not-a-uuid', quoteRequestId: -1, extra: true })));

    expect(res.status).toBe(422);
    expect(Object.keys(res.body.errors)).toEqual(expect.arrayContaining(['eventId', 'quoteRequestId']));
  });

  it('returns 400 for a signed body that is not JSON', async () => {
    const res = await postSigned('not json');

    expect(res.status).toBe(400);
  });
});

describe('GET /health', () => {
  it('reports ok when MongoDB is connected', async () => {
    const res = await request(app).get('/health');

    expect(res.status).toBe(200);
    expect(res.body).toEqual({ status: 'ok', mongo: 'up' });
  });
});

describe('GET /events', () => {
  async function seed() {
    await AuditEvent.create([
      makeEvent({ type: 'quote_request.created', occurredAt: '2026-10-14T10:00:00Z', data: {} }),
      makeEvent({ type: 'quote_request.assigned', occurredAt: '2026-10-14T11:00:00Z', data: { from: null, to: 3 } }),
      makeEvent({ occurredAt: '2026-10-14T12:00:00Z' }),
      makeEvent({ quoteRequestId: 99, occurredAt: '2026-10-14T13:00:00Z' }),
    ]);
  }

  it('requires the read API key', async () => {
    const res = await request(app).get('/events');

    expect(res.status).toBe(401);
  });

  it('filters by quoteRequestId and returns newest first', async () => {
    await seed();

    const res = await request(app).get('/events?quoteRequestId=42').set('X-Api-Key', config.readApiKey);

    expect(res.status).toBe(200);
    expect(res.body.data.map((e: { type: string }) => e.type)).toEqual([
      'quote_request.status_changed',
      'quote_request.assigned',
      'quote_request.created',
    ]);
    expect(res.body.meta).toEqual({ page: 1, perPage: 20, total: 3, lastPage: 1 });
  });

  it('filters by type and paginates', async () => {
    await seed();

    const res = await request(app)
      .get('/events?type=quote_request.status_changed&perPage=1&page=2')
      .set('X-Api-Key', config.readApiKey);

    expect(res.status).toBe(200);
    expect(res.body.data).toHaveLength(1);
    expect(res.body.data[0].quoteRequestId).toBe(42);
    expect(res.body.meta).toEqual({ page: 2, perPage: 1, total: 2, lastPage: 2 });
  });

  it('rejects invalid query parameters', async () => {
    const res = await request(app).get('/events?perPage=500').set('X-Api-Key', config.readApiKey);

    expect(res.status).toBe(422);
    expect(res.body.errors.perPage).toBeDefined();
  });
});
