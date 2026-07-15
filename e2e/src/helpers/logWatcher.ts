import EventEmitter from 'events';
import fs from 'fs';
import path from 'path';

export class LogWatcher extends EventEmitter {
  private watchers: fs.FSWatcher[] = [];
  private positions: Map<string, number> = new Map();
  private outputPath = './reports/latest-logs.jsonl';

  constructor(public readonly logFiles: string[]) {
    super();
  }

  start(): void {
    fs.mkdirSync(path.dirname(this.outputPath), { recursive: true });
    for (const file of this.logFiles) {
      this.watchFile(file);
    }
  }

  stop(): void {
    for (const watcher of this.watchers) {
      watcher.close();
    }
    this.watchers = [];
  }

  attach(testName: string): void {
    this.on('line', (line: string, source: string) => {
      const entry = { ts: new Date().toISOString(), test: testName, source, line };
      fs.appendFileSync(this.outputPath, JSON.stringify(entry) + '\n');
    });
  }

  private watchFile(file: string): void {
    if (!fs.existsSync(file)) {
      this.emit('warn', `Log file not found: ${file}`);
      return;
    }
    const stat = fs.statSync(file);
    this.positions.set(file, stat.size);

    const watcher = fs.watch(file, (eventType) => {
      if (eventType !== 'change') return;
      const currentSize = fs.statSync(file).size;
      const lastPosition = this.positions.get(file) || 0;
      if (currentSize <= lastPosition) return;

      const stream = fs.createReadStream(file, { start: lastPosition, end: currentSize });
      let remainder = '';
      stream.on('data', (chunk: Buffer | string) => {
        const text = remainder + (Buffer.isBuffer(chunk) ? chunk.toString('utf8') : chunk);
        const lines = text.split('\n');
        remainder = lines.pop() || '';
        for (const line of lines) {
          if (line) this.emit('line', line, file);
        }
      });
      stream.on('end', () => {
        this.positions.set(file, currentSize);
      });
    });

    this.watchers.push(watcher);
  }
}
