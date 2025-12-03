import { MongoMemoryServer } from 'mongodb-memory-server';
import mongoose from 'mongoose';
import { spawn } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('..', import.meta.url));
const dbPath = path.join(root, '.runtime/mongodb');
mkdirSync(dbPath, { recursive: true });
console.log(
  'Starting local MongoDB demo. The first run downloads MongoDB; demo data persists in .runtime/mongodb.',
);
const mongo = await MongoMemoryServer.create({
  binary: {
    version: '8.2.6',
    downloadDir: path.join(root, 'node_modules/.cache/mongodb-memory-server'),
  },
  instance: {
    dbPath,
    storageEngine: 'wiredTiger',
    dbName: 'campus_chat_demo',
    launchTimeout: 60_000,
  },
});
process.env.MONGODB_URI = mongo.getUri('campus_chat_demo');
process.env.DEMO_MODE = 'true';
process.env.NODE_ENV = 'development';
process.env.DEMO_PASSWORD ||= 'CampusDemo!2026';
const { seedDemo } = await import('../server/src/seed.js');
await mongoose.connect(process.env.MONGODB_URI);
await seedDemo();
await mongoose.disconnect();
const run = (file, args) =>
  spawn(process.execPath, [file, ...args], {
    cwd: root,
    stdio: 'inherit',
    env: process.env,
  });
const api = run('server/src/index.js', []);
const web = run('node_modules/vite/bin/vite.js', [
  '--config',
  'client/vite.config.js',
  '--host',
  '127.0.0.1',
  'client',
]);
console.log('\nOpen http://localhost:5173 — Demo password: ' + process.env.DEMO_PASSWORD);
let closing = false;
const close = async () => {
  if (closing) return;
  closing = true;
  api.kill();
  web.kill();
  await mongo.stop({ doCleanup: false });
  process.exit(0);
};
process.on('SIGINT', close);
process.on('SIGTERM', close);
api.on('exit', close);
web.on('exit', close);
