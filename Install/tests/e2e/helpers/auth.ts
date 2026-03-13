import { expect, type Page } from '@playwright/test';

const USER_EMAIL = process.env.TEST_USER_EMAIL;
const USER_PASSWORD = process.env.TEST_USER_PASSWORD;

export async function ensureAuthenticatedPage(page: Page, targetUrl: string): Promise<void> {
  await page.goto(targetUrl);
  if (!page.url().includes('/login')) {
    return;
  }

  if (!USER_EMAIL || !USER_PASSWORD) {
    throw new Error('Login required for E2E builder flow. Set TEST_USER_EMAIL and TEST_USER_PASSWORD, or provide PLAYWRIGHT_AUTH_STORAGE_STATE.');
  }

  const loginPageResponse = await page.context().request.get('/login');
  const loginPageHtml = await loginPageResponse.text();
  const csrfToken = loginPageHtml.match(/name="csrf-token"\s+content="([^"]+)"/i)?.[1]
    ?? loginPageHtml.match(/name="_token"\s+value="([^"]+)"/i)?.[1]
    ?? null;

  if (!csrfToken) {
    throw new Error('Unable to resolve CSRF token for E2E login.');
  }

  await page.context().request.post('/login', {
    form: {
      _token: csrfToken,
      email: USER_EMAIL,
      password: USER_PASSWORD,
    },
    headers: {
      referer: loginPageResponse.url(),
    },
  });

  await page.goto(targetUrl);
  await expect(page).not.toHaveURL(/\/login/, { timeout: 30000 });

  if (!page.url().includes(targetUrl)) {
    await page.goto(targetUrl);
  }
}
