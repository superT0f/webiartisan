import { Router } from 'express';
import jwt from 'jsonwebtoken';
import { LogWatcher } from '../../../src/helpers/logWatcher';
import { env } from '../../../src/config/env';
import fs from 'fs';

const router = Router();
const today = new Date().toISOString().slice(0, 10);
const logFiles = [
  `${env.logPaths.api}/api-${today}.log`,
  `${env.logPaths.app}/visits-${today}.log`,
].filter(fs.existsSync);
const watcher = new LogWatcher(logFiles);
let connectionCount = 0;

function verifyQueryToken(req: { url?: string }): { username: string } | null {
  const url = new URL(req.url || '/', 'http://localhost');
  const token = url.searchParams.get('token');
  if (!token) return null;
  try {
    return jwt.verify(token, env.jwtSecret) as { username: string };
  } catch {
    return null;
  }
}

router.get('/stream', (req, res) => {
  if (!verifyQueryToken(req)) {
    res.status(401).json({ error: 'Missing or invalid token' });
    return;
  }

  res.setHeader('Content-Type', 'text/event-stream');
  res.setHeader('Cache-Control', 'no-cache');
  res.setHeader('Connection', 'keep-alive');

  const onLine = (line: string, source: string) => {
    res.write(`data: ${JSON.stringify({ line, source, ts: new Date().toISOString() })}\n\n`);
  };
  watcher.on('line', onLine);

  if (connectionCount === 0) {
    watcher.start();
  }
  connectionCount++;

  req.on('close', () => {
    watcher.off('line', onLine);
    connectionCount--;
    if (connectionCount <= 0) {
      connectionCount = 0;
      watcher.stop();
    }
  });
});

export default router;
