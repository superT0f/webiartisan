import { Router } from 'express';
import { spawn } from 'child_process';
import path from 'path';

const router = Router();

router.post('/', (req, res) => {
  const { tag = 'manual' } = req.body;

  const cwd = path.resolve('..');
  const proc = spawn('npm', ['run', 'test'], {
    cwd,
    detached: true,
    stdio: 'ignore',
    env: { ...process.env, E2E_RUN_TAG: tag },
  });
  proc.unref();

  res.status(202).json({ ok: true, pid: proc.pid, tag });
});

export default router;
