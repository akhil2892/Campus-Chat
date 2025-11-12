import dotenv from 'dotenv';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

export const root = fileURLToPath(new URL('../../..', import.meta.url));
dotenv.config({ path: path.join(root, '.env'), quiet: true });
export const config = {
  port: Number(process.env.PORT || 4000),
  mongoUri: process.env.MONGODB_URI || 'mongodb://127.0.0.1:27017/campus_chat',
  production: process.env.NODE_ENV === 'production',
  origin: process.env.CLIENT_ORIGIN || 'http://localhost:5173',
  domains: (process.env.ALLOWED_EMAIL_DOMAINS || 'college.edu,university.ac.in,students.college.edu,faculty.college.edu').split(',').map(s => s.trim().toLowerCase()).filter(Boolean),
  uploads: process.env.UPLOAD_DIR || path.join(root, 'server/uploads'),
  cookieName: 'campus_session',
  sessionMs: 7 * 24 * 60 * 60 * 1000,
};
