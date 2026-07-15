function requireEnv(key: string): string {
  const value = process.env[key];
  if (!value) {
    throw new Error(`Missing required environment variable: ${key}`);
  }
  return value;
}

export const mode = process.env.E2E_RUN_AGAINST_PROD === 'true' ? 'prod' : 'local';

export const env = {
  mode,
  isProd: mode === 'prod',
  apiUrl: process.env.E2E_API_URL || 'http://localhost:8080/api',
  appUrl: process.env.E2E_APP_URL || 'http://localhost',
  cityUrls: {
    livry: process.env.E2E_LIVRY_URL || 'http://localhost',
    combs: process.env.E2E_COMBS_URL || 'http://localhost',
    vertSaintDenis: process.env.E2E_VSD_URL || 'http://localhost',
  },
  admin: {
    username: process.env.E2E_ADMIN_USER || 'admin',
    password: process.env.E2E_ADMIN_PASS || 'admin',
  },
  testAccounts: {
    password: process.env.E2E_TEST_PASSWORD || 'Password123!',
  },
  logPaths: {
    api: '/home/tof/mnt/gandi/vhosts/api.prigent.tech/storage/logs',
    app: '/home/tof/mnt/gandi/vhosts/app.prigent.tech/htdocs/logs',
  },
  jwtSecret: process.env.E2E_JWT_SECRET || 'dev-secret',
  apiE2eToken: process.env.E2E_API_E2E_TOKEN || '',
};

if (env.isProd) {
  requireEnv('E2E_ADMIN_USER');
  requireEnv('E2E_ADMIN_PASS');
  requireEnv('E2E_JWT_SECRET');
  requireEnv('E2E_API_E2E_TOKEN');
}
