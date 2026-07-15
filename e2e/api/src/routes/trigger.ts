import { Router } from 'express';
import { spawn } from 'child_process';

const router = Router();

router.post('/', (req, res) => {
  const { tag = 'manual' } = req.body;

  const proc = spawn('npm', ['run', 'test'], {
    cwd: process.cwd(),
    detached: true,
    stdio: 'ignore',
    env: { ...process.env, E2E_RUN_TAG: tag },
  });
  proc.unref();

  res.status(202).json({ ok: true, pid: proc.pid, tag });
});

export default router;
