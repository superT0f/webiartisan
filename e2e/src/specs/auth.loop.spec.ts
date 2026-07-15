import { describe, it, expect, beforeAll, afterAll } from 'vitest';
import { env } from '../config/env';
import { ApiClient } from '../helpers/api';
import { newBrowserContext, closeBrowser } from '../helpers/browser';
import { setLocalStorageToken } from '../helpers/cookies';
import { LoginPage } from '../pages/LoginPage';
import { MapPage } from '../pages/MapPage';
import { DashboardPage } from '../pages/DashboardPage';

describe('auth loop', () => {
  const api = new ApiClient(env.apiUrl);
  let consumerEmail: string;
  let consumerPassword: string;

  beforeAll(async () => {
    consumerEmail = ApiClient.generateTestEmail();
    consumerPassword = env.testAccounts.password;
    await api.registerConsumer(consumerEmail, consumerPassword);
  });

  it('does not loop when localStorage token is stale', async () => {
    const { browser, page } = await newBrowserContext();
    const baseUrl = env.cityUrls.livry;

    try {
      await setLocalStorageToken(page, 'invalid-token');
      await page.goto(baseUrl);

      const redirects: string[] = [];
      page.on('framenavigated', (frame) => {
        if (frame === page.mainFrame()) redirects.push(frame.url());
      });

      const loginPage = new LoginPage(page, baseUrl);
      await loginPage.goto();
      await loginPage.login(consumerEmail, consumerPassword);

      const mapPage = new MapPage(page, baseUrl);
      await mapPage.isVisible();

      const loginCount = redirects.filter((u) => u.includes('/login')).length;
      expect(loginCount).toBeLessThanOrEqual(1);
    } finally {
      await closeBrowser(browser);
    }
  });

  it('artisan with matching city slug lands on dashboard', async () => {
    const artisanEmail = ApiClient.generateTestEmail();
    const artisanPassword = env.testAccounts.password;
    await api.registerArtisan(artisanEmail, artisanPassword, 'livry');

    const { browser, page } = await newBrowserContext();
    const baseUrl = env.cityUrls.livry;

    try {
      const loginPage = new LoginPage(page, baseUrl);
      await loginPage.goto();
      await loginPage.login(artisanEmail, artisanPassword);

      const dashboard = new DashboardPage(page, baseUrl);
      await dashboard.waitForLoaded();
      expect(await dashboard.isVisible()).toBe(true);
    } finally {
      await closeBrowser(browser);
    }
  });
});
