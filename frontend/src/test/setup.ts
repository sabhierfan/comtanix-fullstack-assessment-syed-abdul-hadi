// Adds matchers like expect(element).toBeInTheDocument() to Vitest.
import '@testing-library/jest-dom/vitest'
import { cleanup } from '@testing-library/react'
import { afterEach } from 'vitest'

// Remove rendered components and saved sessions between tests, so tests don't affect each other.
afterEach(() => {
  cleanup()
  sessionStorage.clear()
})
