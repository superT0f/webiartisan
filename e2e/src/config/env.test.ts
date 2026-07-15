import { describe, it, expect, vi } from 'vitest';
import { env } from './env';

describe('env', () => {
  it('defaults to local mode', () => {
    expect(env.mode).toBe('local');
  });

  it('exposes city URLs', () => {
    expect(env.cityUrls.livry).toBe('http://localhost');
  });

  it('throws if prod mode lacks required vars', async () => {
    vi.stubEnv('E2E_RUN_AGAINST_PROD', 'true');
    vi.stubEnv('E2E_ADMIN_USER', undefined);
    vi.resetModules();
    await expect(import('./env')).rejects.toThrow();
  });
});
