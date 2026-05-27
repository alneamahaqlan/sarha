// Throwaway smoke test for the new map-first search feature.
// Drives the page with Playwright against the locally installed system Chrome
// (no extra browser download needed) and captures screenshots of:
//   1. Initial /search render (map + results)
//   2. After a doctor-name search
//   3. After a service-name search
//   4. After clicking "Near me" with a mocked geolocation (Riyadh)
//   5. After opening a marker popup
//
// Files land in scripts/_screenshots/. Logs are printed for visual checks done
// in the run output (rating presence, popup card contents, etc.).
import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';

const OUT = path.resolve('scripts/_screenshots');
fs.mkdirSync(OUT, { recursive: true });

const BASE = process.env.SARHA_URL || 'http://sarha.test';

const CHROME = process.platform === 'win32'
    ? (fs.existsSync('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe')
        ? 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'
        : 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe')
    : undefined;

const browser = await chromium.launch({
    headless: true,
    executablePath: CHROME,
    args: ['--no-sandbox'],
});

const ctx = await browser.newContext({
    viewport: { width: 1366, height: 900 },
    locale: 'ar-SA',
    // Mock geolocation in central Riyadh so "Near me" works deterministically.
    geolocation: { latitude: 24.7136, longitude: 46.6753 },
    permissions: ['geolocation'],
});

// Playwright's geolocation mock only kicks in on secure origins. The site is
// served over plain HTTP (http://sarha.test), so we stub navigator.geolocation
// at the JS level for every page in this context — same coordinates we set
// above, just delivered through a forced override.
await ctx.addInitScript(() => {
    const pos = {
        coords: {
            latitude: 24.7136, longitude: 46.6753,
            accuracy: 10, altitude: null, altitudeAccuracy: null,
            heading: null, speed: null,
        },
        timestamp: Date.now(),
    };
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition = (success) => setTimeout(() => success(pos), 0);
        navigator.geolocation.watchPosition = (success) => { setTimeout(() => success(pos), 0); return 1; };
    }
});

const page = await ctx.newPage();
page.on('pageerror', (e) => console.log('PAGE ERROR:', e.message));
page.on('console', (msg) => {
    if (msg.type() === 'error') console.log('CONSOLE ERROR:', msg.text());
});

function log(name, data) {
    console.log(`\n=== ${name} ===`);
    if (data) console.log(JSON.stringify(data, null, 2));
}

async function shot(name) {
    const file = path.join(OUT, `${name}.png`);
    await page.screenshot({ path: file, fullPage: true });
    console.log('  saved', file);
}

async function inspectMap(label) {
    // Wait for Leaflet to mount + at least one marker to render.
    await page.waitForFunction(() => {
        return !!document.querySelector('#search-map .leaflet-marker-icon');
    }, { timeout: 8000 }).catch(() => {});

    const summary = await page.evaluate(() => {
        const map = document.querySelector('#search-map');
        const markers = document.querySelectorAll('#search-map .leaflet-marker-icon');
        const userDot = !!document.querySelector('#search-map .saerha-user-dot');
        const locateBtn = !!document.querySelector('#search-map-locate-btn');
        const areaBtn = !!document.querySelector('#search-map-area-btn');
        const nearMeBtn = !!document.querySelector('#btn-near-me');
        // Heading shows the total results count (server-rendered).
        const heading = document.querySelector('h1')?.innerText?.trim();
        return {
            mapMounted: !!map,
            markersCount: markers.length,
            hasUserDot: userDot,
            hasLocateBtn: locateBtn,
            hasAreaBtn: areaBtn,
            hasNearMeBtn: nearMeBtn,
            heading,
        };
    });
    log(`map state — ${label}`, summary);
    return summary;
}

// ---- 1. initial /search render -------------------------------------------------
await page.goto(`${BASE}/search?clear=1`, { waitUntil: 'networkidle' });
const initial = await inspectMap('initial');
await shot('01-initial');

