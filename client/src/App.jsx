import { useState } from 'react';
import {
  NavLink,
  Navigate,
  Outlet,
  Route,
  Routes,
  useLocation,
  useNavigate,
} from 'react-router-dom';
import {
  Bell,
  BookOpen,
  ChevronDown,
  Compass,
  GraduationCap,
  LayoutDashboard,
  LogOut,
  Menu,
  MessageCircle,
  Moon,
  Plus,
  Search,
  Settings,
  Shield,
  Sparkles,
  Sun,
  Users,
  X,
  CheckCircle2,
  AlertCircle,
} from 'lucide-react';
import { useApp, useResource } from './state.jsx';
import { Avatar, Brand, Loading, Modal, fullName } from './components.jsx';
import { AuthPage } from './pages/AuthPage.jsx';
import { Dashboard } from './pages/Dashboard.jsx';
import { Communities, CreateCommunity } from './pages/Communities.jsx';
import { People } from './pages/People.jsx';
import { Messages } from './pages/Messages.jsx';
import { Profile } from './pages/Profile.jsx';
import { Admin, Reports } from './pages/Admin.jsx';

function Layout() {
  const { user, signOut, notify, theme, setTheme, toast, setToast } = useApp();
  const navigate = useNavigate();
  const location = useLocation();
  const [mobile, setMobile] = useState(false);
  const [create, setCreate] = useState(false);
  const [guidelines, setGuidelines] = useState(false);
  const [search, setSearch] = useState('');
  const { data: conversations } = useResource('/conversations');
  const { data: friends } = useResource('/friends');
  const unread = conversations.reduce((n, c) => n + c.unread, 0);
  const requests = friends.filter((f) => f.status === 'pending' && f.incoming).length;
  const titles = {
    '/': 'Your campus',
    '/groups': 'My groups',
    '/discover': 'Discover',
    '/people': 'People',
    '/profile': 'My profile',
    '/admin': 'Administration',
    '/reports': 'Moderation',
  };
  const title = location.pathname.startsWith('/messages')
    ? 'Conversations'
    : titles[location.pathname] || 'Campus Chat';
  const closeMobile = () => setMobile(false);
  const logout = async () => {
    try {
      await signOut();
      navigate('/login');
    } catch (e) {
      notify(e.message, 'error');
    }
  };
  const nav = (to, Icon, label, badge) => (
    <NavLink
      to={to}
      end={to === '/'}
      onClick={closeMobile}
      className={({ isActive }) => `nav-item ${isActive ? 'active' : ''}`}
    >
      <Icon size={19} />
      <span>{label}</span>
      {badge > 0 && <span className="nav-badge">{badge > 99 ? '99+' : badge}</span>}
    </NavLink>
  );
  return (
    <div className="app-shell">
      {mobile && (
        <button className="sidebar-scrim" onClick={closeMobile} aria-label="Close navigation" />
      )}
      <aside className={`sidebar ${mobile ? 'mobile-open' : ''}`}>
        <Brand />
        <div className="workspace-label">
          <span className="workspace-icon">
            <GraduationCap size={17} />
          </span>
          <div>
            <strong>The campus space</strong>
            <small>Your everyday community</small>
          </div>
          <ChevronDown size={15} />
        </div>
        <div className="nav-section-label">WORKSPACE</div>
        <nav>
          {nav('/', LayoutDashboard, 'Overview')}
          {nav('/messages', MessageCircle, 'Messages', unread)}
          {nav('/groups', Users, 'My groups')}
          {nav('/discover', Compass, 'Discover')}
        </nav>
        <div className="nav-section-label nav-label-second">CAMPUS</div>
        <nav>
          {nav('/people', GraduationCap, 'People', requests)}
          {['lecturer', 'admin'].includes(user.role) && nav('/reports', Shield, 'Moderation')}
          {user.role === 'admin' && nav('/admin', Settings, 'Administration')}
          {nav('/profile', Settings, 'My profile')}
        </nav>
        <div className="sidebar-bottom">
          <div className="sidebar-note">
            <span>
              <Sparkles size={18} />
              Better, together.
            </span>
            <p>
              Good conversations make
              <br />a great campus.
            </p>
            <button onClick={() => setGuidelines(true)}>
              Our community values <span>↗</span>
            </button>
          </div>
          <div className="sidebar-profile">
            <button
              className="profile-link"
              onClick={() => {
                navigate('/profile');
                closeMobile();
              }}
            >
              <Avatar user={user} size="sm" />
              <span>
                <strong>{fullName(user)}</strong>
                <small>
                  {user.role === 'student'
                    ? `${user.section} · Year ${user.year}`
                    : user.role === 'lecturer'
                      ? 'Faculty member'
                      : 'Campus administrator'}
                </small>
              </span>
            </button>
            <button className="icon-button" onClick={logout} aria-label="Sign out" title="Sign out">
              <LogOut size={17} />
            </button>
          </div>
        </div>
      </aside>
      <div className="workspace">
        <header className="topbar">
          <div className="topbar-title">
            <button
              className="icon-button mobile-menu"
              onClick={() => setMobile(true)}
              aria-label="Open navigation"
            >
              <Menu size={21} />
            </button>
            <span>
              Workspace <span className="breadcrumb-slash">/</span>
              <strong>{title}</strong>
            </span>
          </div>
          <form
            className="global-search"
            onSubmit={(e) => {
              e.preventDefault();
              navigate(`/people?q=${encodeURIComponent(search)}`);
            }}
          >
            <Search size={17} />
            <input
              aria-label="Search people on campus"
              placeholder="Search your campus…"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
            <kbd>↵</kbd>
          </form>
          <div className="topbar-actions">
            <button
              className="icon-button"
              aria-label={theme === 'light' ? 'Switch to dark theme' : 'Switch to light theme'}
              onClick={() => setTheme(theme === 'light' ? 'dark' : 'light')}
            >
              {theme === 'light' ? <Moon size={19} /> : <Sun size={19} />}
            </button>
            <button
              className="icon-button notification-button"
              onClick={() => navigate('/people?tab=requests')}
              aria-label={`Friend requests${requests ? `, ${requests} pending` : ''}`}
            >
              <Bell size={19} />
              {requests > 0 && <i />}
            </button>
            <span className="topbar-divider" />
            <Avatar user={user} size="sm" />
          </div>
        </header>
        <main
          className={`main-content ${location.pathname.startsWith('/messages') ? 'main-messages' : ''}`}
        >
          <Outlet context={{ openCreate: () => setCreate(true) }} />
        </main>
        <button
          className="floating-create"
          onClick={() => setCreate(true)}
          aria-label="Create a community"
        >
          <Plus size={22} />
        </button>
      </div>
      {create && <CreateCommunity onClose={() => setCreate(false)} />}
      {guidelines && (
        <Modal
          title="A campus for everyone."
          subtitle="A few things that make this a good place to be."
          onClose={() => setGuidelines(false)}
        >
          <div className="values-list">
            <p>
              <strong>Be kind, stay curious.</strong>
              <br />
              Respect different perspectives. Make room for others.
            </p>
            <p>
              <strong>Share thoughtfully.</strong>
              <br />
              Keep private information private and give credit to the people behind ideas.
            </p>
            <p>
              <strong>Look out for each other.</strong>
              <br />
              Report messages that cross a line. Your selected faculty member can review the real
              sender, including anonymous messages.
            </p>
          </div>
          <button className="button primary full-width" onClick={() => setGuidelines(false)}>
            Sounds good <CheckCircle2 size={17} />
          </button>
        </Modal>
      )}
      {toast && (
        <div className={`toast ${toast.type}`} role={toast.type === 'error' ? 'alert' : 'status'}>
          {toast.type === 'error' ? <AlertCircle size={20} /> : <CheckCircle2 size={20} />}
          <span>{toast.message}</span>
          <button onClick={() => setToast(null)} aria-label="Dismiss notification">
            <X size={17} />
          </button>
        </div>
      )}
    </div>
  );
}
function Protected() {
  const { user, loading } = useApp();
  return loading ? <Loading /> : user ? <Layout /> : <Navigate to="/login" replace />;
}
function Restricted({ roles, children }) {
  const { user } = useApp();
  return roles.includes(user.role) ? children : <Navigate to="/" replace />;
}
export function App() {
  return (
    <Routes>
      <Route path="/login" element={<AuthPage />} />
      <Route path="/register" element={<AuthPage register />} />
      <Route element={<Protected />}>
        <Route index element={<Dashboard />} />
        <Route path="messages/:id?" element={<Messages />} />
        <Route path="groups" element={<Communities groups />} />
        <Route path="discover" element={<Communities />} />
        <Route path="people" element={<People />} />
        <Route path="profile" element={<Profile />} />
        <Route
          path="admin"
          element={
            <Restricted roles={['admin']}>
              <Admin />
            </Restricted>
          }
        />
        <Route
          path="reports"
          element={
            <Restricted roles={['admin', 'lecturer']}>
              <Reports />
            </Restricted>
          }
        />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
