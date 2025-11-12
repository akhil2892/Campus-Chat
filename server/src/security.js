import { randomBytes, createHash } from 'node:crypto';
import mongoose from 'mongoose';
import { z } from 'zod';
import { Session } from './models.js';
import { config } from './config.js';

export class ApiError extends Error {
  constructor(status, message) { super(message); this.status = status; }
}
export const fail = (status, message) => { throw new ApiError(status, message); };
export const id = value => {
  if (!mongoose.isObjectIdOrHexString(value)) fail(400, 'Invalid identifier.');
  return String(value);
};
export const pairKey = (a, b) => [String(a), String(b)].sort().join(':');
const hash = token => createHash('sha256').update(token).digest('hex');
export const cookieOptions = { httpOnly: true, secure: config.production, sameSite: 'lax', path: '/' };
export async function createSession(user, res) {
  const token = randomBytes(32).toString('hex');
  await Session.create({ tokenHash: hash(token), user: user._id, expiresAt: new Date(Date.now() + config.sessionMs) });
  res.cookie(config.cookieName, token, { ...cookieOptions, maxAge: config.sessionMs });
}
export async function authenticate(token) {
  if (typeof token !== 'string' || !/^[a-f0-9]{64}$/.test(token)) return null;
  const session = await Session.findOne({ tokenHash: hash(token), expiresAt: { $gt: new Date() } }).populate('user');
  return session?.user?.active ? session : null;
}
export async function requireAuth(req, res, next) {
  try {
    const session = await authenticate(req.cookies[config.cookieName]);
    if (!session) fail(401, 'Please sign in to continue.');
    req.user = session.user;
    req.session = session;
    next();
  } catch (error) { next(error); }
}
export const roles = (...allowed) => (req, res, next) => allowed.includes(req.user.role) ? next() : next(new ApiError(403, 'You do not have access to this action.'));
export const validate = schema => (req, res, next) => {
  const parsed = schema.safeParse(req.body);
  if (!parsed.success) return next(new ApiError(400, parsed.error.issues[0].message));
  req.body = parsed.data;
  next();
};
export const name = z.string().trim().min(1, 'A name is required.').max(50);
export const objectId = z.string().refine(v => mongoose.isObjectIdOrHexString(v), 'Invalid identifier.');
export function publicUser(user) {
  return {
    _id: String(user._id), firstName: user.firstName, lastName: user.lastName,
    email: user.email, role: user.role, section: user.section, year: user.year,
    rollNumber: user.rollNumber, bio: user.bio, avatar: user.avatar ? `/api/users/${user._id}/avatar` : null,
    color: user.color, anonymous: user.anonymous, createdAt: user.createdAt,
  };
}
export function originGuard(req, res, next) {
  if (!['GET', 'HEAD', 'OPTIONS'].includes(req.method)) {
    const allowed = new Set([config.origin, 'http://127.0.0.1:5173']);
    if (req.headers.origin && !allowed.has(req.headers.origin)) return next(new ApiError(403, 'Request origin is not allowed.'));
    if (req.headers['sec-fetch-site'] === 'cross-site') return next(new ApiError(403, 'Cross-site request is not allowed.'));
  }
  next();
}
