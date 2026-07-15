import { describe, it, expect } from 'vitest';
import { env } from './env';

describe('env', () => {
  it('defaults to local mode', () => {
    expect(env.mode).toBe('local');
  });

  it('exposes city URLs', () => {
    expect(env.cityUrls.livry).toBe('http://localhost');
  });

  it('throws if prod mode lacks required vars', async () => {
    const previous = process.env.E2E_RUN_AGAINST_PROD;
    process.env.E2E_RUN_AGAINST_PROD = 'true';
    delete process.env.E2E_ADMIN_USER;
    vi.resetModules();
    await expect(import('./env')).rejects.toThrow();
    process.env.E2E_RUN_AGAINST_PROD = previous;
  });
});
