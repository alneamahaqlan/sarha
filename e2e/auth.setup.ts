import { test as setup, expect } from '@playwright/test';
import * as fs from 'node:fs';
import * as path from 'node:path';

/**
 * One-shot login that hits the admin login endpoint directly and saves
 * the resulting cookie + localStorage state for the audit project to
 * reuse. Going through the API (not the UI) keeps the setup robust
 * against future redesigns of the login screen.
 *
 * Admin credentials are the DatabaseSeeder defaults — never use this
 * config against an environment with real data.
 */

const ADMIN_EMAIL = 'admin@saerha.sa';
const ADMIN_PASSWORD = 'password';
const STORAGE = 'test-results/.auth/admin.json';

setup('authenticate as super-admin', async ({ page }) => {
  fs.mkdirSync(path.dirname(STORAGE), { recursive: true });

  // The API is stateful (statefulApi() in bootstrap/app.php) which means
  // POST /api/v1/auth/login is CSRF-protected and reads the XSRF-TOKEN
  // cookie. We first navigate to the SPA shell so the server issues
  // BOTH the laravel_session cookie AND the XSRF-TOKEN cookie, then
  // we fetch login via the page's fetch() so the cookies + the matching
  // header are sent together. Going through page.evaluate is much
  // simpler than wiring the token into Playwright's APIRequestContext.
  await page.goto('/app/login');
  await page.waitForLoadState('networkidle');

  const result = await page.evaluate(async ({ email, password }) => {
    const xsrf = decodeURIComponent(
      document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '',
    );
    const res = await fetch('/api/v1/auth/login', {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-XSRF-TOKEN': xsrf,
      },
      body: JSON.stringify({ guard: 'admin', email, password }),
    });
    return { status: res.status, body: await res.text() };
  }, { email: ADMIN_EMAIL, password: ADMIN_PASSWORD });

  expect(result.status, `login failed: ${result.status} ${result.body}`).toBeLessThan(400);

  await page.goto('/app/admin/dashboard');
  await page.waitForLoadState('networkidle');

  // Wipe any sidebar preference baked in by prior manual runs so the
  // audit's default-state assertions stay deterministic.
  await page.evaluate(() => {
    try {
      localStorage.removeItem('admin.sidebar.touched');
      localStorage.removeItem('admin.sidebar.collapsed');
    } catch { /* ignore */ }
  });

  await page.context().storageState({ path: STORAGE });
});
