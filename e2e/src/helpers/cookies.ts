import { Page } from 'puppeteer';

export async function setLocalStorageToken(page: Page, token: string, key = 'user_token'): Promise<void> {
  await page.evaluate((k, t) => {
    localStorage.setItem(k, t);
  }, key, token);
}

export async function clearLocalStorageToken(page: Page, key = 'user_token'): Promise<void> {
  await page.evaluate((k) => {
    localStorage.removeItem(k);
  }, key);
}

export async function setTokenCookie(
  page: Page,
  token: string,
  domain: string,
  key = 'user_token'
): Promise<void> {
  await page.setCookie({
    name: key,
    value: token,
    domain,
    path: '/',
    httpOnly: false,
    secure: true,
    sameSite: 'Lax',
  });
}
