import { useEffect, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { ArrowUpRight, Check, MessageCircle, Search, UserPlus, Users, X } from 'lucide-react';
import { api, useApp, useResource } from '../state.jsx';
import {
  Avatar,
  Empty,
  ErrorMessage,
  Loading,
  PageHeading,
  fullName,
  relativeTime,
} from '../components.jsx';

export function People() {
  const { notify, refresh, online } = useApp();
  const [params, setParams] = useSearchParams();
  const [query, setQuery] = useState(params.get('q') || '');
  const [debounced, setDebounced] = useState(query);
  const [busy, setBusy] = useState('');
  const { data: people, error, loading } = useResource(`/users?q=${encodeURIComponent(debounced)}`);
  const { data: friends } = useResource('/friends');
  const tab = params.get('tab') || 'everyone';
  const requests = friends.filter((f) => f.status === 'pending' && f.incoming);
  const navigate = useNavigate();
  useEffect(() => {
    setQuery(params.get('q') || '');
  }, [params.get('q')]);
  useEffect(() => {
    const timer = setTimeout(() => setDebounced(query), 250);
    return () => clearTimeout(timer);
  }, [query]);
  const action = async (key, work, message) => {
    setBusy(key);
    try {
      await work();
      refresh();
      if (message) notify(message);
    } catch (e) {
      notify(e.message, 'error');
    } finally {
      setBusy('');
    }
  };
  const chat = (person) =>
    action(person._id, async () => {
      const conversation = await api('/conversations/direct', {
        method: 'POST',
        body: { userId: person._id },
      });
      navigate(`/messages/${conversation._id}`);
    });
  const respond = (request, accept) =>
    action(
      request._id,
      () =>
        api(`/friends/${request._id}`, {
          method: 'PATCH',
          body: { action: accept ? 'accept' : 'reject' },
        }),
      accept ? 'A new campus connection. Say hello!' : 'Friend request declined.',
    );
  const visible = people.filter((person) =>
    tab === 'friends' ? person.friendship === 'friends' : true,
  );
  return (
    <>
      <PageHeading
        eyebrow="FAMILIAR FACES. NEW CONNECTIONS."
        title="Find classmates & friends."
        description="Send a friend request. Once it’s accepted, you can chat and invite them to a group."
      />
      <div className="filter-bar">
        <div className="filter-tabs">
          {[
            ['everyone', 'Everyone'],
            ['friends', 'My friends'],
            ['requests', 'Requests'],
          ].map(([value, label]) => (
            <button
              key={value}
              className={tab === value ? 'selected' : ''}
              onClick={() => setParams({ tab: value })}
            >
              {label}
              {value === 'requests' && requests.length > 0 && (
                <span className="tab-count">{requests.length}</span>
              )}
            </button>
          ))}
        </div>
        {tab !== 'requests' && (
          <label className="search-field">
            <Search size={17} />
            <input
              aria-label="Search campus people"
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder="A name, an email…"
            />
          </label>
        )}
      </div>
      <ErrorMessage message={error} />
      {tab === 'requests' ? (
        requests.length ? (
          <div className="request-list">
            {requests.map((request) => (
              <article className="request-card" key={request._id}>
                <Avatar user={request.user} size="lg" />
                <div>
                  <h3>{fullName(request.user)}</h3>
                  <p>
                    {request.user.section
                      ? `${request.user.section} · Year ${request.user.year}`
                      : 'Faculty member'}
                    <span> · {relativeTime(request.createdAt)}</span>
                  </p>
                  <small>Would like to connect with you.</small>
                </div>
                <div className="request-actions">
                  <button
                    className="button primary"
                    disabled={busy === request._id}
                    onClick={() => respond(request, true)}
                  >
                    <Check size={17} />
                    Accept
                  </button>
                  <button
                    className="icon-button bordered"
                    disabled={busy === request._id}
                    onClick={() => respond(request, false)}
                    aria-label={`Decline request from ${fullName(request.user)}`}
                  >
                    <X size={18} />
                  </button>
                </div>
              </article>
            ))}
          </div>
        ) : (
          <Empty
            icon={UserPlus}
            title="All caught up."
            text="New friend requests will appear here. Say hello to someone on campus."
          />
        )
      ) : loading && !people.length ? (
        <Loading />
      ) : visible.length ? (
        <div className="people-grid">
          {visible.map((person) => (
            <article className="person-card" key={person._id}>
              <div className="person-card-top">
                <Avatar user={person} size="lg" online={online.includes(person._id)} />
                <span className={`tag ${person.role !== 'student' ? 'faculty-tag' : ''}`}>
                  {person.role === 'student'
                    ? person.section
                    : person.role === 'lecturer'
                      ? 'Faculty'
                      : 'Admin'}
                </span>
              </div>
              <h3>{fullName(person)}</h3>
              <span className="person-meta">
                {person.role === 'student'
                  ? `Year ${person.year} · ${person.section}`
                  : 'Here for the campus community'}
              </span>
              <p>{person.bio || 'A familiar face, a new connection. Say hello.'}</p>
              {person.friendship === 'friends' ? (
                <button
                  className="button secondary full-width"
                  disabled={busy === person._id}
                  onClick={() => chat(person)}
                >
                  <MessageCircle size={16} />
                  Message
                  <ArrowUpRight size={16} />
                </button>
              ) : person.friendship === 'received' ? (
                <button
                  className="button secondary full-width"
                  onClick={() => setParams({ tab: 'requests' })}
                >
                  Respond to request
                  <ArrowUpRight size={16} />
                </button>
              ) : (
                <button
                  className={`button full-width ${person.friendship === 'sent' ? 'quiet' : 'secondary'}`}
                  disabled={person.friendship === 'sent' || busy === person._id}
                  onClick={() =>
                    action(
                      person._id,
                      () =>
                        api('/friends', {
                          method: 'POST',
                          body: { userId: person._id },
                        }),
                      'Friend request sent. A hello is on its way.',
                    )
                  }
                >
                  <UserPlus size={16} />
                  {person.friendship === 'sent'
                    ? 'Request sent'
                    : busy === person._id
                      ? 'Sending…'
                      : 'Add friend'}
                </button>
              )}
            </article>
          ))}
        </div>
      ) : (
        <Empty
          icon={Users}
          title={query ? 'No familiar faces yet.' : 'Your people are out there.'}
          text={
            query
              ? 'Try another name or email address.'
              : 'Explore the campus directory and make your first connection.'
          }
        />
      )}
    </>
  );
}
