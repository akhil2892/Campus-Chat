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
  const communities = conversations.filter((c) => c.kind !== 'direct');
  const groups = conversations.filter((c) => c.kind === 'group');
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
          <p>A new day. A good conversation. A little more connection.</p>
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
            Find your community, share a little of your world,
            <br className="desktop-break" /> and turn familiar faces into friends.
          </p>
          <Link className="button primary" to="/discover">
            Explore your campus <ArrowUpRight size={18} />
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
                <p>Good company, shared interests.</p>
              </div>
              <Link className="text-link" to="/groups">
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
                  <p>A little catching up goes a long way.</p>
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
