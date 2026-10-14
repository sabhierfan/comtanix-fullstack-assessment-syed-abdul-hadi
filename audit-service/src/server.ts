import mongoose from 'mongoose';
import { createApp } from './app.js';
import { loadConfig } from './config.js';
import { AuditEvent } from './models/AuditEvent.js';

async function main(): Promise<void> {
  const config = loadConfig();

  await mongoose.connect(config.mongoUri);
  // Build indexes before accepting traffic: the unique eventId index is what makes POST /events idempotent.
  await AuditEvent.init();

  const server = createApp(config).listen(config.port, () => {
    console.log(`Audit service listening on port ${config.port}`);
  });

  // On Ctrl+C or a stop signal: stop taking new requests, finish the current ones,
  // close the database connection, then exit.
  const shutdown = () => {
    server.close(() => {
      void mongoose.disconnect().then(() => process.exit(0));
    });
  };
  process.on('SIGINT', shutdown);
  process.on('SIGTERM', shutdown);
}

main().catch((error) => {
  console.error('Failed to start audit service', error);
  process.exit(1);
});
