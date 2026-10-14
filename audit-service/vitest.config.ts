import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    include: ['tests/**/*.test.ts'],
    environment: 'node',
    // Tests share one MongoDB test database, so run files one at a time.
    fileParallelism: false,
    testTimeout: 15000,
    hookTimeout: 30000,
  },
});
