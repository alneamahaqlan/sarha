// Aggregate touch-target offenders + cluster by class so the human
// report names the smallest set of fixes that cover the long tail.
const fs = require('node:fs');
const path = require('node:path');

const dir = path.join(__dirname, '..', 'test-results', 'touch-targets');
const files = fs.readdirSync(dir).filter((f) => f.endsWith('.json'));

const perPage = {};
const clusterByClass = {};

for (const f of files) {
  const data = JSON.parse(fs.readFileSync(path.join(dir, f), 'utf8'));
  const page = f.replace('-mobile-375.json', '');
  perPage[page] = data.count;
  for (const o of data.offenders) {
    // Cluster key: tag + first 2 class tokens — captures the component
    // signature without drowning in instance-specific variants.
    const firstClasses = (o.cls ?? '').split(/\s+/).slice(0, 3).join(' ');
    const key = `${o.tag} :: ${firstClasses}`;
    if (!clusterByClass[key]) clusterByClass[key] = { count: 0, pages: new Set(), example: null, avgH: 0 };
    clusterByClass[key].count++;
    clusterByClass[key].pages.add(page);
    clusterByClass[key].avgH += o.h;
    if (!clusterByClass[key].example || o.text.length > clusterByClass[key].example.length) {
      clusterByClass[key].example = o.text;
    }
  }
}

const clusters = Object.entries(clusterByClass)
  .map(([key, v]) => ({
    key,
    count: v.count,
    avgH: Math.round(v.avgH / v.count),
    pages: Array.from(v.pages).sort().join(','),
    example: v.example,
  }))
  .sort((a, b) => b.count - a.count);

console.log('\n=== Per-page touch-target offender count (mobile-375) ===');
for (const [p, c] of Object.entries(perPage).sort((a, b) => b[1] - a[1])) {
  console.log(`  ${p.padEnd(15)} ${c}`);
}
console.log(`  TOTAL          ${Object.values(perPage).reduce((a, b) => a + b, 0)}`);

console.log('\n=== Top class-clusters (signatures appearing most often) ===');
for (const c of clusters.slice(0, 12)) {
  console.log(`  ×${String(c.count).padStart(3)} | avg ${c.avgH}px | pages: ${c.pages}`);
  console.log(`         ${c.key}`);
  console.log(`         example text: "${c.example}"`);
}
