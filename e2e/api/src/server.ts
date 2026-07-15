import express from 'express';
import cors from 'cors';
import path from 'path';
import { env } from '../../src/config/env';
import { db } from './db';
import { authMiddleware, AuthRequest, generateToken } from './auth';
import runsRouter from './routes/runs';
import logsRouter from './routes/logs';
import triggerRouter from './routes/trigger';

const app = express();
const PORT = process.env.E2E_DASHBOARD_PORT || 4000;

app.use(cors({ origin: true, credentials: true }));
app.use(express.json());

app.get('/api/health', (_req, res) => res.json({ ok: true }));

app.post('/api/auth/login', (req, res) => {
  const { username, password } = req.body;
  if (username === env.admin.username && password === env.admin.password) {
    res.json({ token: generateToken(username) });
  } else {
    res.status(401).json({ error: 'Invalid credentials' });
  }
});

app.use('/api/runs', authMiddleware as express.RequestHandler, runsRouter);
app.use('/api/logs', authMiddleware as express.RequestHandler, logsRouter);
app.use('/api/runs/trigger', authMiddleware as express.RequestHandler, triggerRouter);

app.listen(PORT, () => {
  console.log(`Dashboard API listening on http://localhost:${PORT}`);
});
