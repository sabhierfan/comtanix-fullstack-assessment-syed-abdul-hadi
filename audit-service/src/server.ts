import mongoose from 'mongoose';
import { createApp } from './app.js';
import { config } from './config.js';

async function main(): Promise<void> {
  await mongoose.connect(config.mongoUri);

  const app = createApp();
  app.listen(config.port, () => {
    console.log(`Audit service listening on port ${config.port}`);
  });
}

main().catch((error) => {
  console.error('Failed to start audit service', error);
  process.exit(1);
});
