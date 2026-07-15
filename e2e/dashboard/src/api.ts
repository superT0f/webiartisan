export const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:4000/api';

function authHeaders(): Record<string, string> {
  const token = localStorage.getItem('e2e_token');
  return token ? { Authorization: `Bearer ${token}` } : {};
}

async function parseJson<T = any>(res: Response): Promise<T> {
  if (!res.ok) {
    const text = await res.text().catch(() => '');
    throw new Error(text || res.statusText || `Request failed with status ${res.status}`);
  }
  return res.json() as Promise<T>;
}

export async function login(username: string, password: string): Promise<string> {
  const res = await fetch(`${API_URL}/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ username, password }),
  });
  const data = await parseJson<{ token: string }>(res);
  return data.token;
}

export async function fetchRuns() {
  const res = await fetch(`${API_URL}/runs`, { headers: authHeaders() });
  return parseJson(res);
}

export async function fetchRun(id: number) {
  const res = await fetch(`${API_URL}/runs/${id}`, { headers: authHeaders() });
  return parseJson(res);
}

export async function triggerRun(token: string) {
  const res = await fetch(`${API_URL}/runs/trigger`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${token}`,
    },
  });
  await parseJson(res);
}

export function streamLogs(token: string, onMessage: (data: unknown) => void): () => void {
  const es = new EventSource(`${API_URL}/logs/stream?token=${encodeURIComponent(token)}`, {
    withCredentials: false,
  });
  es.onmessage = (event) => onMessage(JSON.parse(event.data));
  return () => es.close();
}
