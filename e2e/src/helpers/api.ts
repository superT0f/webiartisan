import { v4 as uuidv4 } from 'uuid';

export interface AuthResponse {
  token: string;
  user?: { id: number; email: string };
}

export class ApiClient {
  constructor(public readonly baseUrl: string) {}

  static generateTestEmail(): string {
    return `e2e-${uuidv4().slice(0, 8)}@prigent.tech`;
  }

  private async request(path: string, options: RequestInit = {}): Promise<unknown> {
    const response = await fetch(`${this.baseUrl}${path}`, {
      headers: { 'Content-Type': 'application/json', ...options.headers },
      ...options,
    });
    const text = await response.text();
    const data = text ? JSON.parse(text) : null;
    if (!response.ok) {
      throw new Error(`API error ${response.status}: ${JSON.stringify(data)}`);
    }
    return data;
  }

  async health(): Promise<unknown> {
    return this.request('/health');
  }

  async registerConsumer(email: string, password: string): Promise<AuthResponse> {
    return this.request('/users/register', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    }) as Promise<AuthResponse>;
  }

  async loginConsumer(email: string, password: string): Promise<AuthResponse> {
    return this.request('/users/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    }) as Promise<AuthResponse>;
  }

  async registerArtisan(
    email: string,
    password: string,
    citySlug: string,
    options: { companyName: string; categorySlug: string; phone: string }
  ): Promise<{ success: boolean; data?: { id: number; status: string }; message?: string; error?: string }> {
    return this.request('/artisans/register', {
      method: 'POST',
      body: JSON.stringify({
        email,
        password,
        city_slug: citySlug,
        company_name: options.companyName,
        category_slug: options.categorySlug,
        phone: options.phone,
      }),
    });
  }

  async activateTestArtisan(artisanId: number, e2eToken: string): Promise<void> {
    await this.request(`/e2e/activate-artisan/${artisanId}`, {
      method: 'POST',
      headers: { 'X-E2E-Token': e2eToken },
    });
  }

  async loginArtisan(email: string, password: string): Promise<AuthResponse> {
    return this.request('/artisans/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    }) as Promise<AuthResponse>;
  }

  async cleanupTestAccount(userId: number, e2eToken: string): Promise<void> {
    await this.request(`/e2e/cleanup/${userId}`, {
      method: 'DELETE',
      headers: { 'X-E2E-Token': e2eToken },
    });
  }
}
