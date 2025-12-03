import { Conversation, Friendship, Message } from './models.js';
import { fail, id, pairKey, publicUser } from './security.js';

export async function requireConversation(conversationId, user) {
  const conversation = await Conversation.findById(id(conversationId));
  if (!conversation?.active) fail(404, 'Conversation not found.');
  if (!conversation.members.some((member) => String(member) === String(user._id)))
    fail(403, 'Join this conversation to continue.');
  if (conversation.kind === 'direct') {
    const other = conversation.members.find((member) => String(member) !== String(user._id));
    if (
      !(await Friendship.exists({
        pairKey: pairKey(user._id, other),
        status: 'accepted',
      }))
    )
      fail(403, 'You can only message friends.');
  }
  return conversation;
}
export async function autoSection(user) {
  if (user.role !== 'student') return;
  await Conversation.findOneAndUpdate(
    { uniqueKey: `section:${user.section}:${user.year}` },
    {
      $setOnInsert: {
        name: `Section ${user.section} · Year ${user.year}`,
        description: 'Your class, all in one place. Notes, questions, and everything in between.',
        kind: 'group',
        category: 'section',
        section: user.section,
        year: user.year,
        creator: user._id,
        topic: 'Academics',
      },
      $addToSet: { members: user._id },
    },
    { upsert: true, returnDocument: 'after', setDefaultsOnInsert: true },
  );
}
export function serializeMessage(message, viewer, conversation) {
  const sender = message.sender;
  const mine = String(sender._id || sender) === String(viewer._id);
  const hidden = message.anonymous && !mine;
  const reads = conversation.reads;
  const readCount = conversation.members.filter(
    (member) =>
      String(member._id || member) !== String(sender._id || sender) &&
      new Date(
        reads?.get?.(String(member._id || member)) || reads?.[String(member._id || member)] || 0,
      ) >= message.createdAt,
  ).length;
  return {
    _id: String(message._id),
    conversation: String(message.conversation),
    text: message.text,
    anonymous: message.anonymous,
    mine,
    sender: hidden
      ? {
          firstName: 'Anonymous',
          lastName: '',
          color: 'lavender',
          avatar: null,
        }
      : publicUser(sender),
    attachment: message.attachment?.filename
      ? {
          name: message.attachment.originalName,
          mime: message.attachment.mime,
          size: message.attachment.size,
          url: `/api/messages/${message._id}/attachment`,
        }
      : null,
    createdAt: message.createdAt,
    readCount,
  };
}
export async function serializeConversation(conversation, viewer) {
  const joined = conversation.members.some(
    (member) => String(member._id || member) === String(viewer._id),
  );
  const populated = await conversation.populate(
    'members',
    'firstName lastName email role section year color avatar bio',
  );
  const last = joined
    ? await Message.findOne({ conversation: conversation._id })
        .sort({ createdAt: -1, _id: -1 })
        .populate('sender')
    : null;
  const unread = joined
    ? await Message.countDocuments({
        conversation: conversation._id,
        sender: { $ne: viewer._id },
        createdAt: {
          $gt: conversation.reads.get(String(viewer._id)) || new Date(0),
        },
      })
    : 0;
  const other = populated.members.find((member) => String(member._id) !== String(viewer._id));
  return {
    _id: String(conversation._id),
    name:
      conversation.kind === 'direct' && other
        ? `${other.firstName} ${other.lastName}`
        : conversation.name,
    description: conversation.description,
    kind: conversation.kind,
    category: conversation.category,
    topic: conversation.topic,
    color: conversation.kind === 'direct' ? other?.color : conversation.color,
    section: conversation.section,
    year: conversation.year,
    creator: String(conversation.creator),
    memberCount: populated.members.length,
    members: populated.members.map(publicUser),
    joined,
    unread,
    lastMessage: last ? serializeMessage(last, viewer, conversation) : null,
    lastMessageAt: conversation.lastMessageAt,
  };
}
