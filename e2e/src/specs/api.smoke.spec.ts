import { describe, it, expect } from 'vitest';
import { env } from '../config/env';
import { ApiClient } from '../helpers/api';

describe('api smoke', () => {
  const api = new ApiClient(env.apiUrl);

  it('health endpoint returns 200', async () => {
    const result = await api.health();
    expect(result).toBeDefined();
  });

  it('consumer can register and login', async () => {
    const email = ApiClient.generateTestEmail();
    const password = env.testAccounts.password;
    const register = await api.registerConsumer(email, password);
    expect(register.token).toBeDefined();

    const login = await api.loginConsumer(email, password);
    expect(login.token).toBeDefined();
  });

  it('protected route returns 401 without token', async () => {
    const response = await fetch(`${env.apiUrl}/users/me`);
    expect(response.status).toBe(401);
  });
});
