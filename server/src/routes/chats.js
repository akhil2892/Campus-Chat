import { Router } from 'express';
import { z } from 'zod';
import path from 'node:path';
import { Conversation, Friendship, Message, User, Section } from '../models.js';
import { fail, validate, id, objectId, pairKey } from '../security.js';
import { requireConversation, serializeConversation, serializeMessage } from '../conversations.js';
import { upload, saveUpload, removeUpload } from '../uploads.js';
import { config } from '../config.js';
import { notifyUsers, publishMessage } from '../realtime.js';

export const chatsRouter = Router();
chatsRouter.get('/conversations', async (req, res) => {
  const conversations = await Conversation.find({
    active: true,
    members: req.user._id,
  }).sort({ lastMessageAt: -1 });
  res.json(await Promise.all(conversations.map((c) => serializeConversation(c, req.user))));
});
chatsRouter.get('/discover', async (req, res) => {
  const eligibleSection =
    req.user.role === 'student' ? { section: req.user.section, year: req.user.year } : {};
  const conversations = await Conversation.find({
    active: true,
    kind: { $ne: 'direct' },
    $or: [
      { kind: 'room' },
      { category: 'section', ...eligibleSection },
      { invited: req.user._id },
      { members: req.user._id },
    ],
  })
    .sort({ createdAt: -1 })
    .limit(100);
  res.json(await Promise.all(conversations.map((c) => serializeConversation(c, req.user))));
});
chatsRouter.post(
  '/conversations/direct',
  validate(z.object({ userId: objectId })),
  async (req, res) => {
    if (req.body.userId === String(req.user._id)) fail(400, 'Choose a friend to message.');
    const other = await User.findById(req.body.userId);
    if (!other?.active) fail(404, 'Friend not found.');
    const key = pairKey(req.user._id, other._id);
    if (!(await Friendship.exists({ pairKey: key, status: 'accepted' })))
      fail(403, 'Add this person as a friend before messaging.');
    const conversation = await Conversation.findOneAndUpdate(
      { uniqueKey: `direct:${key}` },
      {
        $setOnInsert: {
          name: 'Direct message',
          kind: 'direct',
          creator: req.user._id,
          members: [req.user._id, other._id],
        },
      },
      { returnDocument: 'after', upsert: true, setDefaultsOnInsert: true },
    );
    notifyUsers(req.app.get('io'), conversation.members, 'data:refresh');
    res.status(201).json(await serializeConversation(conversation, req.user));
  },
);
chatsRouter.post(
  '/conversations',
  validate(
    z.object({
      name: z.string().trim().min(2, 'Use at least two characters for the name.').max(80),
      description: z.string().trim().max(500).default(''),
      kind: z.enum(['group', 'room']),
      category: z.enum(['section', 'interest', 'general']).default('interest'),
      topic: z.string().trim().max(40).default('Campus life'),
      color: z.enum(['sage', 'peach', 'lavender', 'blue', 'gold']).default('sage'),
      memberIds: z.array(objectId).max(50).default([]),
      section: z.string().trim().max(15).optional(),
      year: z.coerce.number().int().min(1).max(4).optional(),
    }),
  ),
  async (req, res) => {
    const body = req.body;
    const memberIds = [...new Set(body.memberIds)].filter(
      (member) => member !== String(req.user._id),
    );
    const members = [req.user._id];
    const data = { ...body, creator: req.user._id };
    delete data.memberIds;
    if (body.kind === 'room') {
      data.category = 'general';
      delete data.section;
      delete data.year;
    } else if (body.category === 'section') {
      if (!['admin', 'lecturer'].includes(req.user.role))
        fail(403, 'Only faculty or admins can create section groups.');
      if (!body.section || !body.year) fail(400, 'Choose a section and year.');
      data.section = body.section.toUpperCase();
      if (!(await Section.exists({ code: data.section, active: true })))
        fail(400, 'Choose an active section.');
      data.uniqueKey = `section:${data.section}:${body.year}`;
      const students = await User.find({
        role: 'student',
        section: data.section,
        year: body.year,
        active: true,
      }).select('_id');
      members.push(...students.map((u) => u._id));
    } else {
      delete data.section;
      delete data.year;
      for (const memberId of memberIds) {
        if (
          !(await User.exists({ _id: memberId, active: true })) ||
          !(await Friendship.exists({
            pairKey: pairKey(req.user._id, memberId),
            status: 'accepted',
          }))
        )
          fail(403, 'Only accepted friends can be added to a private group.');
      }
      members.push(...memberIds);
    }
    const conversation = await Conversation.create({
      ...data,
      members,
      invited: members,
    });
    notifyUsers(req.app.get('io'), members, 'data:refresh');
    res.status(201).json(await serializeConversation(conversation, req.user));
  },
);
chatsRouter.post('/conversations/:id/join', async (req, res) => {
  const conversation = await Conversation.findById(id(req.params.id));
  if (!conversation?.active || conversation.kind === 'direct') fail(404, 'Community not found.');
  if (
    conversation.category === 'section' &&
    req.user.role === 'student' &&
    (conversation.section !== req.user.section || conversation.year !== req.user.year)
  )
    fail(403, 'This group belongs to another section.');
  if (
    conversation.kind === 'group' &&
    conversation.category !== 'section' &&
    !conversation.invited.some((member) => String(member) === String(req.user._id))
  )
    fail(403, 'An invitation is required for this group.');
  await Conversation.updateOne({ _id: conversation._id }, { $addToSet: { members: req.user._id } });
  notifyUsers(req.app.get('io'), [...conversation.members, req.user._id], 'data:refresh');
  res.json({ success: true });
});
chatsRouter.post('/conversations/:id/leave', async (req, res) => {
  const conversation = await requireConversation(req.params.id, req.user);
  if (conversation.kind === 'direct') fail(400, 'Direct conversations cannot be left.');
  await Conversation.updateOne({ _id: conversation._id }, { $pull: { members: req.user._id } });
  notifyUsers(req.app.get('io'), conversation.members, 'data:refresh');
  res.json({ success: true });
});
chatsRouter.delete('/conversations/:id', async (req, res) => {
  const conversation = await requireConversation(req.params.id, req.user);
  if (conversation.kind === 'direct' || conversation.category === 'section')
    fail(403, 'This conversation cannot be deleted.');
  if (String(conversation.creator) !== String(req.user._id) && req.user.role !== 'admin')
    fail(403, 'Only the creator can delete this community.');
  conversation.active = false;
  await conversation.save();
  notifyUsers(req.app.get('io'), conversation.members, 'data:refresh');
  res.json({ success: true });
});
chatsRouter.get('/conversations/:id/messages', async (req, res) => {
  const conversation = await requireConversation(req.params.id, req.user);
  let cursor = {};
  if (req.query.before) {
    const previous = await Message.findOne({
      _id: id(req.query.before),
      conversation: conversation._id,
    });
    if (!previous) fail(400, 'Invalid message cursor.');
    cursor = {
      $or: [
        { createdAt: { $lt: previous.createdAt } },
        { createdAt: previous.createdAt, _id: { $lt: previous._id } },
      ],
    };
  }
  const messages = await Message.find({
    conversation: conversation._id,
    ...cursor,
  })
    .sort({ createdAt: -1, _id: -1 })
    .limit(51)
    .populate('sender');
  const hasMore = messages.length > 50;
  res.json({
    messages: messages
      .slice(0, 50)
      .reverse()
      .map((m) => serializeMessage(m, req.user, conversation)),
    hasMore,
  });
});
chatsRouter.post('/conversations/:id/messages', upload.single('file'), async (req, res) => {
  const conversation = await requireConversation(req.params.id, req.user);
  const parsed = z
    .object({
      text: z.string().trim().max(1000, 'Messages can contain up to 1,000 characters.').default(''),
      anonymous: z.enum(['true', 'false']).optional(),
    })
    .safeParse(req.body);
  if (!parsed.success) fail(400, parsed.error.issues[0].message);
  if (!parsed.data.text && !req.file) fail(400, 'Write a message or add an attachment.');
  const attachment = await saveUpload(req.file);
  let message;
  try {
    message = await Message.create({
      conversation: conversation._id,
      sender: req.user._id,
      text: parsed.data.text,
      anonymous: parsed.data.anonymous ? parsed.data.anonymous === 'true' : req.user.anonymous,
      attachment,
    });
  } catch (error) {
    await removeUpload(attachment?.filename);
    throw error;
  }
  await Conversation.updateOne(
    { _id: conversation._id },
    { $set: { lastMessageAt: message.createdAt } },
  );
  await message.populate('sender');
  await publishMessage(req.app.get('io'), message, conversation);
  res.status(201).json(serializeMessage(message, req.user, conversation));
});
chatsRouter.post('/conversations/:id/read', async (req, res) => {
  const conversation = await requireConversation(req.params.id, req.user);
  await Conversation.updateOne(
    { _id: conversation._id },
    { $set: { [`reads.${req.user._id}`]: new Date() } },
  );
  notifyUsers(req.app.get('io'), conversation.members, 'conversation:read', {
    conversationId: String(conversation._id),
  });
  res.json({ success: true });
});
chatsRouter.get('/messages/:id/attachment', async (req, res) => {
  const message = await Message.findById(id(req.params.id));
  if (!message?.attachment?.filename) fail(404, 'Attachment not found.');
  await requireConversation(message.conversation, req.user);
  res.set('Content-Type', message.attachment.mime).set('Cache-Control', 'private, max-age=300');
  const filename = path.join(config.uploads, path.basename(message.attachment.filename));
  if (message.attachment.mime.startsWith('image/')) res.sendFile(filename);
  else res.download(filename, message.attachment.originalName);
});
