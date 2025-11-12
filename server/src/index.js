import mongoose from 'mongoose';
import { createServer } from 'node:http';
import { createApp } from './app.js';
import { config } from './config.js';
import { attachRealtime } from './realtime.js';

try {
  await mongoose.connect(config.mongoUri, { serverSelectionTimeoutMS: 10_000 });
  const app = createApp();
  const server = createServer(app);
  const io = attachRealtime(server, app);
  server.listen(config.port, '127.0.0.1', () => console.log(`Campus Chat API: http://localhost:${config.port}`));
  const shutdown = async () => { io.close(); server.close(); await mongoose.disconnect(); process.exit(0); };
  process.on('SIGINT', shutdown); process.on('SIGTERM', shutdown);
  server.on('error', error => { console.error(error.message); process.exit(1); });
} catch (error) {
  console.error(`Unable to start Campus Chat: ${error.message}\nStart MongoDB or set MONGODB_URI in .env. For a local demo, run npm run demo.`);
  process.exit(1);
}
