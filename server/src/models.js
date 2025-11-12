import mongoose from 'mongoose';

const { Schema, model } = mongoose;
const ref = (name, extra = {}) => ({ type: Schema.Types.ObjectId, ref: name, ...extra });
const userSchema = new Schema({
  firstName: { type: String, required: true, trim: true, maxlength: 50 },
  lastName: { type: String, required: true, trim: true, maxlength: 50 },
  email: { type: String, unique: true, required: true, lowercase: true, trim: true },
  passwordHash: { type: String, required: true, select: false },
  role: { type: String, enum: ['student', 'lecturer', 'admin'], default: 'student' },
  rollNumber: { type: String, trim: true },
  section: String,
  year: Number,
  bio: { type: String, maxlength: 240, default: '' },
  avatar: String,
  color: { type: String, default: 'sage' },
  anonymous: { type: Boolean, default: false },
  active: { type: Boolean, default: true },
}, { timestamps: true });
userSchema.index({ rollNumber: 1 }, { unique: true, partialFilterExpression: { rollNumber: { $type: 'string' } } });
export const User = model('User', userSchema);
export const Session = model('Session', new Schema({
  tokenHash: { type: String, unique: true, required: true },
  user: ref('User', { required: true }),
  expiresAt: { type: Date, required: true, index: { expires: 0 } },
}, { timestamps: true }));
export const Section = model('Section', new Schema({
  code: { type: String, unique: true, required: true, uppercase: true, trim: true },
  name: { type: String, required: true, trim: true },
  department: { type: String, required: true, trim: true },
  active: { type: Boolean, default: true },
}, { timestamps: true }));
export const Friendship = model('Friendship', new Schema({
  pairKey: { type: String, unique: true, required: true },
  sender: ref('User', { required: true }),
  receiver: ref('User', { required: true }),
  status: { type: String, enum: ['pending', 'accepted', 'rejected'], default: 'pending' },
}, { timestamps: true }));
const conversationSchema = new Schema({
  name: { type: String, required: true, maxlength: 80 },
  description: { type: String, maxlength: 500, default: '' },
  kind: { type: String, enum: ['direct', 'group', 'room'], required: true },
  category: { type: String, enum: ['section', 'interest', 'general'], default: 'general' },
  topic: { type: String, maxlength: 40, default: 'Campus life' },
  color: { type: String, enum: ['sage', 'peach', 'lavender', 'blue', 'gold'], default: 'sage' },
  creator: ref('User', { required: true }),
  members: [ref('User')],
  invited: [ref('User')],
  uniqueKey: { type: String },
  section: String,
  year: Number,
  active: { type: Boolean, default: true },
  reads: { type: Map, of: Date, default: {} },
  lastMessageAt: { type: Date, default: Date.now },
}, { timestamps: true });
conversationSchema.index({ uniqueKey: 1 }, { unique: true, sparse: true });
conversationSchema.index({ members: 1, active: 1 });
export const Conversation = model('Conversation', conversationSchema);
const messageSchema = new Schema({
  conversation: ref('Conversation', { required: true }),
  sender: ref('User', { required: true }),
  text: { type: String, maxlength: 1000, default: '' },
  anonymous: { type: Boolean, default: false },
  attachment: { filename: String, originalName: String, mime: String, size: Number },
}, { timestamps: true });
messageSchema.index({ conversation: 1, createdAt: -1, _id: -1 });
export const Message = model('Message', messageSchema);
const reportSchema = new Schema({
  message: ref('Message', { required: true }),
  reporter: ref('User', { required: true }),
  faculty: ref('User', { required: true }),
  reason: { type: String, required: true, maxlength: 80 },
  details: { type: String, maxlength: 1000, default: '' },
  status: { type: String, enum: ['pending', 'reviewed'], default: 'pending' },
}, { timestamps: true });
reportSchema.index({ message: 1, reporter: 1 }, { unique: true });
export const Report = model('Report', reportSchema);
export const Audit = model('Audit', new Schema({
  actor: ref('User', { required: true }),
  action: { type: String, required: true },
  detail: String,
}, { timestamps: true }));
