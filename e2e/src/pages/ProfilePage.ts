import { Page } from 'puppeteer';

export class ProfilePage {
  constructor(private page: Page, private baseUrl: string) {}

  async goto(): Promise<void> {
    await this.page.goto(`${this.baseUrl}/profil`);
  }

  async isVisible(): Promise<boolean> {
    return this.page.evaluate(() => document.body.innerText.includes('Profil'));
  }
}
