# E2E Tests (Playwright)

Run: `npx playwright test` or `npm run test:e2e`. Start the app first: `npm run start` (or `php artisan serve` on port 8001).

## Generate-website flow (smoke)

`flows/generate-website.spec.ts`:

- Create page loads and generate-website entry point is visible
- Chat generates project → redirect to project or show progress
- Chat generates project → inspect/CMS tab opens when redirect completes (Task 5)

## Builder-critical flows (Task 5)

The file `flows/builder-critical.spec.ts` contains real browser specs for:

1. Open project CMS and editor tab
2. Canvas / preview visible in builder
3. Code tab shows output
3b. Code tab shows multi-page output when project has multiple pages
4. Save draft control exists
5a. Fallback mode does not spam status endpoint
5. Persistence after reload
6. Structure or canvas: builder UI has canvas and optional section controls
7. Save draft and reload: builder still loads after save

**To run builder-critical specs** you need a valid project ID:

```bash
# Create DB and seed (creates users/projects)
php artisan migrate:fresh --seed

# Get a project ID (e.g. first project)
php artisan tinker --execute="echo App\Models\Project::first()?->id;"

# Run with project ID (and optional login for protected routes)
TEST_PROJECT_ID=<id> npx playwright test tests/e2e/flows/builder-critical.spec.ts
```

If `TEST_PROJECT_ID` is not set, builder-critical tests are **skipped** (they do not fail). So the rest of the E2E suite can run without a seeded project.

Optional env for login (when project CMS redirects to login):

- `TEST_USER_EMAIL`
- `TEST_USER_PASSWORD`

Use credentials of a user that owns the project (e.g. from your seed or a test user).
