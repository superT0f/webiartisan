import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { LogWatcher } from './logWatcher';
import fs from 'fs';
import path from 'path';
import os from 'os';

describe('LogWatcher', () => {
  let tmpDir: string;
  let watcher: LogWatcher;

  beforeEach(() => {
    tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'e2e-logs-'));
    const logFile = path.join(tmpDir, 'api-test.log');
    fs.writeFileSync(logFile, 'existing line\n');
    watcher = new LogWatcher([logFile]);
  });

  afterEach(() => {
    watcher.stop();
    fs.rmSync(tmpDir, { recursive: true, force: true });
  });

  it('emits new lines appended to a file', async () => {
    const lines: string[] = [];
    watcher.on('line', (line) => lines.push(line));
    watcher.start();

    const logFile = watcher.logFiles[0];
    fs.appendFileSync(logFile, 'new line\n');

    await new Promise((resolve) => setTimeout(resolve, 200));
    expect(lines).toContain('new line');
  });
});
