import { useState } from 'react';
import { Link } from 'react-router-dom';
import {
  ArrowUpRight,
  Check,
  Edit3,
  GraduationCap,
  Hash,
  MessageCircle,
  Plus,
  Shield,
  Users,
} from 'lucide-react';
import { api, useApp, useResource } from '../state.jsx';
import {
  Avatar,
  Empty,
  ErrorMessage,
  Loading,
  Modal,
  PageHeading,
  fullName,
  relativeTime,
} from '../components.jsx';

export function Admin() {
  const { notify, refresh } = useApp();
  const { data, loading, error } = useResource('/admin', null);
  const { data: sections, error: sectionError } = useResource('/admin/sections');
  const [tab, setTab] = useState('Overview');
  const [edit, setEdit] = useState(null);
  const [busy, setBusy] = useState('');
  const activate = async (section) => {
    setBusy(section._id);
    try {
      await api(`/admin/sections/${section._id}`, {
        method: 'PATCH',
        body: { active: !section.active },
      });
      refresh();
      notify(`Section ${section.code} ${section.active ? 'deactivated' : 'activated'}.`);
    } catch (e) {
      notify(e.message, 'error');
    } finally {
      setBusy('');
    }
  };
  return (
    <>
      <PageHeading
        eyebrow="LOOKING AFTER YOUR CAMPUS"
        title="The bigger picture."
        description="A healthy community starts with a little care behind the scenes."
        action={
          <button className="button primary" onClick={() => setEdit({})}>
            <Plus size={17} />
            New section
          </button>
        }
      />
      <div className="filter-tabs admin-tabs">
        {['Overview', 'Sections', 'Activity'].map((value) => (
          <button
            key={value}
            className={tab === value ? 'selected' : ''}
            onClick={() => setTab(value)}
          >
            {value}
          </button>
        ))}
      </div>
      <ErrorMessage message={error || sectionError} />
      {loading && !data ? (
        <Loading />
      ) : data && tab === 'Overview' ? (
        <>
          <div className="admin-stat-grid">
            {[
              ['users', 'Active people', Users, 'sage'],
              ['groups', 'Class & private groups', GraduationCap, 'lavender'],
              ['rooms', 'Campus rooms', Hash, 'peach'],
              ['messages', 'Messages shared', MessageCircle, 'blue'],
            ].map(([key, label, Icon, color]) => (
              <div className="panel admin-stat" key={key}>
                <span className={`stat-icon tone-${color}`}>
                  <Icon size={22} />
                </span>
                <strong>{data.stats[key]}</strong>
                <p>{label}</p>
              </div>
            ))}
          </div>
          <div className="moderation-banner">
            <span className="stat-icon tone-gold">
              <Shield size={24} />
            </span>
            <div>
              <strong>
                {data.stats.pendingReports
                  ? `${data.stats.pendingReports} reports need a little attention.`
                  : 'Your moderation queue is all caught up.'}
              </strong>
              <p>Help keep conversations thoughtful and campus welcoming.</p>
            </div>
            <Link className="button secondary" to="/reports">
              View reports
              <ArrowUpRight size={17} />
            </Link>
          </div>
          <section className="panel table-panel">
            <div className="section-heading">
              <h2>Newest faces on campus</h2>
              <span className="tag">
                {data.stats.students} students · {data.stats.faculty} faculty
              </span>
            </div>
            <div className="table-scroll">
              <table>
                <thead>
                  <tr>
                    <th>Person</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Section</th>
                    <th>Joined</th>
                  </tr>
                </thead>
                <tbody>
                  {data.users.map((person) => (
                    <tr key={person._id}>
                      <td>
                        <span className="table-person">
                          <Avatar user={person} size="xs" />
                          {fullName(person)}
                        </span>
                      </td>
                      <td>{person.email}</td>
                      <td>
                        <span className="tag">{person.role}</span>
                      </td>
                      <td>{person.section || '—'}</td>
                      <td>{relativeTime(person.createdAt)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>
        </>
      ) : tab === 'Sections' ? (
        <section className="panel table-panel">
          <div className="section-heading">
            <h2>Campus sections</h2>
            <span className="tag">{sections.length} sections</span>
          </div>
          <div className="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>Code</th>
                  <th>Section name</th>
                  <th>Department</th>
                  <th>Students</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {sections.map((section) => (
                  <tr key={section._id}>
                    <td>
                      <strong>{section.code}</strong>
                    </td>
                    <td>{section.name}</td>
                    <td>{section.department}</td>
                    <td>{section.students}</td>
                    <td>
                      <span className={`status-tag ${section.active ? 'active' : ''}`}>
                        {section.active ? 'Active' : 'Inactive'}
                      </span>
                    </td>
                    <td>
                      <div className="table-actions">
                        <button
                          className="icon-button"
                          aria-label={`Edit ${section.code}`}
                          onClick={() => setEdit(section)}
                        >
                          <Edit3 size={16} />
                        </button>
                        <button
                          className="text-button"
                          disabled={busy === section._id}
                          onClick={() => activate(section)}
                        >
                          {section.active ? 'Deactivate' : 'Activate'}
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {!sections.length && (
            <Empty
              icon={GraduationCap}
              title="Your campus starts here."
              text="Create a section so students can join their class."
            />
          )}
        </section>
      ) : tab === 'Activity' && data ? (
        <section className="panel activity-panel">
          <h2>A little behind the scenes</h2>
          {data.logs.length ? (
            data.logs.map((log) => (
              <div className="activity-item" key={log._id}>
                <span className="activity-icon">
                  <Check size={16} />
                </span>
                <div>
                  <strong>{log.action}</strong>
                  <p>{log.detail}</p>
                  <small>
                    {fullName(log.actor)} · {relativeTime(log.createdAt)}
                  </small>
                </div>
              </div>
            ))
          ) : (
            <Empty title="A fresh start." text="Administrative actions will appear here." />
          )}
        </section>
      ) : null}
      {edit && <EditSection section={edit} onClose={() => setEdit(null)} />}
    </>
  );
}
function EditSection({ section, onClose }) {
  const { refresh, notify } = useApp();
  const [form, setForm] = useState({
    code: section.code || '',
    name: section.name || '',
    department: section.department || '',
  });
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const submit = async (e) => {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      await api(section._id ? `/admin/sections/${section._id}` : '/admin/sections', {
        method: section._id ? 'PATCH' : 'POST',
        body: form,
      });
      refresh();
      notify(section._id ? 'Section updated.' : 'A new section is ready for students.');
      onClose();
    } catch (e) {
      setError(e.message);
    } finally {
      setBusy(false);
    }
  };
  return (
    <Modal
      title={section._id ? 'A little update.' : 'Make room for a new class.'}
      subtitle="Sections connect students to their automatic class groups."
      onClose={onClose}
    >
      <ErrorMessage message={error} />
      <form onSubmit={submit} className="form-stack">
        {[
          ['code', 'Section code', 'e.g. CSE-C'],
          ['name', 'Section name', 'e.g. Computer Science C'],
          ['department', 'Department', 'e.g. Computer Science & Engineering'],
        ].map(([key, label, placeholder]) => (
          <label key={key}>
            {label}
            <input
              value={form[key]}
              onChange={(e) => setForm((f) => ({ ...f, [key]: e.target.value }))}
              placeholder={placeholder}
              maxLength={key === 'code' ? 15 : 80}
              required
            />
          </label>
        ))}
        <button className="button primary full-width" disabled={busy}>
          {busy ? 'Saving…' : 'Save section'}
          <Check size={17} />
        </button>
      </form>
    </Modal>
  );
}
export function Reports() {
  const { refresh, notify } = useApp();
  const { data, loading, error } = useResource('/reports');
  const [tab, setTab] = useState('pending');
  const [busy, setBusy] = useState('');
  const reports = data.filter((report) => report.status === tab);
  const update = async (report) => {
    setBusy(report._id);
    try {
      await api(`/reports/${report._id}`, {
        method: 'PATCH',
        body: { status: report.status === 'pending' ? 'reviewed' : 'pending' },
      });
      refresh();
      notify(
        report.status === 'pending'
          ? 'Report marked as reviewed.'
          : 'Report returned to the queue.',
      );
    } catch (e) {
      notify(e.message, 'error');
    } finally {
      setBusy('');
    }
  };
  return (
    <>
      <PageHeading
        eyebrow="A THOUGHTFUL, SAFER SPACE"
        title="Look out for your people."
        description="Review reported messages with care. Keep campus conversations welcoming."
      />
      <div className="filter-tabs admin-tabs">
        {['pending', 'reviewed'].map((value) => (
          <button
            key={value}
            className={tab === value ? 'selected' : ''}
            onClick={() => setTab(value)}
          >
            {value === 'pending' ? 'Needs review' : 'Reviewed'}
            <span className="tab-count">{data.filter((r) => r.status === value).length}</span>
          </button>
        ))}
      </div>
      <ErrorMessage message={error} />
      {loading && !data.length ? (
        <Loading />
      ) : reports.length ? (
        <div className="reports-list">
          {reports.map((report) => (
            <article className="panel report-card" key={report._id}>
              <div className="report-heading">
                <span className={`status-tag ${report.status === 'reviewed' ? 'active' : ''}`}>
                  {report.status === 'reviewed' ? 'Reviewed' : 'Needs review'}
                </span>
                <small>{relativeTime(report.createdAt)}</small>
              </div>
              <h3>{report.reason}</h3>
              <div className="reported-preview">{report.message.text || 'Attachment message'}</div>
              {report.details && <p className="report-context">{report.details}</p>}
              <div className="report-people">
                <div>
                  <span>Reported by</span>
                  <strong>{fullName(report.reporter)}</strong>
                </div>
                <div>
                  <span>
                    Message sender
                    {report.message.anonymous ? ' · Anonymous to peers' : ''}
                  </span>
                  <strong>{fullName(report.message.sender)}</strong>
                </div>
                <div>
                  <span>Conversation</span>
                  <strong>{report.message.conversation}</strong>
                </div>
              </div>
              <div className="report-footer">
                <span>Assigned to {fullName(report.faculty)}</span>
                <button
                  className="button secondary"
                  disabled={busy === report._id}
                  onClick={() => update(report)}
                >
                  {busy === report._id
                    ? 'Updating…'
                    : report.status === 'pending'
                      ? 'Mark reviewed'
                      : 'Return to queue'}
                  <Check size={16} />
                </button>
              </div>
            </article>
          ))}
        </div>
      ) : (
        <Empty
          icon={Shield}
          title={tab === 'pending' ? 'A little peace of mind.' : 'Nothing reviewed yet.'}
          text={
            tab === 'pending'
              ? 'Your queue is clear. New reports assigned to you will appear here.'
              : 'Reports you review will appear here.'
          }
        />
      )}
    </>
  );
}
