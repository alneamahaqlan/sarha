import { defineConfig } from '@playwright/test';

/**
 * Standalone responsive-audit config — kept at root so the PHPUnit
 * suite under `tests/` stays untouched. Lives in `e2e/`.
 *
 * Runs serially (workers: 1) because every viewport runs login in the
 * setup project and we want a single chromium process to keep the
 * audit deterministic.
 */
export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [['list'], ['json', { outputFile: 'test-results/audit-report.json' }]],
  use: {
    baseURL: 'http://sarha.test',
    ignoreHTTPSErrors: true,
    // Disable animations so screenshots are stable.
    actionTimeout: 10_000,
    navigationTimeout: 30_000,
  },
  projects: [
    {
      name: 'setup',
      testMatch: /auth\.setup\.ts/,
    },
    {
      name: 'audit',
      testMatch: /responsive\.spec\.ts/,
      dependencies: ['setup'],
      use: { storageState: 'test-results/.auth/admin.json' },
    },
    {
      // Public site has no auth gating for the journey pages, so it
      // skips the setup project entirely.
      name: 'public',
      testMatch: /public\.spec\.ts/,
    },
    {
      // CLS measurement on home / search / clinic-detail at 375px.
      // Standalone project so the long-running observer pages don't
      // block the main responsive audit.
      name: 'cls',
      testMatch: /cls\.spec\.ts/,
    },
  ],
});
