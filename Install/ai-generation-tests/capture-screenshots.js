/**
 * Capture screenshots for all sites in ai-generation-tests/manifest.json.
 * Run: node capture-screenshots.js
 * Requires: npm install playwright && npx playwright install chromium
 *
 * Usage:
 *   cd Install/ai-generation-tests && node capture-screenshots.js
 *   BASE_URL=http://localhost:8000 node capture-screenshots.js
 */
const fs = require('fs');
const path = require('path');

const manifestPath = path.join(__dirname, 'manifest.json');
const baseUrl = process.env.BASE_URL || '';

async function main() {
  if (!fs.existsSync(manifestPath)) {
    console.error('Run php artisan webu:generate-ai-test-sites first to create manifest.json');
    process.exit(1);
  }
  let Playwright;
  try {
    Playwright = require('playwright');
  } catch {
    console.error('Install Playwright: npm install playwright && npx playwright install chromium');
    process.exit(1);
  }

  const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
  const sites = manifest.sites || [];
  const browser = await Playwright.chromium.launch({ headless: true });
  const productSlug = process.env.PRODUCT_SLUG || 'smart-watch-pro-1'; // demo seeder first product
  const pagesToCapture = [
    { path: '', file: 'home.png' },
    { path: '/shop', file: 'shop.png' },
    { path: '/product/' + productSlug, file: 'product.png' },
    { path: '/cart', file: 'cart.png' },
    { path: '/checkout', file: 'checkout.png' },
  ];

  for (const site of sites) {
    if (site.error || !site.storefront_base) continue;
    const base = baseUrl ? site.storefront_base.replace(/^https?:\/\/[^/]+/, baseUrl) : site.storefront_base;
    const dirName = (site.name || site.scenario || 'site').replace(/[^a-z0-9_-]/gi, '_');
    const outDir = path.join(__dirname, 'screenshots', dirName);
    fs.mkdirSync(outDir, { recursive: true });

    for (const { path: slug, file } of pagesToCapture) {
      const page = await browser.newPage();
      await page.setViewportSize({ width: 1280, height: 720 });
      try {
        await page.goto(base + slug, { waitUntil: 'domcontentloaded', timeout: 15000 });
        await page.screenshot({ path: path.join(outDir, file), fullPage: false });
      } catch (e) {
        console.warn('Skip', dirName, slug || '/', e.message);
      }
      await page.close();
    }
    if (sites.indexOf(site) % 5 === 0) console.log('Captured', dirName);
  }

  await browser.close();
  console.log('Screenshots saved under ai-generation-tests/screenshots/');
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
