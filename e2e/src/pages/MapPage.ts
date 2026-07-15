import { Page } from 'puppeteer';

export class MapPage {
  constructor(private page: Page, private baseUrl: string) {}

  async goto(): Promise<void> {
    await this.page.goto(`${this.baseUrl}/carte`);
  }

  async isVisible(): Promise<boolean> {
    return this.page.evaluate(() => document.body.innerText.includes('Carte'));
  }
}
