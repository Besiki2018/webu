# AI Generation Test Results

This directory stores results from automated AI website generation runs.

## Generating test sites

From the `Install` directory:

```bash
# Generate 10 sites (one per scenario)
php artisan webu:generate-ai-test-sites

# Generate 50 sites (5 per scenario)
php artisan webu:generate-ai-test-sites --repeat=5

# Specific scenarios only
php artisan webu:generate-ai-test-sites --scenarios=online_clothing_store --scenarios=electronics_store

# Dry run (no DB changes)
php artisan webu:generate-ai-test-sites --dry-run
```

After each run, `manifest.json` lists every generated site with:

- `project_id`, `site_id`
- `storefront_base` — base URL for the storefront (e.g. `https://yourapp.com/app/{project_id}`)
- `pages` — home, shop, product, cart, checkout, contact

## Screenshots

**Automated capture (Playwright):**

1. Start the app and run the generator: `php artisan webu:generate-ai-test-sites`.
2. From `Install/ai-generation-tests`: `npm install playwright && npx playwright install chromium`.
3. Run: `node capture-screenshots.js` (optionally `BASE_URL=http://localhost:8000 node capture-screenshots.js`).
4. Screenshots are saved under `screenshots/{site_name}/` (home.png, shop.png, cart.png, checkout.png). Product page requires a known slug; add it to the script if needed.

## Scenarios

- online_clothing_store  
- electronics_store  
- cosmetics_store  
- pet_shop  
- kids_toys_store  
- furniture_store  
- grocery_store  
- sports_equipment_store  
- digital_products_store  
- luxury_jewelry_store  
