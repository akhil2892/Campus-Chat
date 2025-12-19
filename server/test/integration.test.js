import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import { mkdtemp, rm } from 'node:fs/promises';
import os from 'node:os';
import { createServer } from 'node:http';
import mongoose from 'mongoose';
import request from 'supertest';
import { MongoMemoryServer } from 'mongodb-memory-server';
import { io as socketClient } from 'socket.io-client';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';

process.env.NODE_ENV = 'test';
process.env.DEMO_MODE = 'true';
const uploads = await mkdtemp(path.join(os.tmpdir(), 'campus-chat-test-'));
process.env.UPLOAD_DIR = uploads;
const { createApp } = await import('../src/app.js');
const { attachRealtime } = await import('../src/realtime.js');
const { seedDemo } = await import('../src/seed.js');
const { User, Conversation, Message, Report } = await import('../src/models.js');
let mongo, app, http, io, origin;
let student, faculty, admin, sophia;
let studentId, sophiaId, sectionId, directId, friendId, testMessage;
before(
  async () => {
    mongo = await MongoMemoryServer.create({
      instance: { launchTimeout: 60_000 },
      binary: {
        version: '8.2.6',
        downloadDir: path.resolve('../../node_modules/.cache/mongodb-memory-server'),
      },
    });
    await mongoose.connect(mongo.getUri());
    await seedDemo('CampusDemo!2026');
    app = createApp();
    http = createServer(app);
    io = attachRealtime(http, app);
    await new Promise((resolve) => http.listen(0, '127.0.0.1', resolve));
    origin = `http://127.0.0.1:${http.address().port}`;
    student = request.agent(app);
    faculty = request.agent(app);
    admin = request.agent(app);
    sophia = request.agent(app);
    studentId = (await student.post('/api/auth/demo').send({ role: 'student' }).expect(200)).body
      ._id;
    await faculty.post('/api/auth/demo').send({ role: 'lecturer' }).expect(200);
    await admin.post('/api/auth/demo').send({ role: 'admin' }).expect(200);
    sophiaId = (
      await sophia
        .post('/api/auth/login')
        .send({ email: 'sophia@college.edu', password: 'CampusDemo!2026' })
        .expect(200)
    ).body._id;
    const conversations = (await student.get('/api/conversations').expect(200)).body;
    sectionId = conversations.find((c) => c.category === 'section')._id;
    directId = conversations.find(
      (c) => c.kind === 'direct' && c.members.some((m) => m._id === sophiaId),
    )._id;
  },
  { timeout: 120_000 },
);
after(async () => {
  await new Promise((resolve) => (io ? io.close(resolve) : resolve()));
  await mongoose.disconnect();
  await mongo?.stop();
  await rm(uploads, { recursive: true, force: true });
});
test('authentication protects APIs, validates credentials and origins, and keeps passwords private', async () => {
  await request(app).get('/api/conversations').expect(401);
  await request(app)
    .post('/api/auth/login')
    .send({ email: 'akhil@college.edu', password: 'incorrect' })
    .expect(401);
  await student
    .patch('/api/users/me')
    .set('Origin', 'https://evil.example')
    .send({ firstName: 'Changed' })
    .expect(403);
  const me = (await student.get('/api/auth/me').expect(200)).body;
  assert.equal(me.firstName, 'Akhil');
  assert.equal(me.passwordHash, undefined);
  const response = await request(app)
    .post('/api/auth/login')
    .send({ email: 'akhil@college.edu', password: 'CampusDemo!2026' })
    .expect(200);
  assert.match(response.headers['set-cookie'][0], /HttpOnly/);
  assert.match(response.headers['set-cookie'][0], /SameSite=Lax/);
});
test('registration validates role and college data, rejects duplicates, and auto-joins exactly one section', async () => {
  const body = {
    firstName: 'New',
    lastName: 'Student',
    email: 'new@college.edu',
    password: 'AstrongPass123!',
    rollNumber: 'NEW100',
    section: 'CSE-A',
    year: 3,
  };
  await request(app)
    .post('/api/auth/register')
    .send({ ...body, role: 'admin' })
    .expect(400);
  await request(app)
    .post('/api/auth/register')
    .send({ ...body, email: 'new@gmail.com' })
    .expect(400);
  await request(app)
    .post('/api/auth/register')
    .send({ ...body, section: 'INVALID' })
    .expect(400);
  await request(app)
    .post('/api/auth/register')
    .send({ ...body, role: 'lecturer' })
    .expect(400);
  const agent = request.agent(app);
  const response = await agent.post('/api/auth/register').send(body).expect(201);
  const groups = (await agent.get('/api/conversations').expect(200)).body;
  assert.equal(groups.length, 1);
  assert.equal(groups[0]._id, sectionId);
  assert.equal(await Conversation.countDocuments({ uniqueKey: 'section:CSE-A:3' }), 1);
  await request(app).post('/api/auth/register').send(body).expect(409);
  await request(app)
    .post('/api/auth/register')
    .send({ ...body, email: 'another@college.edu' })
    .expect(409);
  assert.equal(await User.countDocuments({ _id: response.body._id }), 1);
});
test('friend requests enforce receiver-only acceptance and unlock direct chat', async () => {
  const agent = request.agent(app);
  await agent
    .post('/api/auth/login')
    .send({ email: 'leo@college.edu', password: 'CampusDemo!2026' })
    .expect(200);
  const leo = await User.findOne({ email: 'leo@college.edu' });
  friendId = String(leo._id);
  await student.post('/api/conversations/direct').send({ userId: friendId }).expect(403);
  const incoming = (await student.get('/api/friends').expect(200)).body.find(
    (f) => f.user._id === friendId,
  );
  await agent.patch(`/api/friends/${incoming._id}`).send({ action: 'accept' }).expect(404);
  await student.patch(`/api/friends/${incoming._id}`).send({ action: 'accept' }).expect(200);
  const c = (await student.post('/api/conversations/direct').send({ userId: friendId }).expect(201))
    .body;
  const again = (
    await student.post('/api/conversations/direct').send({ userId: friendId }).expect(201)
  ).body;
  assert.equal(c._id, again._id);
  await student.post('/api/friends').send({ userId: friendId }).expect(409);
  await student.post('/api/friends').send({ userId: studentId }).expect(400);
});
test('room discovery hides message contents until joining, and leaving revokes access', async () => {
  const rooms = (await student.get('/api/discover').expect(200)).body;
  const room = rooms.find((c) => c.name === 'After class');
  assert.equal(room.joined, false);
  assert.equal(room.lastMessage, null);
  await student.get(`/api/conversations/${room._id}/messages`).expect(403);
  await student.post(`/api/conversations/${room._id}/join`).expect(200);
  await student.get(`/api/conversations/${room._id}/messages`).expect(200);
  await student.post(`/api/conversations/${room._id}/leave`).expect(200);
  await student
    .post(`/api/conversations/${room._id}/messages`)
    .field('text', 'Must fail')
    .expect(403);
});
test('section and private group membership reject unrelated students; only creators delete groups', async () => {
  const otherSection = await Conversation.findOne({ section: 'CSE-B' });
  await student.post(`/api/conversations/${otherSection._id}/join`).expect(403);
  await student
    .post('/api/conversations')
    .send({
      name: 'Invalid section',
      kind: 'group',
      category: 'section',
      section: 'CSE-A',
      year: 2,
    })
    .expect(403);
  const group = (
    await student
      .post('/api/conversations')
      .send({
        name: 'Test private group',
        kind: 'group',
        memberIds: [sophiaId],
        topic: 'Projects',
      })
      .expect(201)
  ).body;
  await faculty.post(`/api/conversations/${group._id}/join`).expect(403);
  await sophia.delete(`/api/conversations/${group._id}`).expect(403);
  await student.delete(`/api/conversations/${group._id}`).expect(200);
  await sophia.get(`/api/conversations/${group._id}/messages`).expect(404);
  await student.delete(`/api/conversations/${sectionId}`).expect(403);
});
test('messages enforce input bounds, redact anonymous senders, and persist read receipts', async () => {
  await student.post(`/api/conversations/${directId}/messages`).field('text', '').expect(400);
  await student
    .post(`/api/conversations/${directId}/messages`)
    .field('text', 'x'.repeat(1001))
    .expect(400);
  const response = await student
    .post(`/api/conversations/${directId}/messages`)
    .field('text', 'An anonymous test thought')
    .field('anonymous', 'true')
    .expect(201);
  testMessage = response.body._id;
  assert.equal(response.body.mine, true);
  assert.equal(response.body.sender._id, studentId);
  const messages = (await sophia.get(`/api/conversations/${directId}/messages`).expect(200)).body
    .messages;
  const anonymous = messages.find((m) => m._id === testMessage);
  assert.equal(anonymous.sender.firstName, 'Anonymous');
  assert.equal(anonymous.sender._id, undefined);
  assert.equal(anonymous.sender.email, undefined);
  await sophia.post(`/api/conversations/${directId}/read`).expect(200);
  const own = (
    await student.get(`/api/conversations/${directId}/messages`).expect(200)
  ).body.messages.find((m) => m._id === testMessage);
  assert.equal(own.readCount, 1);
});
test('attachment downloads require membership and reject executable files disguised as images', async () => {
  await student
    .post(`/api/conversations/${directId}/messages`)
    .attach('file', Buffer.from('<?php echo 1; ?>'), {
      filename: 'fake.png',
      contentType: 'image/png',
    })
    .expect(400);
  const response = await student
    .post(`/api/conversations/${directId}/messages`)
    .field('text', 'A small note')
    .attach('file', Buffer.from('Campus project notes'), {
      filename: 'notes.txt',
      contentType: 'text/plain',
    })
    .expect(201);
  await student.get(response.body.attachment.url).expect(200);
  await faculty.get(response.body.attachment.url).expect(403);
  await request(app).get(response.body.attachment.url).expect(401);
});
test('reporting validates faculty and membership, prevents duplicates, and reveals anonymous identity only to moderators', async () => {
  const lecturer = await User.findOne({ role: 'lecturer' });
  const body = {
    messageId: testMessage,
    facultyId: String(lecturer._id),
    reason: 'Other',
    details: 'Integration test',
  };
  await faculty.post('/api/reports').send(body).expect(403);
  await student.post('/api/reports').send(body).expect(400);
  const report = (await sophia.post('/api/reports').send(body).expect(201)).body;
  await sophia.post('/api/reports').send(body).expect(409);
  await student.get('/api/reports').expect(403);
  const reports = (await faculty.get('/api/reports').expect(200)).body;
  assert.equal(reports.find((r) => r._id === report._id).message.sender._id, studentId);
  await student.patch(`/api/reports/${report._id}`).send({ status: 'reviewed' }).expect(403);
  await faculty.patch(`/api/reports/${report._id}`).send({ status: 'reviewed' }).expect(200);
  assert.equal((await Report.findById(report._id)).status, 'reviewed');
});
test('admin sections validate uniqueness, protect codes in use, toggle availability, and audit changes', async () => {
  await student.get('/api/admin').expect(403);
  await faculty.get('/api/admin/sections').expect(403);
  const section = (
    await admin
      .post('/api/admin/sections')
      .send({ code: 'NEW-A', name: 'New section A', department: 'Engineering' })
      .expect(201)
  ).body;
  await admin
    .post('/api/admin/sections')
    .send({ code: 'NEW-A', name: 'Duplicate', department: 'Engineering' })
    .expect(409);
  await admin.patch(`/api/admin/sections/${section._id}`).send({ active: false }).expect(200);
  assert.ok(
    !(await request(app).get('/api/auth/sections').expect(200)).body.some(
      (s) => s.code === 'NEW-A',
    ),
  );
  const used = (await admin.get('/api/admin/sections').expect(200)).body.find(
    (s) => s.code === 'CSE-A',
  );
  await admin.patch(`/api/admin/sections/${used._id}`).send({ code: 'RENAMED' }).expect(409);
  const overview = (await admin.get('/api/admin').expect(200)).body;
  assert.ok(overview.logs.some((log) => log.action === 'Created section'));
  assert.ok(overview.stats.messages > 0);
});
test('pagination returns distinct pages in chronological order', async () => {
  await Message.insertMany(
    Array.from({ length: 55 }, (_, i) => ({
      conversation: directId,
      sender: studentId,
      text: `Pagination ${i}`,
      createdAt: new Date(Date.now() + i),
    })),
  );
  const recent = (await student.get(`/api/conversations/${directId}/messages`).expect(200)).body;
  assert.equal(recent.messages.length, 50);
  assert.equal(recent.hasMore, true);
  const older = (
    await student
      .get(`/api/conversations/${directId}/messages?before=${recent.messages[0]._id}`)
      .expect(200)
  ).body;
  assert.ok(older.messages.length > 0);
  const recentIds = new Set(recent.messages.map((m) => m._id));
  assert.ok(older.messages.every((m) => !recentIds.has(m._id)));
});
test('Socket.IO rejects unauthenticated clients and sends recipient-specific anonymous messages live', async () => {
  const noAuth = socketClient(origin, {
    reconnection: false,
    transports: ['websocket'],
  });
  await new Promise((resolve, reject) => {
    noAuth.on('connect_error', resolve);
    noAuth.on('connect', () => reject(new Error('Anonymous socket connected')));
  });
  noAuth.disconnect();
  const login = await request(app)
    .post('/api/auth/login')
    .send({ email: 'sophia@college.edu', password: 'CampusDemo!2026' })
    .expect(200);
  const peer = socketClient(origin, {
    reconnection: false,
    transports: ['websocket'],
    extraHeaders: { Cookie: login.headers['set-cookie'][0].split(';')[0] },
  });
  await new Promise((resolve, reject) => {
    peer.on('connect', resolve);
    peer.on('connect_error', reject);
  });
  const event = new Promise((resolve, reject) => {
    const timer = setTimeout(() => reject(new Error('Live message timeout')), 5000);
    peer.once('message:new', (message) => {
      clearTimeout(timer);
      resolve(message);
    });
  });
  await student
    .post(`/api/conversations/${directId}/messages`)
    .field('text', 'Socket privacy test')
    .field('anonymous', 'true')
    .expect(201);
  const message = await event;
  assert.equal(message.text, 'Socket privacy test');
  assert.equal(message.sender._id, undefined);
  assert.equal(message.sender.firstName, 'Anonymous');
  peer.disconnect();
});
test('profile updates cannot promote roles, passwords revoke other sessions, and logout revokes cookies', async () => {
  const agent = request.agent(app);
  await agent
    .post('/api/auth/login')
    .send({ email: 'noor@college.edu', password: 'CampusDemo!2026' })
    .expect(200);
  await agent.patch('/api/users/me').send({ bio: 'A new bio', role: 'admin' }).expect(200);
  assert.equal((await agent.get('/api/auth/me')).body.role, 'student');
  const second = request.agent(app);
  await second
    .post('/api/auth/login')
    .send({ email: 'noor@college.edu', password: 'CampusDemo!2026' })
    .expect(200);
  await agent
    .patch('/api/users/me/password')
    .send({ currentPassword: 'wrong', password: 'NewStrongPass!' })
    .expect(400);
  await agent
    .patch('/api/users/me/password')
    .send({ currentPassword: 'CampusDemo!2026', password: 'NewStrongPass!' })
    .expect(200);
  await second.get('/api/auth/me').expect(401);
  await agent.post('/api/auth/logout').expect(200);
  await agent.get('/api/auth/me').expect(401);
});
test('fresh-campus admin setup creates a new administrator without changing existing users', async () => {
  const run = promisify(execFile);
  const env = {
    ...process.env,
    MONGODB_URI: mongo.getUri(),
    ADMIN_EMAIL: 'setupadmin@college.edu',
    ADMIN_PASSWORD: 'SetupAdminStrongPass!',
  };
  const result = await run(process.execPath, [path.resolve('src/admin.js')], { env });
  assert.match(result.stdout, /Administrator setupadmin@college.edu created/);
  const created = await User.findOne({ email: 'setupadmin@college.edu' });
  assert.equal(created.role, 'admin');
  await assert.rejects(
    run(process.execPath, [path.resolve('src/admin.js')], { env }),
    /Command failed/,
  );
  assert.equal(await User.countDocuments({ email: 'setupadmin@college.edu' }), 1);
  await assert.rejects(
    run(process.execPath, [path.resolve('src/admin.js')], {
      env: { ...env, ADMIN_EMAIL: 'sophia@college.edu' },
    }),
    /Command failed/,
  );
  assert.equal((await User.findById(sophiaId)).role, 'student');
});
test('avatar uploads validate images and update the URL so a saved photo displays immediately', async () => {
  const png = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
    'base64',
  );
  const first = (
    await student
      .post('/api/users/me/avatar')
      .attach('file', png, { filename: 'avatar.png', contentType: 'image/png' })
      .expect(200)
  ).body;
  assert.match(first.avatar, /avatar\?v=/);
  await student
    .get(first.avatar)
    .expect('Content-Type', /image\/png/)
    .expect(200);
  const second = (
    await student
      .post('/api/users/me/avatar')
      .attach('file', png, { filename: 'avatar.png', contentType: 'image/png' })
      .expect(200)
  ).body;
  assert.notEqual(first.avatar, second.avatar);
  await student
    .post('/api/users/me/avatar')
    .attach('file', Buffer.from('Not an image'), { filename: 'fake.png', contentType: 'image/png' })
    .expect(400);
  await student
    .post('/api/users/me/avatar')
    .attach('file', Buffer.alloc(5 * 1024 * 1024 + 1), {
      filename: 'large.png',
      contentType: 'image/png',
    })
    .expect(400);
  await request(app).get(second.avatar).expect(401);
});
