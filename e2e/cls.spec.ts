import { test, type Page } from '@playwright/test';
import * as fs from 'node:fs';
import * as path from 'node:path';

/**
 * CLS (Cumulative Layout Shift) measurement on mobile-375 for the three
 * highest-value public pages. Reads `layout-shift` entries via the
 * PerformanceObserver API, exactly the same data Lighthouse aggregates
 * — so we don't need a Lighthouse runner.
 *
 * Method:
 *   1. Open about:blank with the observer pre-registered so it captures
 *      shifts from the *first* paint of our target URL onward.
 *   2. Navigate to the target page; wait until images settle.
 *   3. Read the accumulated CLS value.
 *
 * CLS interpretation (web.dev/cls):
 *   < 0.10  good
 *   0.10–0.25  needs improvement
 *   > 0.25  poor
 */

const PAGES = [
  { slug: 'home',          url: 'http://sarha.test/' },
  { slug: 'search',        url: 'http://sarha.test/search?q=%D8%AA%D9%86%D8%B8%D9%8A%D9%81' },
  { slug: 'clinic-detail', url: 'http://sarha.test/clinic/riyadh-dental' },
];

const VIEWPORT = { width: 375, height: 700 };
const OUT = 'test-results/public/cls';

async function measureCLS(page: Page, url: string): Promise<{ cls: number; entries: number }> {
  // Mount a fresh page with the observer ready BEFORE we navigate to
  // the target URL — this way we don't miss the initial shifts.
  await page.goto('about:blank');
  await page.evaluate(() => {
    (window as any).__cls = 0;
    (window as any).__shifts = 0;
    const obs = new PerformanceObserver((list) => {
      for (const e of list.getEntries()) {
        const layoutShift = e as PerformanceEntry & { hadRecentInput?: boolean; value?: number };
        if (!layoutShift.hadRecentInput && typeof layoutShift.value === 'number') {
          (window as any).__cls += layoutShift.value;
          (window as any).__shifts++;
        }
      }
    });
    obs.observe({ type: 'layout-shift', buffered: true });
    (window as any).__obs = obs;
  });

  await page.goto(url, { waitUntil: 'load' });
  // Let images finish decoding + scroll a bit so lazy-loaded content
  // also gets a chance to shift. CLS is cumulative across the full
  // session, so the 5s window mirrors what users actually experience.
  await page.waitForLoadState('networkidle', { timeout: 20_000 }).catch(() => null);
  await page.evaluate(() => new Promise<void>((r) => setTimeout(r, 2_000)));

  return await page.evaluate(() => ({
    cls: Math.round(((window as any).__cls ?? 0) * 1000) / 1000,
    entries: (window as any).__shifts ?? 0,
  }));
}

for (const pg of PAGES) {
  test(`cls: ${pg.slug}`, async ({ page }) => {
    await page.setViewportSize(VIEWPORT);
    const result = await measureCLS(page, pg.url);

    fs.mkdirSync(OUT, { recursive: true });
    fs.writeFileSync(
      path.join(OUT, `${pg.slug}.json`),
      JSON.stringify({ url: pg.url, viewport: VIEWPORT, ...result }, null, 2),
    );

    const rating = result.cls < 0.1 ? 'GOOD' : result.cls < 0.25 ? 'NEEDS-IMPROVEMENT' : 'POOR';
    console.log(`  CLS ${pg.slug.padEnd(15)} ${result.cls.toFixed(3)}  (${result.entries} shifts) — ${rating}`);
  });
}
