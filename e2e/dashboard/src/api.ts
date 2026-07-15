const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:4000/api';

function authHeaders(): Record<string, string> {
  const token = localStorage.getItem('e2e_token');
  return token ? { Authorization: `Bearer ${token}` } : {};
}

export async function login(username: string, password: string): Promise<string> {
  const res = await fetch(`${API_URL}/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ username, password }),
  });
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Login failed');
  return data.token;
}

export async function fetchRuns() {
  const res = await fetch(`${API_URL}/runs`, { headers: authHeaders() });
  return res.json();
}

export async function fetchRun(id: number) {
  const res = await fetch(`${API_URL}/runs/${id}`, { headers: authHeaders() });
  return res.json();
}

export function streamLogs(onMessage: (data: unknown) => void): () => void {
  const es = new EventSource(`${API_URL}/logs/stream`, { withCredentials: true });
  es.onmessage = (event) => onMessage(JSON.parse(event.data));
  return () => es.close();
}
