import { useState } from 'react';
import { Link, useNavigate, useOutletContext } from 'react-router-dom';
import { ArrowUpRight, Compass, Hash, Plus, Search, Users } from 'lucide-react';
import { api, useApp, useResource } from '../state.jsx';
import {
  Avatar,
  CommunityCard,
  Empty,
  ErrorMessage,
  Loading,
  Modal,
  PageHeading,
  fullName,
} from '../components.jsx';

export function Communities({ groups = false }) {
  const { refresh, notify } = useApp();
  const { openCreate } = useOutletContext();
  const { data, error, loading } = useResource(groups ? '/conversations' : '/discover');
  const [query, setQuery] = useState('');
  const [filter, setFilter] = useState('All spaces');
  const [busy, setBusy] = useState('');
  const items = data
    .filter((c) => (groups ? c.kind === 'group' : c.kind !== 'direct'))
    .filter((c) =>
      `${c.name} ${c.topic} ${c.description}`.toLowerCase().includes(query.toLowerCase()),
    )
    .filter(
      (c) =>
        filter === 'All spaces' ||
        (filter === 'My spaces'
          ? c.joined
          : filter === 'Section groups'
            ? c.category === 'section'
            : c.topic === filter),
    );
  const join = async (id) => {
    setBusy(id);
    try {
      await api(`/conversations/${id}/join`, { method: 'POST' });
      refresh();
      notify('You’re in. Say your first hello!');
    } catch (e) {
      notify(e.message, 'error');
    } finally {
      setBusy('');
    }
  };
  return (
    <>
      <PageHeading
        eyebrow={groups ? 'YOUR SHARED SPACES' : 'FIND YOUR KIND OF PEOPLE'}
        title={groups ? 'Your class & study groups.' : 'Discover campus rooms.'}
        description={
          groups
            ? 'Your class group is ready. Create a private group for notes, projects, or exam prep.'
            : 'Join a club conversation, ask a question, or find students who share your interests.'
        }
        action={
          <button className="button primary" onClick={() => openCreate(groups ? 'group' : 'room')}>
            <Plus size={17} />
            Create a space
          </button>
        }
      />
      {!groups && (
        <div className="discover-banner">
          <span className="discover-banner-icon">
            <Compass size={33} />
          </span>
          <div>
            <strong>Different interests. One campus.</strong>
            <p>Campus rooms are open to everyone. Private groups bring your friends together.</p>
          </div>
          <span className="discover-banner-star">✳</span>
        </div>
      )}
      <div className="filter-bar">
        <div className="filter-tabs">
          {(groups
            ? ['All spaces', 'Section groups']
            : ['All spaces', 'My spaces', 'Technology', 'Art & design', 'Campus life']
          ).map((f) => (
            <button className={filter === f ? 'selected' : ''} key={f} onClick={() => setFilter(f)}>
              {f}
            </button>
          ))}
        </div>
        <label className="search-field">
          <Search size={17} />
          <input
            aria-label="Search communities"
            placeholder="Find a space…"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
          />
        </label>
      </div>
      <ErrorMessage message={error} />
      {loading && !data.length ? (
        <Loading />
      ) : items.length ? (
        <div className="community-grid">
          {items.map((c) => (
            <CommunityCard key={c._id} community={c} onJoin={join} busy={busy === c._id} />
          ))}
        </div>
      ) : (
        <Empty
          icon={groups ? Users : Compass}
          title={
            query || filter !== 'All spaces'
              ? 'No spaces found.'
              : groups
                ? 'Make room for your people.'
                : 'Your campus is just getting started.'
          }
          text={
            query || filter !== 'All spaces'
              ? 'Try a different search or explore another interest.'
              : 'Create a community and start something good.'
          }
          action={
            <button
              className="button secondary"
              onClick={() => openCreate(groups ? 'group' : 'room')}
            >
              <Plus size={17} />
              Create a space
            </button>
          }
        />
      )}
      <div className="bottom-note">
        <span>✳</span> Great communities start with a simple hello.
      </div>
    </>
  );
}
export function CreateCommunity({ onClose, initialKind = 'room' }) {
  const { user, notify, refresh } = useApp();
  const navigate = useNavigate();
  const { data: friendships } = useResource('/friends');
  const { data: sections } = useResource('/auth/sections');
  const friends = friendships.filter((f) => f.status === 'accepted');
  const [form, setForm] = useState({
    name: '',
    description: '',
    kind: initialKind,
    category: 'interest',
    topic: 'Campus life',
    color: 'sage',
    memberIds: [],
    section: '',
    year: '3',
  });
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const change = (e) => setForm((f) => ({ ...f, [e.target.name]: e.target.value }));
  const submit = async (e) => {
    e.preventDefault();
    setError('');
    setBusy(true);
    try {
      const body = { ...form };
      if (body.kind !== 'group' || body.category !== 'section') {
        delete body.section;
        delete body.year;
      }
      const c = await api('/conversations', { method: 'POST', body });
      refresh();
      notify('Your new space is ready. Make yourself at home.');
      onClose();
      navigate(`/messages/${c._id}`);
    } catch (e) {
      setError(e.message);
    } finally {
      setBusy(false);
    }
  };
  return (
    <Modal
      title="Create a room or group."
      subtitle="Start a study group, plan a project, or bring a club together."
      onClose={onClose}
    >
      <ErrorMessage message={error} />
      <form className="form-stack" onSubmit={submit}>
        <div className="space-type-select">
          <button
            type="button"
            className={form.kind === 'room' ? 'selected' : ''}
            onClick={() => setForm((f) => ({ ...f, kind: 'room' }))}
          >
            <Hash size={22} />
            <strong>Campus room</strong>
            <small>Open to everyone</small>
          </button>
          <button
            type="button"
            className={form.kind === 'group' ? 'selected' : ''}
            onClick={() => setForm((f) => ({ ...f, kind: 'group' }))}
          >
            <Users size={22} />
            <strong>Private group</strong>
            <small>Invite your friends</small>
          </button>
        </div>
        <label>
          Space name
          <input
            name="name"
            required
            minLength={2}
            maxLength={80}
            value={form.name}
            onChange={change}
            placeholder={
              form.kind === 'group' ? 'e.g. Semester 3 study crew' : 'e.g. The music club'
            }
          />
        </label>
        <label>
          A little about it
          <textarea
            name="description"
            rows={3}
            maxLength={500}
            value={form.description}
            onChange={change}
            placeholder="What brings your people together?"
          />
        </label>
        <div className="form-row">
          <label>
            Interest
            <select name="topic" value={form.topic} onChange={change}>
              {[
                'Campus life',
                'Technology',
                'Art & design',
                'Academics',
                'Books & culture',
                'Outdoors',
                'Music',
                'Sports',
                'Projects',
              ].map((t) => (
                <option key={t}>{t}</option>
              ))}
            </select>
          </label>
          {form.kind === 'group' && (
            <label>
              Group type
              <select name="category" value={form.category} onChange={change}>
                <option value="interest">Interest</option>
                <option value="general">General</option>
                {user.role !== 'student' && <option value="section">Section</option>}
              </select>
            </label>
          )}
        </div>
        {form.kind === 'group' && form.category === 'section' ? (
          <div className="form-row">
            <label>
              Section
              <select name="section" value={form.section} onChange={change} required>
                <option value="">Choose section</option>
                {sections.map((s) => (
                  <option key={s._id}>{s.code}</option>
                ))}
              </select>
            </label>
            <label>
              Year
              <select name="year" value={form.year} onChange={change}>
                {[1, 2, 3, 4].map((y) => (
                  <option key={y} value={y}>
                    Year {y}
                  </option>
                ))}
              </select>
            </label>
          </div>
        ) : (
          form.kind === 'group' && (
            <fieldset className="friend-picker">
              <legend>Invite friends</legend>
              {friends.length ? (
                friends.map((f) => (
                  <label key={f._id}>
                    <input
                      type="checkbox"
                      checked={form.memberIds.includes(f.user._id)}
                      onChange={(e) =>
                        setForm((current) => ({
                          ...current,
                          memberIds: e.target.checked
                            ? [...current.memberIds, f.user._id]
                            : current.memberIds.filter((id) => id !== f.user._id),
                        }))
                      }
                    />
                    <Avatar user={f.user} size="xs" />
                    <span>{fullName(f.user)}</span>
                  </label>
                ))
              ) : (
                <p className="form-help">Add campus friends from the People page to invite them.</p>
              )}
            </fieldset>
          )
        )}
        <fieldset className="color-picker">
          <legend>Make it yours</legend>
          {['sage', 'peach', 'lavender', 'blue', 'gold'].map((color) => (
            <button
              type="button"
              aria-label={`${color} color`}
              aria-pressed={form.color === color}
              key={color}
              className={`tone-${color} ${form.color === color ? 'selected' : ''}`}
              onClick={() => setForm((f) => ({ ...f, color }))}
            >
              {form.color === color ? '✓' : ''}
            </button>
          ))}
        </fieldset>
        <button className="button primary full-width" disabled={busy}>
          {busy ? 'Creating…' : 'Create my space'}
          <ArrowUpRight size={17} />
        </button>
      </form>
    </Modal>
  );
}
