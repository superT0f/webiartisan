import { describe, it, expect, beforeAll, afterAll } from 'vitest';
import { env } from '../config/env';
import { ApiClient } from '../helpers/api';
import { newBrowserContext, closeBrowser } from '../helpers/browser';
import { setLocalStorageToken } from '../helpers/cookies';
import { MapPage } from '../pages/MapPage';
import { DashboardPage } from '../pages/DashboardPage';

describe('auth loop', () => {
  const api = new ApiClient(env.apiUrl);
  let consumerEmail: string;
  let consumerPassword: string;
  let consumerUserId: number | undefined;
  let artisanUserId: number | undefined;

  beforeAll(async () => {
    consumerEmail = ApiClient.generateTestEmail();
    consumerPassword = env.testAccounts.password;
    const consumer = await api.registerConsumer(consumerEmail, consumerPassword);
    consumerUserId = consumer.user?.id;
  });

  afterAll(async () => {
    if (consumerUserId) {
      await api.cleanupTestAccount(consumerUserId, env.apiE2eToken);
    }
    if (artisanUserId) {
      await api.cleanupTestAccount(artisanUserId, env.apiE2eToken);
    }
  });

  it('does not loop when localStorage token is stale', async () => {
    const { browser, page } = await newBrowserContext();
    const baseUrl = env.cityUrls.livry;

    try {
      const redirects: string[] = [];
      page.on('framenavigated', (frame) => {
        if (frame === page.mainFrame()) redirects.push(frame.url());
      });

      // Seed a stale token on the app origin, then reload the map.
      await page.goto(`${baseUrl}/carte`, { waitUntil: 'networkidle0' });
      await setLocalStorageToken(page, 'invalid-token');
      await page.goto(`${baseUrl}/carte`, { waitUntil: 'networkidle0' });

      expect(page.url()).toContain('/carte');
      const loginCount = redirects.filter((u) => u.includes('/login')).length;
      expect(loginCount).toBeLessThanOrEqual(1);
    } finally {
      await closeBrowser(browser);
    }
  });

  it('consumer can access the map after API login', async () => {
    const login = await api.loginConsumer(consumerEmail, consumerPassword);
    expect(login.token).toBeDefined();

    const { browser, page } = await newBrowserContext();
    const baseUrl = env.cityUrls.livry;

    try {
      await page.goto(`${baseUrl}/carte`, { waitUntil: 'networkidle0' });
      await setLocalStorageToken(page, login.token);
      await page.goto(`${baseUrl}/carte`, { waitUntil: 'networkidle0' });

      const mapPage = new MapPage(page, baseUrl);
      expect(await mapPage.isVisible()).toBe(true);
    } finally {
      await closeBrowser(browser);
    }
  });

  it('artisan with matching city slug lands on dashboard', async () => {
    const artisanEmail = ApiClient.generateTestEmail();
    const artisanPassword = env.testAccounts.password;
    const artisan = await api.registerArtisan(artisanEmail, artisanPassword, 'livry', {
      companyName: 'E2E Artisan',
      categorySlug: 'boulanger',
      phone: '0600000000',
    });
    artisanUserId = artisan.data?.id;

    await api.activateTestArtisan(artisanUserId!, env.apiE2eToken);

    const login = await api.loginArtisan(artisanEmail, artisanPassword);
    expect(login.token).toBeDefined();

    const { browser, page } = await newBrowserContext();
    const baseUrl = env.cityUrls.livry;

    try {
      await page.goto(`${baseUrl}/espace`, { waitUntil: 'networkidle0' });
      await page.evaluate((token: string) => {
        localStorage.setItem('artisan_token', token);
      }, login.token);
      await page.goto(`${baseUrl}/espace`, { waitUntil: 'networkidle0' });

      const dashboard = new DashboardPage(page, baseUrl);
      await dashboard.waitForLoaded();
      expect(await dashboard.isVisible()).toBe(true);
    } finally {
      await closeBrowser(browser);
    }
  });
});
