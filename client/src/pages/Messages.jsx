import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import {
  ArrowLeft,
  Check,
  CheckCheck,
  FileText,
  Flag,
  Hash,
  Info,
  LogOut,
  MessageCircle,
  Paperclip,
  Plus,
  Search,
  Send,
  Shield,
  Smile,
  Trash2,
  Users,
  X,
} from 'lucide-react';
import { api, useApp, useResource } from '../state.jsx';
import {
  Avatar,
  Empty,
  ErrorMessage,
  Loading,
  Modal,
  MemberStack,
  fullName,
  relativeTime,
} from '../components.jsx';

export function Messages() {
  const { id } = useParams();
  const { user, setUser, notify, refresh, online, connected, socket } = useApp();
  const navigate = useNavigate();
  const { data: conversations, error } = useResource('/conversations');
  const [filter, setFilter] = useState('All');
  const [search, setSearch] = useState('');
  const [messages, setMessages] = useState([]);
  const [hasMore, setHasMore] = useState(false);
  const [loading, setLoading] = useState(false);
  const [messageError, setMessageError] = useState('');
  const [text, setText] = useState('');
  const [file, setFile] = useState(null);
  const [sending, setSending] = useState(false);
  const [typing, setTyping] = useState('');
  const [report, setReport] = useState(null);
  const [details, setDetails] = useState(false);
  const [newChat, setNewChat] = useState(false);
  const [emoji, setEmoji] = useState(false);
  const [confirm, setConfirm] = useState('');
  const feed = useRef(null);
  const fileInput = useRef(null);
  const textarea = useRef(null);
  const typingTimer = useRef(null);
  const lastTyping = useRef(0);
  const activeId = useRef(id);
  const conversation = conversations.find((c) => c._id === id);
  const visible = conversations
    .filter(
      (c) => filter === 'All' || (filter === 'Direct' ? c.kind === 'direct' : c.kind !== 'direct'),
    )
    .filter((c) => c.name.toLowerCase().includes(search.toLowerCase()));
  const scroll = () => {
    requestAnimationFrame(() => {
      if (feed.current) feed.current.scrollTop = feed.current.scrollHeight;
    });
  };
  const markRead = (current) =>
    api(`/conversations/${current}/read`, { method: 'POST' }).catch(() => {});
  useEffect(() => {
    activeId.current = id;
    setMessages([]);
    setText('');
    setFile(null);
    setTyping('');
    setMessageError('');
    setDetails(false);
    setHasMore(false);
    if (!id) return;
    let active = true;
    setLoading(true);
    api(`/conversations/${id}/messages`)
      .then((data) => {
        if (active) {
          setMessages((current) => {
            const combined = new Map(
              [...data.messages, ...current].map((message) => [message._id, message]),
            );
            return [...combined.values()].sort(
              (a, b) => new Date(a.createdAt) - new Date(b.createdAt),
            );
          });
          setHasMore(data.hasMore);
          scroll();
          markRead(id);
          refresh();
        }
      })
      .catch((e) => {
        if (active) setMessageError(e.message);
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
      clearTimeout(typingTimer.current);
    };
  }, [id]);
  useEffect(() => {
    const receive = (e) => {
      const message = e.detail;
      if (message.conversation !== activeId.current) return;
      setMessages((current) =>
        current.some((m) => m._id === message._id) ? current : [...current, message],
      );
      setTyping('');
      scroll();
      if (document.visibilityState === 'visible') markRead(activeId.current);
    };
    const receipts = (e) => {
      if (e.detail.conversationId !== activeId.current) return;
      api(`/conversations/${activeId.current}/messages`)
        .then((data) => {
          setMessages((current) =>
            current.map((m) => data.messages.find((updated) => updated._id === m._id) || m),
          );
        })
        .catch(() => {});
    };
    const showTyping = (e) => {
      if (e.detail.conversationId !== activeId.current) return;
      setTyping(e.detail.active ? e.detail.name : '');
      clearTimeout(typingTimer.current);
      typingTimer.current = setTimeout(() => setTyping(''), 3000);
    };
    const focus = () => {
      if (activeId.current && document.visibilityState === 'visible') markRead(activeId.current);
    };
    window.addEventListener('campus:message', receive);
    window.addEventListener('campus:read', receipts);
    window.addEventListener('campus:typing', showTyping);
    document.addEventListener('visibilitychange', focus);
    return () => {
      window.removeEventListener('campus:message', receive);
      window.removeEventListener('campus:read', receipts);
      window.removeEventListener('campus:typing', showTyping);
      document.removeEventListener('visibilitychange', focus);
    };
  }, []);
  useEffect(() => {
    if (!connected || !id) return;
    let active = true;
    api(`/conversations/${id}/messages`)
      .then((data) => {
        if (active)
          setMessages((current) => {
            const map = new Map([...current, ...data.messages].map((m) => [m._id, m]));
            return [...map.values()].sort((a, b) => new Date(a.createdAt) - new Date(b.createdAt));
          });
      })
      .catch(() => {});
    return () => {
      active = false;
    };
  }, [connected, id]);
  const send = async (e) => {
    e.preventDefault();
    if ((!text.trim() && !file) || sending || !id) return;
    const target = id;
    setSending(true);
    const body = new FormData();
    body.set('text', text);
    body.set('anonymous', String(user.anonymous));
    if (file) body.set('file', file);
    try {
      const message = await api(`/conversations/${target}/messages`, {
        method: 'POST',
        body,
      });
      if (activeId.current === target) {
        setMessages((current) =>
          current.some((m) => m._id === message._id) ? current : [...current, message],
        );
        setText('');
        setFile(null);
        setEmoji(false);
        scroll();
      }
      refresh();
    } catch (e) {
      notify(e.message, 'error');
    } finally {
      setSending(false);
      textarea.current?.focus();
    }
  };
  const loadOlder = async () => {
    if (!messages.length || loading) return;
    const target = id;
    setLoading(true);
    const previousHeight = feed.current?.scrollHeight || 0;
    try {
      const data = await api(`/conversations/${target}/messages?before=${messages[0]._id}`);
      if (activeId.current === target) {
        setMessages((current) => [...data.messages, ...current]);
        setHasMore(data.hasMore);
        requestAnimationFrame(() => {
          if (feed.current) feed.current.scrollTop = feed.current.scrollHeight - previousHeight;
        });
      }
    } catch (e) {
      notify(e.message, 'error');
    } finally {
      setLoading(false);
    }
  };
  const updateText = (e) => {
    setText(e.target.value);
    if (Date.now() - lastTyping.current > 1000) {
      socket.current?.emit('typing', { conversationId: id, active: true });
      lastTyping.current = Date.now();
    }
  };
  const toggleAnonymous = async () => {
    try {
      const updated = await api('/users/me', {
        method: 'PATCH',
        body: { anonymous: !user.anonymous },
      });
      setUser(updated);
    } catch (e) {
      notify(e.message, 'error');
    }
  };
  const communityAction = async () => {
    try {
      await api(`/conversations/${id}${confirm === 'delete' ? '' : '/leave'}`, {
        method: confirm === 'delete' ? 'DELETE' : 'POST',
      });
      setConfirm('');
      setDetails(false);
      refresh();
      navigate('/messages');
      notify(confirm === 'delete' ? 'Community deleted.' : 'You’ve left this space.');
    } catch (e) {
      notify(e.message, 'error');
    }
  };
  const other = conversation?.members.find((u) => u._id !== user._id);
  return (
    <div className={`messaging-layout ${id ? 'chat-active' : ''}`}>
      <aside className="conversation-sidebar">
        <div className="conversation-sidebar-heading">
          <div>
            <span className="eyebrow">KEEP IN TOUCH</span>
            <h1>
              Messages<span>✳</span>
            </h1>
          </div>
          <button
            className="icon-button bordered"
            aria-label="Start a conversation"
            onClick={() => setNewChat(true)}
          >
            <Plus size={20} />
          </button>
        </div>
        <label className="search-field">
          <Search size={17} />
          <input
            aria-label="Search conversations"
            placeholder="Find a conversation…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </label>
        <div className="chat-filter-tabs">
          {['All', 'Direct', 'Communities'].map((f) => (
            <button key={f} className={filter === f ? 'selected' : ''} onClick={() => setFilter(f)}>
              {f}
            </button>
          ))}
        </div>
        <ErrorMessage message={error} />
        <div className="conversation-list">
          {visible.length ? (
            visible.map((c) => {
              const member = c.members.find((u) => u._id !== user._id);
              return (
                <Link
                  className={`conversation-item ${id === c._id ? 'selected' : ''}`}
                  to={`/messages/${c._id}`}
                  key={c._id}
                >
                  {c.kind === 'direct' ? (
                    <Avatar user={member} online={online.includes(member?._id)} />
                  ) : (
                    <span className={`avatar tone-${c.color}`}>
                      {c.kind === 'room' ? <Hash size={22} /> : <Users size={21} />}
                    </span>
                  )}
                  <div className="conversation-preview">
                    <div>
                      <strong>{c.name}</strong>
                      <small>{relativeTime(c.lastMessage?.createdAt)}</small>
                    </div>
                    <p>
                      {c.lastMessage?.mine
                        ? 'You: '
                        : c.lastMessage?.anonymous
                          ? 'Anonymous: '
                          : ''}
                      {c.lastMessage?.text ||
                        c.lastMessage?.attachment?.name ||
                        'Start a conversation'}
                    </p>
                    <span className="conversation-kind">
                      {c.kind === 'direct'
                        ? 'Direct message'
                        : c.category === 'section'
                          ? 'Section group'
                          : c.kind === 'room'
                            ? 'Campus room'
                            : 'Private group'}
                    </span>
                  </div>
                  {c.unread > 0 && <span className="unread-pill">{c.unread}</span>}
                </Link>
              );
            })
          ) : (
            <Empty
              title="A quiet corner, for now."
              text="Add a friend or join a campus space to start chatting."
              action={
                <Link className="text-link" to="/people">
                  Find your people ↗
                </Link>
              }
            />
          )}
        </div>
        <div className="conversation-sidebar-footer">
          <span className={`connection-dot ${connected ? 'connected' : ''}`} />
          {connected ? 'You’re connected' : 'Reconnecting…'}
          <Shield size={14} />
        </div>
      </aside>
      <section className="chat-main">
        {id ? (
          <>
            <header className="chat-header">
              <button
                className="icon-button chat-back"
                aria-label="Back to conversations"
                onClick={() => navigate('/messages')}
              >
                <ArrowLeft size={21} />
              </button>
              {conversation?.kind === 'direct' ? (
                <Avatar user={other} online={online.includes(other?._id)} />
              ) : (
                <span className={`avatar tone-${conversation?.color || 'sage'}`}>
                  {conversation?.kind === 'room' ? <Hash size={23} /> : <Users size={23} />}
                </span>
              )}
              <div>
                <h2>{conversation?.name || 'Conversation'}</h2>
                <p>
                  {conversation?.kind === 'direct' ? (
                    online.includes(other?._id) ? (
                      <>
                        <span className="status-dot" />
                        Around campus
                      </>
                    ) : (
                      'Direct conversation'
                    )
                  ) : (
                    `${conversation?.memberCount || 0} members · ${conversation?.topic || 'A shared space'}`
                  )}
                </p>
              </div>
              <button
                className="icon-button chat-info"
                onClick={() => setDetails(true)}
                aria-label="Conversation details"
              >
                <Info size={21} />
              </button>
            </header>
            <ErrorMessage message={messageError} />
            <div className="message-feed" ref={feed}>
              {hasMore && (
                <button className="load-older" onClick={loadOlder} disabled={loading}>
                  {loading ? 'Loading…' : 'Earlier messages'}
                </button>
              )}
              {loading && !messages.length ? (
                <Loading />
              ) : !messages.length && !messageError ? (
                <Empty
                  title="A good conversation starts here."
                  text="Say hello, share an idea, or ask a question."
                />
              ) : (
                <>
                  {messages.map((message, i) => {
                    const date = new Date(message.createdAt).toLocaleDateString('en-IN', {
                      day: 'numeric',
                      month: 'long',
                      year: 'numeric',
                    });
                    const previousDate = i
                      ? new Date(messages[i - 1].createdAt).toLocaleDateString('en-IN', {
                          day: 'numeric',
                          month: 'long',
                          year: 'numeric',
                        })
                      : '';
                    return (
                      <div key={message._id}>
                        {date !== previousDate && (
                          <div className="message-date">
                            <span>{date}</span>
                          </div>
                        )}
                        <div className={`message-row ${message.mine ? 'mine' : ''}`}>
                          {!message.mine && <Avatar user={message.sender} size="sm" />}
                          <div className="message-body">
                            {!message.mine && (
                              <span className="message-author">
                                {fullName(message.sender)}
                                {message.anonymous && <Shield size={11} />}
                              </span>
                            )}
                            <div className="message-bubble">
                              {message.text && <p>{message.text}</p>}
                              {message.attachment && (
                                <a
                                  className="attachment-link"
                                  href={message.attachment.url}
                                  target="_blank"
                                  rel="noreferrer"
                                >
                                  {message.attachment.mime.startsWith('image/') ? (
                                    <img
                                      src={message.attachment.url}
                                      alt={message.attachment.name}
                                      loading="lazy"
                                    />
                                  ) : (
                                    <>
                                      <FileText size={22} />
                                      <span>
                                        {message.attachment.name}
                                        <small>
                                          {(message.attachment.size / 1024).toFixed(1)} KB ·
                                          Download
                                        </small>
                                      </span>
                                    </>
                                  )}
                                </a>
                              )}
                            </div>
                            <div className="message-meta">
                              <span>
                                {new Date(message.createdAt).toLocaleTimeString('en-IN', {
                                  hour: '2-digit',
                                  minute: '2-digit',
                                })}
                              </span>
                              {message.mine && (
                                <>
                                  <span>
                                    {message.anonymous ? 'Anonymous · ' : ''}
                                    {message.readCount > 0 ? 'Read' : 'Sent'}
                                  </span>
                                  {message.readCount > 0 ? (
                                    <CheckCheck size={13} />
                                  ) : (
                                    <Check size={13} />
                                  )}
                                </>
                              )}
                              {!message.mine && (
                                <button
                                  className="report-button"
                                  onClick={() => setReport(message)}
                                  aria-label={`Report message from ${fullName(message.sender)}`}
                                  title="Report message"
                                >
                                  <Flag size={12} />
                                </button>
                              )}
                            </div>
                          </div>
                        </div>
                      </div>
                    );
                  })}
                </>
              )}
              {typing && (
                <div className="typing-indicator">
                  <span>
                    <i />
                    <i />
                    <i />
                  </span>
                  {typing} is typing…
                </div>
              )}
            </div>
            <div className="composer-area">
              {file && (
                <div className="selected-file">
                  <Paperclip size={16} />
                  <span>{file.name}</span>
                  <button
                    className="icon-button"
                    onClick={() => setFile(null)}
                    aria-label="Remove attachment"
                  >
                    <X size={16} />
                  </button>
                </div>
              )}
              {emoji && (
                <div className="emoji-picker">
                  {['👋', '☕', '😊', '✨', '🙌', '💚', '👍', '🎉'].map((value) => (
                    <button
                      key={value}
                      onClick={() => {
                        setText((t) => t + value);
                        setEmoji(false);
                        textarea.current?.focus();
                      }}
                    >
                      {value}
                    </button>
                  ))}
                </div>
              )}
              <form className="message-composer" onSubmit={send}>
                <textarea
                  ref={textarea}
                  aria-label="Write a message"
                  placeholder={
                    user.anonymous
                      ? 'Share a thought anonymously…'
                      : 'A hello, a thought, a little update…'
                  }
                  value={text}
                  onChange={updateText}
                  maxLength={1000}
                  rows={1}
                  onKeyDown={(e) => {
                    if (e.key === 'Enter' && !e.shiftKey && !e.nativeEvent.isComposing) {
                      e.preventDefault();
                      send(e);
                    }
                  }}
                />
                <div className="composer-controls">
                  <div>
                    <input
                      ref={fileInput}
                      type="file"
                      hidden
                      accept=".png,.jpg,.jpeg,.gif,.pdf,.doc,.docx,.txt,.zip"
                      onChange={(e) => {
                        const selected = e.target.files[0];
                        if (selected?.size > 10 * 1024 * 1024)
                          notify('Files must be 10 MB or smaller.', 'error');
                        else setFile(selected || null);
                        e.target.value = '';
                      }}
                    />
                    <button
                      className="icon-button"
                      type="button"
                      aria-label="Attach a file"
                      onClick={() => fileInput.current?.click()}
                    >
                      <Paperclip size={18} />
                    </button>
                    <button
                      className="icon-button"
                      type="button"
                      aria-label="Add an emoji"
                      onClick={() => setEmoji(!emoji)}
                    >
                      <Smile size={19} />
                    </button>
                    <button
                      className={`anonymous-toggle ${user.anonymous ? 'enabled' : ''}`}
                      type="button"
                      onClick={toggleAnonymous}
                      aria-pressed={user.anonymous}
                    >
                      <Shield size={14} />
                      <span>{user.anonymous ? 'Anonymous on' : 'Anonymous off'}</span>
                    </button>
                  </div>
                  <button
                    className="send-button"
                    disabled={sending || (!text.trim() && !file) || Boolean(messageError)}
                    aria-label="Send message"
                  >
                    {sending ? <span className="spinner" /> : <Send size={18} />}
                  </button>
                </div>
              </form>
              <div className="composer-caption">
                <span>Enter to send · Shift + Enter for a new line</span>
                <span>{text.length}/1000</span>
              </div>
            </div>
          </>
        ) : (
          <div className="chat-welcome">
            <span className="chat-welcome-art">
              <MessageCircle size={70} strokeWidth={1.4} />
              <i>✳</i>
            </span>
            <span className="eyebrow">A LITTLE MORE CONNECTION</span>
            <h2>
              Good conversations.
              <br />
              <em>Great company.</em>
            </h2>
            <p>
              Pick a conversation, share an idea,
              <br />
              or simply say hello.
            </p>
            <button className="button primary" onClick={() => setNewChat(true)}>
              <Plus size={17} />
              Start a conversation
            </button>
            <span className="chat-welcome-note">
              <Shield size={14} />
              Your campus. A thoughtful space.
            </span>
          </div>
        )}
      </section>
      {report && <ReportMessage message={report} onClose={() => setReport(null)} />}
      {newChat && <NewConversation onClose={() => setNewChat(false)} />}
      {details && conversation && (
        <Modal
          title={conversation.name}
          subtitle={conversation.description || 'A little space to keep the conversation going.'}
          onClose={() => setDetails(false)}
        >
          <div className="conversation-member-list">
            <span className="eyebrow">{conversation.memberCount} PEOPLE IN THIS SPACE</span>
            {conversation.members.map((member) => (
              <div key={member._id}>
                <Avatar user={member} size="sm" online={online.includes(member._id)} />
                <span>
                  <strong>{fullName(member)}</strong>
                  <small>
                    {member.role === 'student'
                      ? `${member.section} · Year ${member.year}`
                      : 'Faculty / staff'}
                  </small>
                </span>
                {member._id === conversation.creator && <span className="tag">Creator</span>}
              </div>
            ))}
          </div>
          {conversation.kind !== 'direct' && (
            <div className="community-actions">
              <button className="button secondary" onClick={() => setConfirm('leave')}>
                <LogOut size={16} />
                Leave space
              </button>
              {conversation.category !== 'section' &&
                (conversation.creator === user._id || user.role === 'admin') && (
                  <button className="button danger" onClick={() => setConfirm('delete')}>
                    <Trash2 size={16} />
                    Delete space
                  </button>
                )}
            </div>
          )}
        </Modal>
      )}
      {confirm && (
        <Modal
          title={confirm === 'delete' ? 'Delete this space?' : 'Leave this space?'}
          subtitle={
            confirm === 'delete'
              ? 'This community will close for every member. Its messages will no longer be accessible.'
              : 'You can rejoin eligible section groups, invited private groups, and campus rooms later.'
          }
          onClose={() => setConfirm('')}
        >
          <div className="form-row">
            <button className="button secondary" onClick={() => setConfirm('')}>
              Keep my space
            </button>
            <button
              className={`button ${confirm === 'delete' ? 'danger' : 'primary'}`}
              onClick={communityAction}
            >
              {confirm === 'delete' ? 'Delete space' : 'Leave space'}
            </button>
          </div>
        </Modal>
      )}
    </div>
  );
}
function NewConversation({ onClose }) {
  const { data, loading, error } = useResource('/friends');
  const { notify, refresh } = useApp();
  const navigate = useNavigate();
  const [busy, setBusy] = useState('');
  const friends = data.filter((f) => f.status === 'accepted');
  const start = async (friend) => {
    setBusy(friend._id);
    try {
      const c = await api('/conversations/direct', {
        method: 'POST',
        body: { userId: friend.user._id },
      });
      refresh();
      onClose();
      navigate(`/messages/${c._id}`);
    } catch (e) {
      notify(e.message, 'error');
    } finally {
      setBusy('');
    }
  };
  return (
    <Modal
      title="It starts with a hello."
      subtitle="Choose a campus friend to start a conversation."
      onClose={onClose}
    >
      <ErrorMessage message={error} />
      {loading ? (
        <Loading />
      ) : friends.length ? (
        <div className="new-chat-list">
          {friends.map((friend) => (
            <button disabled={busy === friend._id} key={friend._id} onClick={() => start(friend)}>
              <Avatar user={friend.user} />
              <span>
                <strong>{fullName(friend.user)}</strong>
                <small>{friend.user.section || 'Faculty'}</small>
              </span>
              <MessageCircle size={19} />
            </button>
          ))}
        </div>
      ) : (
        <Empty
          title="Your people are out there."
          text="Connect with someone first, then say hello."
          action={
            <Link className="button secondary" to="/people" onClick={onClose}>
              Find people ↗
            </Link>
          }
        />
      )}
    </Modal>
  );
}
function ReportMessage({ message, onClose }) {
  const { data: faculty } = useResource('/faculty');
  const { notify } = useApp();
  const [form, setForm] = useState({
    facultyId: '',
    reason: 'Harassment or bullying',
    details: '',
  });
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const submit = async (e) => {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      await api('/reports', {
        method: 'POST',
        body: { ...form, messageId: message._id },
      });
      notify('Report sent to your selected faculty member.');
      onClose();
    } catch (e) {
      setError(e.message);
    } finally {
      setBusy(false);
    }
  };
  return (
    <Modal
      title="Look out for your campus."
      subtitle="Your selected faculty member will review this message and its real sender, even if it was anonymous."
      onClose={onClose}
    >
      <div className="reported-preview">{message.text || message.attachment?.name}</div>
      <ErrorMessage message={error} />
      <form className="form-stack" onSubmit={submit}>
        <label>
          Send to faculty
          <select
            required
            value={form.facultyId}
            onChange={(e) => setForm((f) => ({ ...f, facultyId: e.target.value }))}
          >
            <option value="">Choose a faculty member</option>
            {faculty.map((person) => (
              <option key={person._id} value={person._id}>
                {fullName(person)}
              </option>
            ))}
          </select>
        </label>
        <label>
          Reason
          <select
            value={form.reason}
            onChange={(e) => setForm((f) => ({ ...f, reason: e.target.value }))}
          >
            {['Harassment or bullying', 'Inappropriate content', 'Spam', 'Other'].map((reason) => (
              <option key={reason}>{reason}</option>
            ))}
          </select>
        </label>
        <label>
          Anything else? <span className="optional">Optional</span>
          <textarea
            rows={3}
            maxLength={1000}
            placeholder="A little context helps."
            value={form.details}
            onChange={(e) => setForm((f) => ({ ...f, details: e.target.value }))}
          />
        </label>
        <button className="button primary full-width" disabled={busy || !faculty.length}>
          {busy ? 'Sending…' : 'Send report'}
          <Flag size={16} />
        </button>
      </form>
    </Modal>
  );
}
