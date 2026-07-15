import { Page } from 'puppeteer';

export class DashboardPage {
  constructor(private page: Page, private baseUrl: string) {}

  async goto(): Promise<void> {
    await this.page.goto(`${this.baseUrl}/espace`);
  }

  async waitForLoaded(): Promise<void> {
    await this.page.waitForFunction(
      () => document.body.innerText.includes('Tableau de bord') || document.body.innerText.includes('Dashboard'),
      { timeout: 10_000 }
    );
  }

  async isVisible(): Promise<boolean> {
    return this.page.evaluate(() => document.body.innerText.includes('Tableau de bord'));
  }
}
