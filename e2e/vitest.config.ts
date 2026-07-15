import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    globals: true,
    globalSetup: './src/globalSetup.ts',
    reporters: ['default', 'json'],
    outputFile: {
      json: './reports/latest.json',
    },
    hookTimeout: 60_000,
    testTimeout: 60_000,
  },
});
