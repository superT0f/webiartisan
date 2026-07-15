import { Router } from 'express';
import { db } from '../db';

const router = Router();

interface Run {
  id: number;
  started_at: string;
  finished_at: string | null;
  status: string;
  passed: number;
  failed: number;
  report_json: string | null;
  logs_jsonl: string | null;
}

router.get('/', (_req, res) => {
  const rows = db.prepare('SELECT * FROM runs ORDER BY started_at DESC').all() as Run[];
  res.json(rows);
});

router.get('/:id', (req, res) => {
  const row = db.prepare('SELECT * FROM runs WHERE id = ?').get(req.params.id) as Run | undefined;
  if (!row) {
    res.status(404).json({ error: 'Run not found' });
    return;
  }
  res.json(row);
});

router.post('/', (req, res) => {
  const { status = 'running' } = req.body;
  const startedAt = new Date().toISOString();
  const result = db
    .prepare('INSERT INTO runs (started_at, status) VALUES (?, ?)')
    .run(startedAt, status);
  res.status(201).json({ id: result.lastInsertRowid });
});

router.patch('/:id', (req, res) => {
  const { status, finished_at, passed, failed, report_json, logs_jsonl } = req.body;
  db.prepare(
    `UPDATE runs SET
      status = COALESCE(?, status),
      finished_at = COALESCE(?, finished_at),
      passed = COALESCE(?, passed),
      failed = COALESCE(?, failed),
      report_json = COALESCE(?, report_json),
      logs_jsonl = COALESCE(?, logs_jsonl)
    WHERE id = ?`
  ).run(status, finished_at, passed, failed, report_json, logs_jsonl, req.params.id);
  res.json({ ok: true });
});

router.delete('/:id', (req, res) => {
  db.prepare('DELETE FROM runs WHERE id = ?').run(req.params.id);
  res.status(204).send();
});

export default router;
