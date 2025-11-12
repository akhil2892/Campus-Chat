import mongoose from 'mongoose';
import bcrypt from 'bcryptjs';
import { pathToFileURL } from 'node:url';
import { User, Section, Friendship, Conversation, Message, Audit } from './models.js';
import { config } from './config.js';
import { pairKey } from './security.js';

export async function seedDemo(password = process.env.DEMO_PASSWORD) {
  if (config.production || process.env.NODE_ENV === 'production') throw new Error('Demo seeding is disabled in production.');
  if (!password || password.length < 8) throw new Error('Set DEMO_PASSWORD (at least 8 characters) before seeding.');
  if (await User.countDocuments()) { console.log('Database already contains users. Seed skipped; no existing records were changed.'); return; }
  await Promise.all([User.init(), Section.init(), Friendship.init(), Conversation.init(), Message.init(), Audit.init()]);
  const passwordHash = await bcrypt.hash(password, 12);
  await Section.insertMany([
    { code: 'CSE-A', name: 'Computer Science A', department: 'Computer Science & Engineering' },
    { code: 'CSE-B', name: 'Computer Science B', department: 'Computer Science & Engineering' },
    { code: 'ECE-A', name: 'Electronics A', department: 'Electronics & Communication' },
  ]);
  const records = [
    ['Akhil', 'Rao', 'akhil@college.edu', 'sage', 'CSE-A', 'Building things, finding good coffee, and making campus feel a little smaller.'],
    ['Sophia', 'Chen', 'sophia@college.edu', 'peach', 'CSE-A', 'Design, good books, and an unreasonable number of playlists.'],
    ['Arjun', 'Mehta', 'arjun@college.edu', 'blue', 'CSE-A', 'Code. Coffee. Repeat. Usually in that order.'],
    ['Priya', 'Nair', 'priya@college.edu', 'lavender', 'CSE-A', 'Photography and the little things in between.'],
    ['Leo', 'Martin', 'leo@college.edu', 'gold', 'CSE-B', 'Always up for a game or a weekend hike.'],
    ['Noor', 'Khan', 'noor@college.edu', 'sage', 'ECE-A', 'Robotics club, campus radio, and late-night conversations.'],
  ];
  const students = await User.insertMany(records.map((r, i) => ({ firstName: r[0], lastName: r[1], email: r[2], color: r[3], section: r[4], bio: r[5], rollNumber: `DEMO${100 + i}`, year: 3, passwordHash })));
  const lecturer = await User.create({ firstName: 'Evelyn', lastName: 'Reed', email: 'evelyn@faculty.college.edu', role: 'lecturer', color: 'lavender', bio: 'Computer science faculty. Here to help.', passwordHash });
  const admin = await User.create({ firstName: 'Campus', lastName: 'Admin', email: 'admin@college.edu', role: 'admin', color: 'blue', passwordHash });
  const [akhil, sophia, arjun, priya, leo, noor] = students;
  await Friendship.insertMany([
    ...[sophia, arjun, priya].map(other => ({ sender: akhil._id, receiver: other._id, pairKey: pairKey(akhil._id, other._id), status: 'accepted' })),
    { sender: leo._id, receiver: akhil._id, pairKey: pairKey(leo._id, akhil._id), status: 'pending' },
    { sender: noor._id, receiver: akhil._id, pairKey: pairKey(noor._id, akhil._id), status: 'pending' },
  ]);
  const section = await Conversation.create({ name: 'CSE-A · Year 3', description: 'The home base for our class. Share notes, ask questions, and help each other out.', kind: 'group', category: 'section', topic: 'Academics', uniqueKey: 'section:CSE-A:3', section: 'CSE-A', year: 3, creator: lecturer._id, members: [akhil, sophia, arjun, priya, lecturer].map(u => u._id), color: 'sage' });
  for (const student of [leo, noor]) await Conversation.create({ name: `${student.section} · Year 3`, kind: 'group', category: 'section', uniqueKey: `section:${student.section}:3`, section: student.section, year: 3, creator: lecturer._id, members: [student._id, lecturer._id] });
  const design = await Conversation.create({ name: 'Design collective', description: 'For the curious minds who see the world a little differently. Share your work, get feedback, and create together.', kind: 'room', topic: 'Art & design', creator: sophia._id, members: [akhil, sophia, priya].map(u => u._id), color: 'peach' });
  const code = await Conversation.create({ name: 'The coding corner', description: 'Side projects, hackathons, and that bug you just cannot figure out. Let’s build something.', kind: 'room', topic: 'Technology', creator: arjun._id, members: [akhil, arjun, noor, lecturer].map(u => u._id), color: 'lavender' });
  await Conversation.create({ name: 'After class', description: 'Weekend plans, campus discoveries, and conversations that go beyond the classroom.', kind: 'room', topic: 'Campus life', creator: leo._id, members: [leo, noor, priya].map(u => u._id), color: 'blue' });
  await Conversation.create({ name: 'The reading room', description: 'One more chapter. Share your latest read and discover your next favorite.', kind: 'room', topic: 'Books & culture', creator: priya._id, members: [sophia, priya].map(u => u._id), color: 'gold' });
  await Conversation.create({ name: 'Weekend explorers', description: 'Find your next adventure, from a new café to a trail outside the city.', kind: 'room', topic: 'Outdoors', creator: noor._id, members: [leo, noor].map(u => u._id), color: 'sage' });
  const project = await Conversation.create({ name: 'Project people', description: 'Our web technologies project team. Small group, big ideas.', kind: 'group', category: 'interest', topic: 'Projects', creator: akhil._id, members: [akhil, sophia, arjun].map(u => u._id), invited: [akhil, sophia, arjun].map(u => u._id), color: 'blue' });
  const dmSophia = await Conversation.create({ name: 'Direct message', kind: 'direct', creator: akhil._id, uniqueKey: `direct:${pairKey(akhil._id, sophia._id)}`, members: [akhil._id, sophia._id] });
  const dmArjun = await Conversation.create({ name: 'Direct message', kind: 'direct', creator: akhil._id, uniqueKey: `direct:${pairKey(akhil._id, arjun._id)}`, members: [akhil._id, arjun._id] });
  const items = [
    [section, lecturer, 'Good morning, everyone! The project rubric is ready. Bring your ideas to our next class.', 220],
    [section, arjun, 'Anyone up for a study session in the library tomorrow?', 175],
    [section, priya, 'Count me in! I can bring my notes from the last lecture.', 150],
    [design, sophia, 'A little inspiration for your day: sometimes the best ideas start with a very rough sketch.', 130],
    [design, priya, 'Yes! Sharing some campus photos later. The light this morning was so good.', 105],
    [code, arjun, 'Weekend hackathon, anyone? Thinking of building something for the campus community.', 100],
    [code, noor, 'I’m in. A campus lost-and-found app could be useful!', 85],
    [project, akhil, 'Let’s keep our project ideas and updates here. Excited to build this with you both.', 75],
    [project, sophia, 'I’ll put together some design references before we meet.', 65],
    [dmSophia, sophia, 'Hey! Have you had a chance to look at the project brief?', 60],
    [dmSophia, akhil, 'Just finished reading it. I have a few ideas we could try.', 53],
    [dmSophia, sophia, 'Love that. Let’s grab a coffee after class and sketch them out ☕', 38],
    [dmArjun, arjun, 'Found a great resource for our project. I’ll send it over after class.', 21],
  ];
  for (const [conversation, sender, text, minutes] of items) {
    const createdAt = new Date(Date.now() - minutes * 60_000);
    await Message.create({ conversation: conversation._id, sender: sender._id, text, createdAt, updatedAt: createdAt });
    await Conversation.updateOne({ _id: conversation._id }, { $set: { lastMessageAt: createdAt } });
  }
  await Audit.create({ actor: admin._id, action: 'Initialized demo campus', detail: 'Fictional students, communities, and conversations added.' });
  console.log('Demo campus seeded. Accounts: akhil@college.edu, evelyn@faculty.college.edu, admin@college.edu.');
}
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  try { await mongoose.connect(config.mongoUri); await seedDemo(); } catch (error) { console.error(error.message); process.exitCode = 1; } finally { await mongoose.disconnect(); }
}
