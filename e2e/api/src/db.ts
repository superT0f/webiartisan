import Database from 'better-sqlite3';
import fs from 'fs';
import path from 'path';

const dbPath = process.env.E2E_DB_PATH || path.resolve('data/runs.sqlite');
fs.mkdirSync(path.dirname(dbPath), { recursive: true });
export const db = new Database(dbPath);

db.exec(`
  CREATE TABLE IF NOT EXISTS runs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    started_at TEXT NOT NULL,
    finished_at TEXT,
    status TEXT NOT NULL,
    passed INTEGER DEFAULT 0,
    failed INTEGER DEFAULT 0,
    report_json TEXT,
    logs_jsonl TEXT
  );

  CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL
  );
`);
