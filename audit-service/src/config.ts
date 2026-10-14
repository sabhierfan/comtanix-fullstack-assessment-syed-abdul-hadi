import 'dotenv/config';

function required(name: string): string {
  const value = process.env[name];
  if (!value) {
    throw new Error(`Missing required environment variable: ${name}`);
  }
  return value;
}

export interface AppConfig {
  /** Shared HMAC-SHA256 secret used to verify events sent by Laravel. */
  signingSecret: string;
  /** API key required to read events (GET /events). Only Laravel's proxy holds it. */
  readApiKey: string;
}

export function loadConfig() {
  return {
    port: Number(process.env.PORT ?? 4000),
    mongoUri: required('MONGO_URI'),
    signingSecret: required('AUDIT_SIGNING_SECRET'),
    readApiKey: required('AUDIT_READ_API_KEY'),
  };
}
