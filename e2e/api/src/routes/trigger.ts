import { Router } from 'express';
import { spawn } from 'child_process';
import path from 'path';

const router = Router();
const OUTPUT_LIMIT = 500 * 1024; // 500 KB
const TIMEOUT_MS = 5 * 60 * 1000; // 5 minutes

router.post('/', (_req, res) => {
  const cwd = path.resolve('.');
  const child = spawn('npm', ['run', 'test:prod'], { cwd });

  let output = '';
  let timeoutId: NodeJS.Timeout | null = null;
  let responded = false;

  const finish = (exitCode: number | null, output: string, error?: string) => {
    if (responded) return;
    responded = true;
    if (timeoutId) clearTimeout(timeoutId);
    if (child.exitCode === null && child.signalCode === null) {
      child.kill('SIGTERM');
    }
    res.json({ exitCode, output, error });
  };

  child.stdout.on('data', (d) => {
    output = (output + d.toString()).slice(-OUTPUT_LIMIT);
  });
  child.stderr.on('data', (d) => {
    output = (output + d.toString()).slice(-OUTPUT_LIMIT);
  });

  child.on('error', (err) => {
    finish(null, output, `Spawn failed: ${err.message}`);
  });

  child.on('close', (code) => {
    finish(code, output);
  });

  timeoutId = setTimeout(() => {
    finish(null, output, `Timeout after ${TIMEOUT_MS}ms`);
  }, TIMEOUT_MS);
});

export default router;