// ---- 2. search by doctor name --------------------------------------------------
// Use the suggest endpoint to pull a real doctor name, then submit.
const suggestForDoctor = await page.evaluate(async () => {
    const r = await fetch('/search/suggest?q=د', { headers: { Accept: 'application/json' } });
    return r.json();
});
const doctorName = suggestForDoctor.doctors?.[0]?.label || 'د';
console.log('doctor pick:', doctorName);
await page.goto(`${BASE}/search?q=${encodeURIComponent(doctorName)}`, { waitUntil: 'networkidle' });
const doctorState = await inspectMap('doctor-search');
await shot('02-doctor-search');

// ---- 3. search by service name -------------------------------------------------
const suggestForService = await page.evaluate(async () => {
    const r = await fetch('/search/suggest?q=تن', { headers: { Accept: 'application/json' } });
    return r.json();
});
const serviceName = suggestForService.services?.[0]?.label || 'تنظيف';
console.log('service pick:', serviceName);
await page.goto(`${BASE}/search?q=${encodeURIComponent(serviceName)}`, { waitUntil: 'networkidle' });
const serviceState = await inspectMap('service-search');
await shot('03-service-search');

// ---- 4. open a marker popup and inspect the rich card -------------------------
// Open the first marker's popup via Leaflet's own API — clicking the icon
// directly is flaky when markers overlap or when the sticky nav intercepts
// pointer events. dispatchEvent on the marker's "click" listener is cleaner.
// Click the first marker via its center coordinates. The marker icon img is
// positioned absolutely by Leaflet (translate3d), so a coordinate click bypasses
// the overlap/intercept issues we saw with a CSS-selector click.
{
    const box = await page.locator('#search-map .leaflet-marker-icon').first().boundingBox();
    if (box) await page.mouse.click(box.x + box.width / 2, box.y + box.height / 2);
}
await page.waitForSelector('.saerha-popup-card', { timeout: 5000 }).catch(() => {});
const popup = await page.evaluate(() => {
    const card = document.querySelector('.saerha-popup-card');
    if (!card) return null;
    return {
        name: card.querySelector('.sp-name')?.innerText?.trim(),
        meta: card.querySelector('.sp-meta')?.innerText?.trim().replace(/\s+/g, ' '),
        snippet: card.querySelector('.sp-snippet')?.innerText?.trim().slice(0, 120),
        chips: Array.from(card.querySelectorAll('.sp-chip')).map((c) => c.innerText.trim()),
        hasRating: !!card.querySelector('.sp-rating'),
        hasViewBtn: !!card.querySelector('.sp-btn-primary'),
        hasDirectionsBtn: !!card.querySelector('.sp-btn-secondary'),
    };
});
log('marker popup card', popup);
await shot('04-marker-popup');

// ---- 5. Near me — sort by distance with mocked geolocation --------------------
await page.goto(`${BASE}/search?clear=1`, { waitUntil: 'networkidle' });
// Use waitForNavigation so we capture the actual server-redirected URL.
const nav = page.waitForURL('**/search?**sort=nearest**', { timeout: 10000 });
await page.click('#btn-near-me');
await nav.catch(() => null);
await page.waitForLoadState('networkidle');
const nearMeState = await inspectMap('near-me');
const url = page.url();
log('after near-me', { url });
await shot('05-near-me');

// ---- 6. open a popup on near-me page so we can verify the "distance km" badge.
{
    const box = await page.locator('#search-map .leaflet-marker-icon').first().boundingBox();
    if (box) await page.mouse.click(box.x + box.width / 2, box.y + box.height / 2);
}
await page.waitForSelector('.saerha-popup-card', { timeout: 5000 }).catch(() => {});
const popupNear = await page.evaluate(() => {
    const meta = document.querySelector('.saerha-popup-card .sp-meta')?.innerText?.trim().replace(/\s+/g, ' ');
    return { meta };
});
log('near-me popup meta (should include km)', popupNear);
await shot('06-near-me-popup');

await browser.close();
console.log('\n--- DONE ---');
console.log('initial:    ', initial);
console.log('doctor:     ', doctorState);
console.log('service:    ', serviceState);
console.log('near-me:    ', nearMeState);
console.log('screenshots →', OUT);
