import { Router } from 'express';

const router = Router();

router.get('/stream', (req, res) => {
  res.setHeader('Content-Type', 'text/event-stream');
  res.setHeader('Cache-Control', 'no-cache');
  res.setHeader('Connection', 'keep-alive');
  res.flushHeaders();

  const send = (data: unknown) => {
    res.write(`data: ${JSON.stringify(data)}\n\n`);
  };

  send({ type: 'connected', time: new Date().toISOString() });

  const interval = setInterval(() => {
    send({ type: 'heartbeat', time: new Date().toISOString() });
  }, 30000);

  req.on('close', () => {
    clearInterval(interval);
    res.end();
  });
});

export default router;
