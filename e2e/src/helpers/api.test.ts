import { describe, it, expect } from 'vitest';
import { ApiClient } from './api';

describe('ApiClient', () => {
  it('has baseUrl from env', () => {
    const api = new ApiClient('http://localhost:8080/api');
    expect(api.baseUrl).toBe('http://localhost:8080/api');
  });

  it('generates unique test emails', () => {
    const e1 = ApiClient.generateTestEmail();
    const e2 = ApiClient.generateTestEmail();
    expect(e1).not.toBe(e2);
    expect(e1).toMatch(/^e2e-.*@prigent\.tech$/);
  });
});
