import { Page } from 'puppeteer';

export class LoginPage {
  constructor(private page: Page, private baseUrl: string) {}

  async goto(): Promise<void> {
    await this.page.goto(`${this.baseUrl}/login`);
  }

  async login(email: string, password: string): Promise<void> {
    await this.page.waitForSelector('input[name="email"]');
    await this.page.type('input[name="email"]', email);
    await this.page.type('input[name="password"]', password);
    await this.page.click('button[type="submit"]');
  }

  async isVisible(): Promise<boolean> {
    return this.page.$eval('input[name="email"]', () => true).catch(() => false);
  }
}
