import { Router } from 'express';
import { z } from 'zod';
import { Report, Message, User, Conversation, Section, Audit } from '../models.js';
import { validate, roles, fail, id, objectId, publicUser } from '../security.js';
import { requireConversation } from '../conversations.js';
import { notifyUsers } from '../realtime.js';

export const moderationRouter = Router();
moderationRouter.get('/faculty', async (req, res) =>
  res.json(
    (await User.find({ role: 'lecturer', active: true }).sort({ firstName: 1 })).map(publicUser),
  ),
);
moderationRouter.post(
  '/reports',
  validate(
    z.object({
      messageId: objectId,
      facultyId: objectId,
      reason: z.enum(['Harassment or bullying', 'Inappropriate content', 'Spam', 'Other']),
      details: z.string().trim().max(1000).default(''),
    }),
  ),
  async (req, res) => {
    const message = await Message.findById(req.body.messageId);
    if (!message) fail(404, 'Message not found.');
    await requireConversation(message.conversation, req.user);
    if (String(message.sender) === String(req.user._id))
      fail(400, 'You cannot report your own message.');
    if (
      !(await User.exists({
        _id: req.body.facultyId,
        role: 'lecturer',
        active: true,
      }))
    )
      fail(400, 'Choose an active faculty member.');
    const report = await Report.create({
      message: message._id,
      reporter: req.user._id,
      faculty: req.body.facultyId,
      reason: req.body.reason,
      details: req.body.details,
    });
    notifyUsers(req.app.get('io'), [report.faculty], 'data:refresh');
    res.status(201).json({ _id: report._id });
  },
);
moderationRouter.get('/reports', roles('lecturer', 'admin'), async (req, res) => {
  const reports = await Report.find(req.user.role === 'admin' ? {} : { faculty: req.user._id })
    .populate('reporter faculty')
    .populate({
      path: 'message',
      populate: [{ path: 'sender' }, { path: 'conversation', select: 'name kind' }],
    })
    .sort({ createdAt: -1 })
    .limit(100);
  res.json(
    reports.map((r) => ({
      _id: r._id,
      status: r.status,
      reason: r.reason,
      details: r.details,
      createdAt: r.createdAt,
      reporter: publicUser(r.reporter),
      faculty: publicUser(r.faculty),
      message: {
        text: r.message.text,
        anonymous: r.message.anonymous,
        sender: publicUser(r.message.sender),
        conversation: r.message.conversation.name,
      },
    })),
  );
});
moderationRouter.patch(
  '/reports/:id',
  roles('lecturer', 'admin'),
  validate(z.object({ status: z.enum(['pending', 'reviewed']) })),
  async (req, res) => {
    const report = await Report.findOneAndUpdate(
      {
        _id: id(req.params.id),
        ...(req.user.role === 'admin' ? {} : { faculty: req.user._id }),
      },
      { status: req.body.status },
      { returnDocument: 'after' },
    );
    if (!report) fail(404, 'Report not found.');
    await Audit.create({
      actor: req.user._id,
      action: 'Updated report',
      detail: `Report ${report._id}: ${report.status}`,
    });
    res.json({ success: true });
  },
);
moderationRouter.get('/admin', roles('admin'), async (req, res) => {
  const [
    users,
    students,
    faculty,
    groups,
    rooms,
    messages,
    sections,
    pendingReports,
    recentUsers,
    logs,
  ] = await Promise.all([
    User.countDocuments({ active: true }),
    User.countDocuments({ active: true, role: 'student' }),
    User.countDocuments({ active: true, role: 'lecturer' }),
    Conversation.countDocuments({ active: true, kind: 'group' }),
    Conversation.countDocuments({ active: true, kind: 'room' }),
    Message.countDocuments(),
    Section.countDocuments({ active: true }),
    Report.countDocuments({ status: 'pending' }),
    User.find({ active: true }).sort({ createdAt: -1 }).limit(10),
    Audit.find().populate('actor').sort({ createdAt: -1 }).limit(20),
  ]);
  res.json({
    stats: {
      users,
      students,
      faculty,
      groups,
      rooms,
      messages,
      sections,
      pendingReports,
    },
    users: recentUsers.map(publicUser),
    logs: logs.map((log) => ({
      _id: log._id,
      action: log.action,
      detail: log.detail,
      actor: publicUser(log.actor),
      createdAt: log.createdAt,
    })),
  });
});
moderationRouter.get('/admin/sections', roles('admin'), async (req, res) => {
  const sections = await Section.find().sort({ code: 1 });
  res.json(
    await Promise.all(
      sections.map(async (section) => ({
        ...section.toObject(),
        students: await User.countDocuments({
          section: section.code,
          role: 'student',
          active: true,
        }),
      })),
    ),
  );
});
const sectionBody = z.object({
  code: z
    .string()
    .trim()
    .min(1)
    .max(15)
    .regex(/^[a-zA-Z0-9-]+$/, 'Use letters, numbers, or hyphens in the section code.')
    .transform((s) => s.toUpperCase()),
  name: z.string().trim().min(2).max(80),
  department: z.string().trim().min(2).max(80),
});
moderationRouter.post(
  '/admin/sections',
  roles('admin'),
  validate(sectionBody),
  async (req, res) => {
    const section = await Section.create(req.body);
    await Audit.create({
      actor: req.user._id,
      action: 'Created section',
      detail: section.code,
    });
    res.status(201).json(section);
  },
);
moderationRouter.patch(
  '/admin/sections/:id',
  roles('admin'),
  validate(sectionBody.partial().extend({ active: z.boolean().optional() })),
  async (req, res) => {
    const section = await Section.findById(id(req.params.id));
    if (!section) fail(404, 'Section not found.');
    if (
      req.body.code &&
      req.body.code !== section.code &&
      ((await User.exists({ section: section.code })) ||
        (await Conversation.exists({
          category: 'section',
          section: section.code,
        })))
    )
      fail(409, 'A section in use cannot be renamed. You can edit its name or department.');
    Object.assign(section, req.body);
    await section.save();
    await Audit.create({
      actor: req.user._id,
      action: 'Updated section',
      detail: `${section.code}: ${section.active ? 'active' : 'inactive'}`,
    });
    res.json(section);
  },
);
