import { Router } from 'express';
import { spawn } from 'child_process';
import path from 'path';

const router = Router();

router.post('/', (_req, res) => {
  const cwd = path.resolve('.');
  const child = spawn('npm', ['run', 'test:prod'], { cwd, shell: true });

  let output = '';
  child.stdout.on('data', (d) => { output += d.toString(); });
  child.stderr.on('data', (d) => { output += d.toString(); });

  child.on('close', (code) => {
    res.json({ exitCode: code, output });
  });
});

export default router;
