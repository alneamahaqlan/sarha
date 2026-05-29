import { test, expect, type Page } from '@playwright/test';
import * as fs from 'node:fs';
import * as path from 'node:path';

/**
 * Responsive audit: 5 admin pages × 3 viewports = 15 test cases.
 *
 * Each test asserts:
 *   1. No horizontal page overflow (documentElement.scrollWidth ≤ innerWidth + 1).
 *   2. A full-page screenshot is captured.
 *   3. On mobile (375), every visible button/link is ≥ 44px tall (soft —
 *      collects offenders and writes them to test-results/touch-targets/).
 *   4. On tablet (820), the sidebar is in icon-rail mode (~64px wide).
 *   5. On desktop (1366), the sidebar is in full mode (~256px wide).
 *
 * Soft assertions (touch targets) write a JSON file per page+viewport so
 * the human-readable report at the end can name the worst offenders.
 */

const PAGES = [
  { slug: 'dashboard',   url: '/app/admin/dashboard' },
  { slug: 'users',       url: '/app/admin/users' },
  { slug: 'bookings',    url: '/app/admin/bookings' },
  { slug: 'clinics',     url: '/app/admin/clinics' },
  { slug: 'user-profile',url: '/app/admin/users/1' },
];

const VIEWPORTS = [
  { label: 'mobile-375',   width: 375,  height: 700 },
  { label: 'tablet-820',   width: 820,  height: 900 },
  { label: 'desktop-1366', width: 1366, height: 800 },
];

const OUT = 'test-results';

/** Wait for the React app to have settled — no spinners, no skeletons. */
async function waitForAppReady(page: Page): Promise<void> {
  // Three signals, any one of which means the app has rendered something
  // meaningful: a main element, a heading, or content with measurable
  // text. We give it up to 8s before bailing — slow CI machines need
  // breathing room.
  await page.waitForLoadState('networkidle', { timeout: 15_000 });
  await page.waitForFunction(
    () => {
      const m = document.querySelector('main');
      return !!m && m.textContent && m.textContent.trim().length > 50;
    },
    null,
    { timeout: 8_000 },
  ).catch(() => { /* don't fail the audit on a slow render */ });
}

for (const pg of PAGES) {
  for (const vp of VIEWPORTS) {
    test(`${pg.slug} @ ${vp.label}`, async ({ page }) => {
      await page.setViewportSize({ width: vp.width, height: vp.height });

      // Reset sidebar preference each test for deterministic state.
      await page.goto('/app/admin/dashboard');
      await page.evaluate(() => {
        try {
          localStorage.removeItem('admin.sidebar.touched');
          localStorage.removeItem('admin.sidebar.collapsed');
        } catch { /* ignore */ }
      });

      await page.goto(pg.url);
      await waitForAppReady(page);

      // ── 1. No horizontal overflow ────────────────────────────────
      const overflow = await page.evaluate(() => ({
        scroll: document.documentElement.scrollWidth,
        width:  window.innerWidth,
        worst:  Array.from(document.querySelectorAll('*'))
          .map((el) => {
            const e = el as HTMLElement;
            return { tag: e.tagName, cls: e.className?.toString?.()?.slice(0, 80) || '', w: e.scrollWidth };
          })
          .filter((x) => x.w > window.innerWidth + 1)
          .sort((a, b) => b.w - a.w)
          .slice(0, 5),
      }));
      const overflowMsg = `${pg.slug}@${vp.label}: scrollWidth=${overflow.scroll}, viewport=${overflow.width}, worst=${JSON.stringify(overflow.worst)}`;
      expect(overflow.scroll, overflowMsg).toBeLessThanOrEqual(overflow.width + 1);

      // ── 2. Screenshot ────────────────────────────────────────────
      const shotPath = path.join(OUT, `${pg.slug}-${vp.label}.png`);
      await page.screenshot({ path: shotPath, fullPage: true });

      // ── 3. Touch target audit (mobile only) ──────────────────────
      if (vp.label === 'mobile-375') {
        const offenders = await page.evaluate(() => {
          const out: Array<{ tag: string; text: string; h: number; w: number; cls: string }> = [];
          const els = document.querySelectorAll('button, a, [role="button"]');
          els.forEach((el) => {
            const e = el as HTMLElement;
            const rect = e.getBoundingClientRect();
            const visible = rect.width > 0 && rect.height > 0 && e.offsetParent !== null;
            if (!visible) return;
            // 1px tolerance for sub-pixel rounding.
            if (rect.height < 43) {
              out.push({
                tag: e.tagName,
                text: (e.textContent ?? '').trim().slice(0, 40) || `[${(e.getAttribute('aria-label') ?? '').slice(0, 30)}]`,
                h: Math.round(rect.height),
                w: Math.round(rect.width),
                cls: (e.className?.toString?.() ?? '').slice(0, 120),
              });
            }
          });
          return out;
        });

        fs.mkdirSync(path.join(OUT, 'touch-targets'), { recursive: true });
        fs.writeFileSync(
          path.join(OUT, 'touch-targets', `${pg.slug}-${vp.label}.json`),
          JSON.stringify({ count: offenders.length, offenders }, null, 2),
        );
        // Soft assertion — log only, don't fail the test. The report
        // step at the end aggregates these into the final table.
        if (offenders.length > 0) {
          console.warn(`  ↳ ${offenders.length} sub-44px target(s) on ${pg.slug}@${vp.label}`);
        }
      }

      // ── 4-5. Sidebar mode ────────────────────────────────────────
      if (vp.label !== 'mobile-375') {
        const asideW = await page.evaluate(() => {
          const a = document.querySelector('aside');
          return a ? a.getBoundingClientRect().width : null;
        });
        if (asideW !== null) {
          fs.mkdirSync(path.join(OUT, 'sidebar'), { recursive: true });
          fs.writeFileSync(
            path.join(OUT, 'sidebar', `${pg.slug}-${vp.label}.json`),
            JSON.stringify({ width: Math.round(asideW) }, null, 2),
          );
          if (vp.label === 'tablet-820') {
            expect(asideW, `tablet sidebar should be icon-rail (~64px), got ${asideW}`).toBeLessThan(100);
          } else if (vp.label === 'desktop-1366') {
            expect(asideW, `desktop sidebar should be full (~256px), got ${asideW}`).toBeGreaterThan(200);
          }
        }
      }
    });
  }
}
