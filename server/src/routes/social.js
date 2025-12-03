import { Router } from 'express';
import { z } from 'zod';
import path from 'node:path';
import bcrypt from 'bcryptjs';
import { User, Friendship, Session } from '../models.js';
import { fail, validate, name, id, pairKey, publicUser, objectId } from '../security.js';
import { upload, saveUpload, removeUpload } from '../uploads.js';
import { config } from '../config.js';
import { notifyUsers } from '../realtime.js';

export const socialRouter = Router();
socialRouter.get('/users', async (req, res) => {
  const q = String(req.query.q || '')
    .slice(0, 80)
    .replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const users = await User.find({
    active: true,
    _id: { $ne: req.user._id },
    ...(q
      ? {
          $or: [
            { firstName: { $regex: q, $options: 'i' } },
            { lastName: { $regex: q, $options: 'i' } },
            { email: { $regex: q, $options: 'i' } },
            {
              $expr: {
                $regexMatch: {
                  input: { $concat: ['$firstName', ' ', '$lastName'] },
                  regex: q,
                  options: 'i',
                },
              },
            },
          ],
        }
      : {}),
  })
    .sort({ firstName: 1 })
    .limit(100);
  const friendships = await Friendship.find({
    $or: [{ sender: req.user._id }, { receiver: req.user._id }],
  });
  const online = req.app.get('online');
  res.json(
    users.map((user) => {
      const friendship = friendships.find((f) => f.pairKey === pairKey(req.user._id, user._id));
      return {
        ...publicUser(user),
        online: online.has(String(user._id)),
        friendshipId: friendship?._id,
        friendship:
          friendship?.status === 'accepted'
            ? 'friends'
            : friendship?.status === 'pending'
              ? String(friendship.sender) === String(req.user._id)
                ? 'sent'
                : 'received'
              : 'none',
      };
    }),
  );
});
socialRouter.get('/users/:id/avatar', async (req, res) => {
  const user = await User.findById(id(req.params.id));
  if (!user?.avatar) fail(404, 'Profile image not found.');
  res.set('Cache-Control', 'private, max-age=300');
  res.sendFile(path.join(config.uploads, path.basename(user.avatar)));
});
socialRouter.patch(
  '/users/me',
  validate(
    z.object({
      firstName: name.optional(),
      lastName: name.optional(),
      bio: z.string().trim().max(240).optional(),
      anonymous: z.boolean().optional(),
      color: z.enum(['sage', 'peach', 'lavender', 'blue', 'gold']).optional(),
    }),
  ),
  async (req, res) => {
    Object.assign(req.user, req.body);
    await req.user.save();
    res.json(publicUser(req.user));
  },
);
socialRouter.post('/users/me/avatar', upload.single('file'), async (req, res) => {
  if (!req.file) fail(400, 'Choose an image first.');
  const saved = await saveUpload(req.file, true);
  const old = req.user.avatar;
  req.user.avatar = saved.filename;
  try {
    await req.user.save();
  } catch (error) {
    await removeUpload(saved.filename);
    throw error;
  }
  await removeUpload(old);
  res.json(publicUser(req.user));
});
socialRouter.patch(
  '/users/me/password',
  validate(
    z.object({
      currentPassword: z.string().min(1).max(200),
      password: z
        .string()
        .min(8)
        .max(72)
        .refine((v) => Buffer.byteLength(v, 'utf8') <= 72),
    }),
  ),
  async (req, res) => {
    const user = await User.findById(req.user._id).select('+passwordHash');
    if (!(await bcrypt.compare(req.body.currentPassword, user.passwordHash)))
      fail(400, 'Current password is incorrect.');
    user.passwordHash = await bcrypt.hash(req.body.password, 12);
    await user.save();
    await Session.deleteMany({ user: user._id, _id: { $ne: req.session._id } });
    res.json({ success: true });
  },
);
socialRouter.get('/friends', async (req, res) => {
  const friendships = await Friendship.find({
    status: { $in: ['pending', 'accepted'] },
    $or: [{ sender: req.user._id }, { receiver: req.user._id }],
  })
    .populate('sender receiver')
    .sort({ createdAt: -1 });
  res.json(
    friendships
      .filter((f) => f.sender?.active && f.receiver?.active)
      .map((f) => ({
        _id: f._id,
        status: f.status,
        incoming: String(f.receiver._id) === String(req.user._id),
        user: publicUser(String(f.sender._id) === String(req.user._id) ? f.receiver : f.sender),
        createdAt: f.createdAt,
      })),
  );
});
socialRouter.post('/friends', validate(z.object({ userId: objectId })), async (req, res) => {
  const other = await User.findById(req.body.userId);
  if (!other?.active) fail(404, 'Student not found.');
  if (String(other._id) === String(req.user._id)) fail(400, 'You cannot send yourself a request.');
  const key = pairKey(req.user._id, other._id);
  const existing = await Friendship.findOne({ pairKey: key });
  if (existing && existing.status !== 'rejected')
    fail(409, 'A friendship or request already exists.');
  const friendship = await Friendship.findOneAndUpdate(
    { pairKey: key, status: 'rejected' },
    {
      $set: { sender: req.user._id, receiver: other._id, status: 'pending' },
    },
    { upsert: true, returnDocument: 'after' },
  );
  notifyUsers(req.app.get('io'), [req.user._id, other._id], 'data:refresh');
  res.status(201).json({ _id: friendship._id });
});
socialRouter.patch(
  '/friends/:id',
  validate(z.object({ action: z.enum(['accept', 'reject']) })),
  async (req, res) => {
    const friendship = await Friendship.findOneAndUpdate(
      { _id: id(req.params.id), receiver: req.user._id, status: 'pending' },
      {
        $set: {
          status: req.body.action === 'accept' ? 'accepted' : 'rejected',
        },
      },
      { returnDocument: 'after' },
    );
    if (!friendship) fail(404, 'Friend request not found.');
    notifyUsers(req.app.get('io'), [friendship.sender, friendship.receiver], 'data:refresh');
    res.json({ success: true });
  },
);
