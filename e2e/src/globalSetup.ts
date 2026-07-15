import { LogWatcher } from './helpers/logWatcher';
import { env } from './config/env';
import fs from 'fs';

const today = new Date().toISOString().slice(0, 10);
const logFiles = [
  `${env.logPaths.api}/api-${today}.log`,
  `${env.logPaths.app}/visits-${today}.log`,
].filter(fs.existsSync);

const watcher = new LogWatcher(logFiles);

export default function setup() {
  fs.mkdirSync('./reports', { recursive: true });
  watcher.start();
  (globalThis as unknown as { __logWatcher: LogWatcher }).__logWatcher = watcher;
}

export function teardown() {
  watcher.stop();
}
