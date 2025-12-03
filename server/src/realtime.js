import { Server } from 'socket.io';
import * as cookie from 'cookie';
import { config } from './config.js';
import { authenticate } from './security.js';
import { requireConversation, serializeMessage } from './conversations.js';
import { User } from './models.js';

export function notifyUsers(io, users, event, data = {}) {
  for (const user of users) io?.to(`user:${user._id || user}`).emit(event, data);
}
export async function publishMessage(io, message, conversation) {
  const users = await User.find({
    _id: { $in: conversation.members },
    active: true,
  });
  for (const user of users)
    io?.to(`user:${user._id}`).emit('message:new', serializeMessage(message, user, conversation));
}
export function attachRealtime(httpServer, app) {
  const online = new Map();
  const io = new Server(httpServer, {
    cors: {
      origin: [config.origin, 'http://127.0.0.1:5173'],
      credentials: true,
    },
    allowRequest: (req, done) =>
      done(
        null,
        !req.headers.origin ||
          [config.origin, 'http://127.0.0.1:5173'].includes(req.headers.origin),
      ),
    maxHttpBufferSize: 10_000,
  });
  app.set('io', io);
  app.set('online', online);
  io.use(async (socket, next) => {
    try {
      const token = cookie.parse(socket.request.headers.cookie || '')[config.cookieName];
      const session = await authenticate(token);
      if (!session) return next(new Error('Please sign in.'));
      socket.data.user = session.user;
      socket.data.sessionId = session._id;
      socket.data.token = token;
      next();
    } catch {
      next(new Error('Unable to authenticate.'));
    }
  });
  io.on('connection', (socket) => {
    const uid = String(socket.data.user._id);
    socket.join(`user:${uid}`);
    online.set(uid, (online.get(uid) || 0) + 1);
    io.emit('presence', [...online.keys()]);
    let lastTyping = 0;
    socket.on('typing', async (payload, acknowledge = () => {}) => {
      const reply = typeof acknowledge === 'function' ? acknowledge : () => {};
      if (Date.now() - lastTyping < 700) return;
      lastTyping = Date.now();
      try {
        const session = await authenticate(socket.data.token);
        if (!session) return socket.disconnect(true);
        const conversation = await requireConversation(payload?.conversationId, session.user);
        const name = session.user.anonymous ? 'Someone' : session.user.firstName;
        notifyUsers(
          io,
          conversation.members.filter((member) => String(member) !== uid),
          'typing',
          {
            conversationId: String(conversation._id),
            name,
            active: Boolean(payload?.active),
          },
        );
        reply({ success: true });
      } catch {
        reply({ error: 'Conversation unavailable.' });
      }
    });
    const expiration = setInterval(async () => {
      try {
        if (!(await authenticate(socket.data.token))) socket.disconnect(true);
      } catch {
        socket.disconnect(true);
      }
    }, 60_000);
    expiration.unref();
    socket.on('disconnect', () => {
      clearInterval(expiration);
      const count = (online.get(uid) || 1) - 1;
      if (count) online.set(uid, count);
      else online.delete(uid);
      io.emit('presence', [...online.keys()]);
    });
  });
  return io;
}
