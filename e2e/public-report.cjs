const fs = require('node:fs');
const path = require('node:path');

const ROOT = path.join(__dirname, '..', 'test-results', 'public');

function jread(p) { try { return JSON.parse(fs.readFileSync(p, 'utf8')); } catch { return null; } }

const overflowDir = path.join(ROOT, 'overflow');
const touchDir = path.join(ROOT, 'touch-targets');
const imgDir = path.join(ROOT, 'images');

const overflowRows = [];
for (const f of fs.readdirSync(overflowDir)) {
  const data = jread(path.join(overflowDir, f));
  if (!data) continue;
  const [page, ...rest] = f.replace('.json', '').split('-');
  const vp = rest.slice(rest.indexOf(['mobile', 'tablet', 'desktop'].find((k) => rest.includes(k)))).join('-');
  // Simpler: split off last 2 parts as viewport
  const parts = f.replace('.json', '').split('-');
  const viewport = parts.slice(-2).join('-');
  const pageName = parts.slice(0, -2).join('-');
  if (data.scroll > data.width + 1) {
    overflowRows.push({
      page: pageName,
      viewport,
      overflow: data.scroll - data.width,
      worst: data.worst?.[0] ? `${data.worst[0].tag}.${(data.worst[0].cls || '').slice(0,50)} (${data.worst[0].w}px)` : 'n/a',
    });
  }
}

console.log('\n=== OVERFLOW PROBLEMS ===');
for (const r of overflowRows) {
  console.log(`  [${r.viewport.padEnd(12)}] ${r.page.padEnd(15)} +${r.overflow}px — ${r.worst}`);
}
if (overflowRows.length === 0) console.log('  (none)');

console.log('\n=== TOUCH TARGETS (mobile-375) — top offenders per page ===');
for (const f of fs.readdirSync(touchDir)) {
  const data = jread(path.join(touchDir, f));
  if (!data) continue;
  const page = f.replace('-mobile-375.json', '');
  console.log(`\n  ${page} — ${data.count} sub-44px target(s)`);
  // Cluster by class signature
  const groups = {};
  for (const o of data.offenders) {
    const sig = `${o.tag} :: ${(o.cls || '').split(/\s+/).slice(0, 3).join(' ').slice(0, 60)}`;
    if (!groups[sig]) groups[sig] = { count: 0, ex: o.text, avg: 0 };
    groups[sig].count++;
    groups[sig].avg += o.h;
  }
  for (const [sig, g] of Object.entries(groups).sort((a, b) => b[1].count - a[1].count).slice(0, 4)) {
    console.log(`    ×${String(g.count).padStart(2)} | avg ${Math.round(g.avg / g.count)}px | "${g.ex}" — ${sig}`);
  }
}

console.log('\n=== IMAGE HYGIENE (mobile-375) ===');
console.log(`  ${'page'.padEnd(15)} ${'total'.padEnd(6)} ${'no-w'.padEnd(5)} ${'no-h'.padEnd(5)} ${'no-lazy'.padEnd(8)} ${'no-alt'}`);
for (const f of fs.readdirSync(imgDir)) {
  const data = jread(path.join(imgDir, f));
  if (!data) continue;
  const page = f.replace('-mobile-375.json', '');
  console.log(
    `  ${page.padEnd(15)} ${String(data.total).padEnd(6)} ${String(data.missing_width).padEnd(5)} ${String(data.missing_height).padEnd(5)} ${String(data.missing_lazy).padEnd(8)} ${data.missing_alt}`,
  );
}
