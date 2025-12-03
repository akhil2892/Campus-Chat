import express from 'express';
import cors from 'cors';
import helmet from 'helmet';
import cookieParser from 'cookie-parser';
import { rateLimit } from 'express-rate-limit';
import path from 'node:path';
import { existsSync } from 'node:fs';
import mongoose from 'mongoose';
import { root, config } from './config.js';
import { requireAuth, originGuard } from './security.js';
import { authRouter } from './routes/auth.js';
import { socialRouter } from './routes/social.js';
import { chatsRouter } from './routes/chats.js';
import { moderationRouter } from './routes/moderation.js';

export function createApp() {
  const app = express();
  app.disable('x-powered-by');
  const proxyHops = Number(process.env.TRUST_PROXY || 0);
  if (Number.isInteger(proxyHops) && proxyHops > 0) app.set('trust proxy', proxyHops);
  app.set('online', new Map());
  app.use(
    helmet({
      contentSecurityPolicy: {
        directives: {
          'img-src': ["'self'", 'data:', 'blob:'],
          'connect-src': ["'self'", 'ws:', 'wss:'],
          'style-src': ["'self'", "'unsafe-inline'"],
        },
      },
    }),
  );
  app.use(
    cors({
      origin: [config.origin, 'http://127.0.0.1:5173'],
      credentials: true,
    }),
  );
  app.use(express.json({ limit: '32kb' }), cookieParser(), originGuard);
  app.get('/api/health', (req, res) =>
    res.status(mongoose.connection.readyState === 1 ? 200 : 503).json({
      status: mongoose.connection.readyState === 1 ? 'ok' : 'unavailable',
      app: 'Campus Chat',
    }),
  );
  app.use('/api/auth', authRouter);
  app.use(
    '/api',
    requireAuth,
    rateLimit({
      windowMs: 60_000,
      limit: 240,
      keyGenerator: (req) => String(req.user._id),
      standardHeaders: 'draft-8',
      legacyHeaders: false,
      skip: () => process.env.NODE_ENV === 'test',
      message: { error: 'Too many requests. Please try again in a minute.' },
    }),
    socialRouter,
    chatsRouter,
    moderationRouter,
  );
  app.use('/api', (req, res) => res.status(404).json({ error: 'Endpoint not found.' }));
  const buildPath = path.join(root, 'client/dist');
  if (existsSync(buildPath)) {
    app.use(express.static(buildPath));
    app.get('/{*path}', (req, res) => res.sendFile(path.join(buildPath, 'index.html')));
  }
  app.use((error, req, res, next) => {
    if (res.headersSent) return next(error);
    let status = error.status || 500;
    let message = error.message;
    if (error.code === 11000) {
      status = 409;
      message = 'This email, roll number, request, section, or report already exists.';
    }
    if (error.name === 'ValidationError' || error.name === 'CastError') {
      status = 400;
      message = 'Please check your input and try again.';
    }
    if (error.code === 'LIMIT_FILE_SIZE') {
      status = 400;
      message = 'Files must be 10 MB or smaller.';
    }
    if (error.name === 'MulterError') {
      status = 400;
      message =
        error.code === 'LIMIT_FILE_SIZE'
          ? 'Files must be 10 MB or smaller.'
          : 'Upload one file at a time.';
    }
    if (status >= 500) {
      console.error(error);
      message = 'Something went wrong. Please try again.';
    }
    res.status(status).json({ error: message });
  });
  return app;
}
