import puppeteer from 'puppeteer';
import type { Browser } from 'puppeteer';

export async function newBrowserContext() {
  const browser = await puppeteer.launch({
    headless: process.env.E2E_HEADLESS !== 'false',
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });
  const page = await browser.newPage();
  page.setDefaultTimeout(30_000);
  return { browser, page };
}

export async function closeBrowser(browser: Browser): Promise<void> {
  await browser.close();
}
