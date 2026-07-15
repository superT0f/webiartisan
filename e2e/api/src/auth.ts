import { Request, Response, NextFunction } from 'express';
import jwt from 'jsonwebtoken';
import { env } from '../../src/config/env';

export interface AuthRequest extends Request {
  user?: { username: string };
}

export function generateToken(username: string): string {
  return jwt.sign({ username }, env.jwtSecret, { expiresIn: '8h' });
}

export function authMiddleware(req: AuthRequest, res: Response, next: NextFunction): void {
  const header = req.headers.authorization || '';
  const token = header.replace(/^Bearer\s+/, '');
  if (!token) {
    res.status(401).json({ error: 'Missing token' });
    return;
  }
  try {
    req.user = jwt.verify(token, env.jwtSecret) as { username: string };
    next();
  } catch {
    res.status(401).json({ error: 'Invalid token' });
  }
}
