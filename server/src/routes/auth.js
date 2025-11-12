import { Router } from 'express';
import { z } from 'zod';
import bcrypt from 'bcryptjs';
import { rateLimit } from 'express-rate-limit';
import { User, Section, Session } from '../models.js';
import { config } from '../config.js';
import { validate, name, fail, requireAuth, createSession, publicUser, cookieOptions } from '../security.js';
import { autoSection } from '../conversations.js';

export const authRouter = Router();
const limiter = rateLimit({ windowMs: 15 * 60 * 1000, limit: 30, standardHeaders: 'draft-8', legacyHeaders: false, message: { error: 'Too many sign-in attempts. Please try again later.' } });
const password = z.string().min(8, 'Use at least 8 characters for your password.').max(72).refine(v => Buffer.byteLength(v, 'utf8') <= 72, 'Password is too long.');
const email = z.email().max(200).transform(s => s.toLowerCase());
authRouter.get('/config', (req, res) => res.json({ demo: !config.production && process.env.DEMO_MODE === 'true', domains: config.domains }));
authRouter.post('/demo', limiter, validate(z.object({ role: z.enum(['student', 'lecturer', 'admin']).default('student') })), async (req, res) => {
  if (config.production || process.env.DEMO_MODE !== 'true') fail(404, 'Demo sign-in is not enabled.');
  const demoEmail = { student: 'akhil@college.edu', lecturer: 'evelyn@faculty.college.edu', admin: 'admin@college.edu' }[req.body.role];
  const user = await User.findOne({ email: demoEmail, active: true });
  if (!user) fail(404, 'Seed the demo campus first.');
  await createSession(user, res);
  res.json(publicUser(user));
});
authRouter.get('/sections', async (req, res) => res.json(await Section.find({ active: true }).sort({ code: 1 })));
authRouter.post('/register', limiter, validate(z.object({
  firstName: name, lastName: name, email, password,
  role: z.enum(['student', 'lecturer']).default('student'),
  rollNumber: z.string().trim().max(30).optional(), section: z.string().trim().max(15).optional(),
  year: z.coerce.number().int().min(1).max(4).optional(),
})), async (req, res) => {
  const body = req.body;
  const domain = body.email.split('@')[1];
  if (!config.domains.includes(domain)) fail(400, `Use a college email ending in ${config.domains.join(', ')}.`);
  if (body.role === 'lecturer' && !domain.startsWith('faculty.')) fail(400, 'Lecturers must use an allowed faculty email domain.');
  if (body.role === 'student') {
    if (!body.rollNumber || !body.section || !body.year) fail(400, 'Roll number, section, and year are required for students.');
    body.section = body.section.toUpperCase();
    if (!await Section.exists({ code: body.section, active: true })) fail(400, 'Choose an active section.');
  } else {
    delete body.rollNumber; delete body.section; delete body.year;
  }
  const user = await User.create({ ...body, passwordHash: await bcrypt.hash(body.password, 12) });
  try { await autoSection(user); } catch (error) { await User.deleteOne({ _id: user._id }); throw error; }
  await createSession(user, res);
  res.status(201).json(publicUser(user));
});
authRouter.post('/login', limiter, validate(z.object({ email, password: z.string().min(1).max(200) })), async (req, res) => {
  const user = await User.findOne({ email: req.body.email, active: true }).select('+passwordHash');
  if (!user || !await bcrypt.compare(req.body.password, user.passwordHash)) fail(401, 'Email or password is incorrect.');
  await createSession(user, res);
  res.json(publicUser(user));
});
authRouter.get('/me', requireAuth, (req, res) => res.json(publicUser(req.user)));
authRouter.post('/logout', requireAuth, async (req, res) => {
  await Session.deleteOne({ _id: req.session._id });
  for (const socket of req.app.get('io')?.sockets.sockets.values() || []) {
    if (String(socket.data.sessionId) === String(req.session._id)) socket.disconnect(true);
  }
  res.clearCookie(config.cookieName, cookieOptions).json({ success: true });
});
