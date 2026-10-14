import { z } from 'zod';

export const eventSchema = z
  .object({
    eventId: z.uuid(),
    type: z
      .string()
      .max(100)
      .regex(/^[a-z_]+\.[a-z_]+$/, 'type must look like "entity.action"'),
    quoteRequestId: z.number().int().positive(),
    actorId: z.number().int().positive().nullable(),
    data: z.record(z.string(), z.unknown()),
    occurredAt: z.iso.datetime({ offset: true }),
  })
  .strict();

export type IncomingEvent = z.infer<typeof eventSchema>;

export const listQuerySchema = z.object({
  quoteRequestId: z.coerce.number().int().positive().optional(),
  type: z.string().max(100).optional(),
  page: z.coerce.number().int().min(1).default(1),
  perPage: z.coerce.number().int().min(1).max(100).default(20),
});

export type ListQuery = z.infer<typeof listQuerySchema>;

/** Flattens zod issues into { field: [messages] }, the same shape Laravel uses. */
export function formatIssues(error: z.ZodError): Record<string, string[]> {
  const errors: Record<string, string[]> = {};
  for (const issue of error.issues) {
    const key = issue.path.join('.') || '_';
    (errors[key] ??= []).push(issue.message);
  }
  return errors;
}
