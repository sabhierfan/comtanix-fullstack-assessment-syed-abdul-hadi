import react from '@vitejs/plugin-react'
import { defineConfig } from 'vitest/config'

// Test settings. jsdom = a fake browser (DOM) inside Node, so React components can render in tests.
export default defineConfig({
  plugins: [react()],
  test: {
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
    include: ['src/**/*.test.{ts,tsx}'],
    restoreMocks: true,
  },
})
