/// <reference types="vitest" />
import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react-swc';
import path from 'path';

// Dedicated Vitest config. Kept separate from vite.config.ts on purpose so the
// production `vite build` never imports test-only dev dependencies (vitest,
// jsdom, MSW). Vitest auto-prefers this file over vite.config.ts.
export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: './src/test/setup.ts',
    css: false,
    include: ['src/**/*.{test,spec}.{ts,tsx}'],
    // MSW needs unhandled requests to surface loudly during tests.
    testTimeout: 10000,
    // In the browser the SPA uses relative API paths (API_BASE_URL = ''), which
    // resolve against the page origin. Node's fetch (under MSW) needs an
    // absolute base, so give tests one — MSW's '*/...' handlers match any host.
    env: {
      VITE_API_BASE_URL: 'http://localhost',
    },
  },
});
