import { Link, useOutletContext } from 'react-router-dom';
import {
  ArrowRight,
  ArrowUpRight,
  BookOpen,
  Compass,
  MessageCircle,
  Plus,
  Sparkles,
  Users,
} from 'lucide-react';
import { useApp, useResource } from '../state.jsx';
import {
  Avatar,
  CampusArt,
  CommunityCard,
  Empty,
  ErrorMessage,
  Loading,
  fullName,
  relativeTime,
} from '../components.jsx';

export function Dashboard() {
  const { user, online } = useApp();
  const { openCreate } = useOutletContext();
  const { data: conversations, loading, error } = useResource('/conversations');
  const { data: friends } = useResource('/friends');
  const direct = conversations.filter((c) => c.kind === 'direct');
  const communities = conversations
    .filter((c) => c.kind !== 'direct')
    .sort((a, b) => Number(b.category === 'section') - Number(a.category === 'section'));
  const groups = conversations.filter((c) => c.kind === 'group');
  const classGroup = groups.find(
    (c) => c.category === 'section' && c.section === user.section && c.year === user.year,
  );
  const friendCount = friends.filter((f) => f.status === 'accepted').length;
  const pending = friends.filter((f) => f.status === 'pending' && f.incoming).length;
  return (
    <div className="dashboard">
      <div className="dashboard-greeting">
        <div>
          <span className="eyebrow">YOUR LITTLE CORNER OF CAMPUS</span>
          <h1>
            Hey, {user.firstName}
            <span className="greeting-sun">✳</span>
          </h1>
          <p>Catch up with classmates, share notes, and make plans after class.</p>
        </div>
        <div className="date-label">
          <span className="date-dot" />
          {new Date().toLocaleDateString('en-IN', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            timeZone: 'Asia/Kolkata',
          })}
        </div>
      </div>
      <nav className="student-shortcuts" aria-label="Campus shortcuts">
        <Link to={classGroup ? `/messages/${classGroup._id}` : '/groups'}>
          <span className="shortcut-icon tone-sage">
            <BookOpen size={21} />
          </span>
          <span>
            <strong>{user.role === 'student' ? 'Open class chat' : 'Open your groups'}</strong>
            <small>
              {classGroup ? `${user.section} · Year ${user.year}` : 'Class & study groups'}
            </small>
          </span>
          <ArrowUpRight size={18} />
        </Link>
        <Link to="/people">
          <span className="shortcut-icon tone-blue">
            <Users size={21} />
          </span>
          <span>
            <strong>Find classmates</strong>
            <small>Connect before you chat</small>
          </span>
          <ArrowUpRight size={18} />
        </Link>
        <button onClick={() => openCreate('group')}>
          <span className="shortcut-icon tone-lavender">
            <Plus size={21} />
          </span>
          <span>
            <strong>Create a study group</strong>
            <small>Invite your campus friends</small>
          </span>
          <ArrowUpRight size={18} />
        </button>
      </nav>
      <section className="welcome-banner">
        <div className="welcome-copy">
          <span className="banner-label">
            <span />
            YOU BELONG HERE
          </span>
          <h2>
            Big campus.
            <br />
            <em>Closer connections.</em>
          </h2>
          <p>
            Study together. Find your club. Ask that question.
            <br className="desktop-break" /> There’s a place for every part of campus life.
          </p>
          <Link className="button primary" to="/discover">
            Discover campus rooms <ArrowUpRight size={18} />
          </Link>
        </div>
        <CampusArt />
        <span className="banner-stamp">
          a space for
          <br />
          <strong>all of us.</strong>
          <span>✳</span>
        </span>
      </section>
      <div className="stats-strip">
        <div>
          <span className="stat-icon tone-sage">
            <Users size={21} />
          </span>
          <div>
            <strong>{friendCount.toString().padStart(2, '0')}</strong>
            <span>Campus friends</span>
          </div>
          <Link to="/people" aria-label="See campus friends">
            <ArrowUpRight size={17} />
          </Link>
        </div>
        <div>
          <span className="stat-icon tone-lavender">
            <MessageCircle size={21} />
          </span>
          <div>
            <strong>
              {conversations
                .reduce((n, c) => n + c.unread, 0)
                .toString()
                .padStart(2, '0')}
            </strong>
            <span>Unread messages</span>
          </div>
          <Link to="/messages" aria-label="See unread messages">
            <ArrowUpRight size={17} />
          </Link>
        </div>
        <div>
          <span className="stat-icon tone-peach">
            <BookOpen size={21} />
          </span>
          <div>
            <strong>{groups.length.toString().padStart(2, '0')}</strong>
            <span>Groups you’re in</span>
          </div>
          <Link to="/groups" aria-label="See your groups">
            <ArrowUpRight size={17} />
          </Link>
        </div>
        <div>
          <span className="stat-icon tone-gold">
            <Sparkles size={21} />
          </span>
          <div>
            <strong>
              {communities
                .filter((c) => c.kind === 'room')
                .length.toString()
                .padStart(2, '0')}
            </strong>
            <span>Campus spaces</span>
          </div>
          <Link to="/discover" aria-label="See campus spaces">
            <ArrowUpRight size={17} />
          </Link>
        </div>
      </div>
      <ErrorMessage message={error} />
      {loading && conversations.length === 0 ? (
        <Loading />
      ) : (
        <>
          <section className="dashboard-section">
            <div className="section-heading">
              <div>
                <h2>
                  Your communities <span className="subtle-count">{communities.length}</span>
                </h2>
                <p>Your class group, project teams, and campus rooms.</p>
              </div>
              <Link className="text-link" to="/discover">
                View all <ArrowRight size={16} />
              </Link>
            </div>
            {communities.length ? (
              <div className="community-grid dashboard-community-grid">
                {communities.slice(0, 3).map((c) => (
                  <CommunityCard key={c._id} community={c} />
                ))}
              </div>
            ) : (
              <Empty
                icon={Users}
                title="Make your first connection."
                text="Discover a campus room or create a space of your own."
                action={
                  <Link className="button secondary" to="/discover">
                    Discover communities
                  </Link>
                }
              />
            )}
          </section>
          <div className="dashboard-bottom-grid">
            <section className="panel conversation-panel">
              <div className="section-heading">
                <div>
                  <h2>Recent conversations</h2>
                  <p>Pick up where you left off.</p>
                </div>
                <Link className="text-link" to="/messages">
                  See all <ArrowUpRight size={16} />
                </Link>
              </div>
              {direct.length ? (
                <div className="recent-list">
                  {direct.slice(0, 3).map((c) => {
                    const other = c.members.find((u) => u._id !== user._id);
                    return (
                      <Link className="recent-item" to={`/messages/${c._id}`} key={c._id}>
                        <Avatar user={other} online={online.includes(other?._id)} />
                        <div className="recent-text">
                          <strong>{c.name}</strong>
                          <p>
                            {c.lastMessage?.mine && 'You: '}
                            {c.lastMessage?.text || 'Say your first hello.'}
                          </p>
                        </div>
                        <div className="recent-meta">
                          <small>{relativeTime(c.lastMessage?.createdAt)}</small>
                          {c.unread > 0 && <span className="unread-pill">{c.unread}</span>}
                        </div>
                      </Link>
                    );
                  })}
                </div>
              ) : (
                <Empty
                  title="It starts with a hello."
                  text="Add a campus friend and start a conversation."
                  action={
                    <Link className="text-link" to="/people">
                      Find your people <ArrowRight size={15} />
                    </Link>
                  }
                />
              )}
            </section>
            <section className="connection-card">
              <div className="connection-doodle">
                <Users size={31} />
                <span>✳</span>
              </div>
              <span className="eyebrow">THERE’S MORE TO CAMPUS</span>
              <h2>
                A familiar face.
                <br />A new friend.
              </h2>
              <p>
                {pending
                  ? `${pending} people would like to connect with you. Your next good conversation might be waiting.`
                  : 'Say hello to someone new. The best connections often start small.'}
              </p>
              <Link className="button secondary" to={pending ? '/people?tab=requests' : '/people'}>
                {pending ? 'View friend requests' : 'Find your people'}
                <ArrowUpRight size={17} />
              </Link>
            </section>
          </div>
        </>
      )}
      <footer className="dashboard-footer">
        <span>Made for the moments in between.</span>
        <span>
          Campus Chat <span>✳</span>
        </span>
      </footer>
    </div>
  );
}
