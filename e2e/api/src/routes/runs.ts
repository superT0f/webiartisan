import { Router } from 'express';
import { db } from '../db';

const router = Router();

router.get('/', (_req, res) => {
  const rows = db.prepare('SELECT * FROM runs ORDER BY started_at DESC LIMIT 50').all();
  res.json(rows);
});

router.get('/:id', (req, res) => {
  const row = db.prepare('SELECT * FROM runs WHERE id = ?').get(req.params.id);
  if (!row) return res.status(404).json({ error: 'Not found' });
  res.json(row);
});

router.post('/import', (req, res) => {
  const { started_at, finished_at, status, passed, failed, report_json, logs_jsonl } = req.body;
  const result = db.prepare(
    `INSERT INTO runs (started_at, finished_at, status, passed, failed, report_json, logs_jsonl)
     VALUES (?, ?, ?, ?, ?, ?, ?)`
  ).run(started_at, finished_at, status, passed, failed, report_json, logs_jsonl);
  res.json({ id: result.lastInsertRowid });
});

export default router;
