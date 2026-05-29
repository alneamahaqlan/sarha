import { test, expect, type Page } from '@playwright/test';
import * as fs from 'node:fs';
import * as path from 'node:path';

/**
 * Public-site responsive audit. Runs against the live patient-facing
 * Blade pages (no auth required for most). Mirrors the admin audit
 * but checks the journey a real patient takes on a phone.
 */

// Resolve a real clinic slug at fixture time so the detail/booking pages
// work without hard-coding a value that may not exist after a re-seed.
let clinicSlug = 'riyadh-dental';

const PAGES = () => [
  { slug: 'home',            url: '/' },
  { slug: 'search',          url: '/search' },
  { slug: 'search-with-q',   url: '/search?q=%D8%AA%D9%86%D8%B8%D9%8A%D9%81' },
  { slug: 'clinic-detail',   url: `/clinic/${clinicSlug}` },
  { slug: 'booking-form',    url: `/clinic/${clinicSlug}/book` },
  { slug: 'compare-3',       url: '/compare?ids=1,2,3' },
  { slug: 'quotes-board',    url: '/quotes' },
  { slug: 'login',           url: '/login' },
];

const VIEWPORTS = [
  { label: 'mobile-375',   width: 375,  height: 700 },
  { label: 'tablet-820',   width: 820,  height: 900 },
  { label: 'desktop-1366', width: 1366, height: 800 },
];

const OUT = 'test-results/public';

async function waitForRender(page: Page): Promise<void> {
  await page.waitForLoadState('networkidle', { timeout: 15_000 });
  // Public pages are server-rendered so first paint = content. Give a
  // small grace for fonts + reveal animations to settle.
  await page.waitForTimeout(400);
}

/**
 * Forces Chromium to bypass the disk cache for the next navigation so
 * a freshly-rebuilt CSS hash is actually pulled. Tailwind v4 fingerprints
 * the file but Chromium may still serve the previous response.
 */
async function disableCache(page: Page): Promise<void> {
  await page.context().route('**/*', (route) => {
    const headers = { ...route.request().headers(), 'cache-control': 'no-cache, no-store, must-revalidate' };
    route.continue({ headers });
  });
}

for (const pg of PAGES()) {
  for (const vp of VIEWPORTS) {
    test(`public:${pg.slug} @ ${vp.label}`, async ({ page }) => {
      await page.setViewportSize({ width: vp.width, height: vp.height });
      await disableCache(page);
      await page.goto(pg.url, { waitUntil: 'domcontentloaded' });
      await waitForRender(page);

      // 1. Horizontal overflow — the cardinal sin on mobile.
      const overflow = await page.evaluate(() => ({
        scroll: document.documentElement.scrollWidth,
        width: window.innerWidth,
        worst: Array.from(document.querySelectorAll('*'))
          .map((el) => {
            const e = el as HTMLElement;
            return {
              tag: e.tagName,
              id: e.id || null,
              cls: e.className?.toString?.()?.slice(0, 100) || '',
              w: e.scrollWidth,
              ow: e.offsetWidth,
            };
          })
          .filter((x) => x.w > window.innerWidth + 1)
          .sort((a, b) => b.w - a.w)
          .slice(0, 5),
      }));

      fs.mkdirSync(path.join(OUT, 'overflow'), { recursive: true });
      fs.writeFileSync(
        path.join(OUT, 'overflow', `${pg.slug}-${vp.label}.json`),
        JSON.stringify(overflow, null, 2),
      );

      // 2. Screenshot
      fs.mkdirSync(OUT, { recursive: true });
      await page.screenshot({
        path: path.join(OUT, `${pg.slug}-${vp.label}.png`),
        fullPage: true,
      });

      // 3. Touch-target audit (mobile only) — public site emphasis is
      // higher since real patients use their thumbs.
      if (vp.label === 'mobile-375') {
        const offenders = await page.evaluate(() => {
          const out: Array<{ tag: string; text: string; h: number; w: number; cls: string; href?: string }> = [];
          const els = document.querySelectorAll('button, a, input[type="submit"], input[type="button"], [role="button"]');
          els.forEach((el) => {
            const e = el as HTMLElement;
            const rect = e.getBoundingClientRect();
            const visible = rect.width > 0 && rect.height > 0 && e.offsetParent !== null;
            if (!visible) return;
            if (rect.height < 43) {
              out.push({
                tag: e.tagName,
                text: (e.textContent ?? '').trim().slice(0, 50) || `[${(e.getAttribute('aria-label') ?? '').slice(0, 30)}]`,
                h: Math.round(rect.height),
                w: Math.round(rect.width),
                cls: (e.className?.toString?.() ?? '').slice(0, 120),
                href: e.tagName === 'A' ? (e as HTMLAnchorElement).getAttribute('href') ?? undefined : undefined,
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
      }

      // 4. Image hygiene (mobile only — CLS impact biggest there).
      if (vp.label === 'mobile-375') {
        const imgs = await page.evaluate(() => {
          const out: Array<{ src: string; hasW: boolean; hasH: boolean; hasLazy: boolean; alt: boolean }> = [];
          document.querySelectorAll('img').forEach((img) => {
            const src = img.getAttribute('src') ?? img.currentSrc ?? '';
            out.push({
              src: src.slice(0, 100),
              hasW: img.hasAttribute('width'),
              hasH: img.hasAttribute('height'),
              hasLazy: img.getAttribute('loading') === 'lazy',
              alt: img.hasAttribute('alt'),
            });
          });
          return out;
        });
        fs.mkdirSync(path.join(OUT, 'images'), { recursive: true });
        fs.writeFileSync(
          path.join(OUT, 'images', `${pg.slug}-${vp.label}.json`),
          JSON.stringify({
            total: imgs.length,
            missing_width: imgs.filter((i) => !i.hasW).length,
            missing_height: imgs.filter((i) => !i.hasH).length,
            missing_lazy: imgs.filter((i) => !i.hasLazy).length,
            missing_alt: imgs.filter((i) => !i.alt).length,
            images: imgs,
          }, null, 2),
        );
      }

      // HARD assertion (post-P0): any horizontal overflow on mobile-375
      // fails the test, since this is the cardinal sin we just fixed.
      // The other viewports stay informational — the audit catches
      // them via screenshots only.
      if (vp.label === 'mobile-375') {
        expect(
          overflow.scroll,
          `horizontal overflow on ${pg.slug}@${vp.label}: scrollWidth=${overflow.scroll}, viewport=${overflow.width}, worst=${JSON.stringify(overflow.worst.slice(0, 2))}`,
        ).toBeLessThanOrEqual(overflow.width + 1);
      } else if (overflow.scroll > overflow.width + 1) {
        console.warn(`  ⚠ overflow ${pg.slug}@${vp.label}: ${overflow.scroll}/${overflow.width}`);
      }
    });
  }
}
