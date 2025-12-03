import { useEffect, useState } from 'react';
import { Link, Navigate } from 'react-router-dom';
import { ArrowRight, Check, Eye, EyeOff, GraduationCap, Shield, Sparkles } from 'lucide-react';
import { api, useApp } from '../state.jsx';
import { Brand, CampusArt, ErrorMessage, Loading } from '../components.jsx';

export function AuthPage({ register = false }) {
  const { user, loading, signIn } = useApp();
  const [form, setForm] = useState({
    firstName: '',
    lastName: '',
    email: '',
    password: '',
    confirm: '',
    role: 'student',
    section: '',
    year: '3',
    rollNumber: '',
  });
  const [sections, setSections] = useState([]);
  const [config, setConfig] = useState({ demo: false, domains: [] });
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [visible, setVisible] = useState(false);
  useEffect(() => {
    api('/auth/sections')
      .then(setSections)
      .catch((e) => setError(e.message));
    api('/auth/config')
      .then(setConfig)
      .catch(() => {});
  }, []);
  const change = (e) => setForm((f) => ({ ...f, [e.target.name]: e.target.value }));
  const submit = async (e) => {
    e.preventDefault();
    setError('');
    if (register && form.password !== form.confirm) {
      setError('Your passwords do not match.');
      return;
    }
    setBusy(true);
    try {
      const body = register
        ? {
            firstName: form.firstName,
            lastName: form.lastName,
            email: form.email,
            password: form.password,
            role: form.role,
            ...(form.role === 'student'
              ? {
                  section: form.section,
                  year: Number(form.year),
                  rollNumber: form.rollNumber,
                }
              : {}),
          }
        : { email: form.email, password: form.password };
      await signIn(register ? '/auth/register' : '/auth/login', body);
    } catch (e) {
      setError(e.message);
    } finally {
      setBusy(false);
    }
  };
  const demo = async (role) => {
    setBusy(true);
    setError('');
    try {
      await signIn('/auth/demo', { role });
    } catch (e) {
      setError(e.message);
    } finally {
      setBusy(false);
    }
  };
  if (loading) return <Loading />;
  if (user) return <Navigate to="/" replace />;
  return (
    <div className="auth-page">
      <section className="auth-story">
        <Brand />
        <div className="auth-story-content">
          <span className="eyebrow">
            <span className="little-star">✳</span> A LITTLE CLOSER, EVERY DAY
          </span>
          <h1>
            Your campus.
            <br />
            Your people.
            <br />
            <em>Your space.</em>
          </h1>
          <p>
            From your first “hey” to your next big idea.
            <br />
            Find the people who make campus feel like home.
          </p>
          <CampusArt />
          <div className="auth-story-footer">
            <span>
              <Check size={16} /> Made for your campus
            </span>
            <span>
              <Shield size={16} /> A thoughtful, safer space
            </span>
          </div>
        </div>
        <div className="auth-copyright">
          CAMPUS CHAT <span>Built for the moments in between.</span>
        </div>
      </section>
      <section className="auth-form-panel">
        <div className="auth-mobile-brand">
          <Brand />
        </div>
        <div className="auth-form-wrap">
          <span className="auth-icon">
            <GraduationCap size={26} />
          </span>
          <h2>{register ? 'Your people are here.' : 'Good to have you back.'}</h2>
          <p className="auth-intro">
            {register
              ? 'Create an account. Make yourself at home.'
              : 'Sign in and pick up where you left off.'}
          </p>
          <ErrorMessage message={error} />
          <form onSubmit={submit} className="form-stack">
            {register && (
              <div className="form-row">
                <label>
                  First name
                  <input
                    name="firstName"
                    value={form.firstName}
                    onChange={change}
                    autoComplete="given-name"
                    required
                    maxLength={50}
                    placeholder="Your first name"
                  />
                </label>
                <label>
                  Last name
                  <input
                    name="lastName"
                    value={form.lastName}
                    onChange={change}
                    autoComplete="family-name"
                    required
                    maxLength={50}
                    placeholder="Your last name"
                  />
                </label>
              </div>
            )}
            <label>
              College email
              <input
                name="email"
                type="email"
                value={form.email}
                onChange={change}
                autoComplete="email"
                required
                placeholder="you@college.edu"
              />
            </label>
            {register && (
              <>
                <label>
                  I’m joining as
                  <select name="role" value={form.role} onChange={change}>
                    <option value="student">Student</option>
                    <option value="lecturer">Lecturer / faculty</option>
                  </select>
                </label>
                {form.role === 'student' ? (
                  <>
                    <label>
                      Roll number
                      <input
                        name="rollNumber"
                        value={form.rollNumber}
                        onChange={change}
                        required
                        maxLength={30}
                        placeholder="Your student roll number"
                      />
                    </label>
                    <div className="form-row">
                      <label>
                        Section
                        <select
                          name="section"
                          aria-label="Section"
                          value={form.section}
                          onChange={change}
                          required
                        >
                          <option value="">Choose a section</option>
                          {sections.map((s) => (
                            <option key={s._id} value={s.code}>
                              {s.code}
                            </option>
                          ))}
                        </select>
                      </label>
                      <label>
                        Year
                        <select name="year" value={form.year} onChange={change}>
                          {[1, 2, 3, 4].map((year) => (
                            <option key={year} value={year}>
                              Year {year}
                            </option>
                          ))}
                        </select>
                      </label>
                    </div>
                  </>
                ) : (
                  <p className="form-help">
                    Use your faculty email, such as name@faculty.college.edu.
                  </p>
                )}
              </>
            )}
            <label>
              Password
              <div className="password-field">
                <input
                  name="password"
                  aria-label="Password"
                  type={visible ? 'text' : 'password'}
                  value={form.password}
                  onChange={change}
                  autoComplete={register ? 'new-password' : 'current-password'}
                  required
                  minLength={register ? 8 : undefined}
                  maxLength={72}
                  placeholder={register ? 'At least 8 characters' : 'Enter your password'}
                />
                <button
                  type="button"
                  className="icon-button"
                  onClick={() => setVisible(!visible)}
                  aria-label={visible ? 'Hide password' : 'Show password'}
                >
                  {visible ? <EyeOff size={18} /> : <Eye size={18} />}
                </button>
              </div>
            </label>
            {register && (
              <label>
                Confirm password
                <input
                  name="confirm"
                  type={visible ? 'text' : 'password'}
                  value={form.confirm}
                  onChange={change}
                  autoComplete="new-password"
                  required
                  placeholder="One more time"
                />
              </label>
            )}
            <button className="button primary full-width" disabled={busy}>
              {busy ? 'Just a moment…' : register ? 'Create my account' : 'Sign in'}
              <ArrowRight size={18} />
            </button>
          </form>
          <p className="auth-switch">
            {register ? 'Already part of the campus?' : 'New around here?'}{' '}
            <Link to={register ? '/login' : '/register'}>
              {register ? 'Sign in' : 'Join your campus'} <span>↗</span>
            </Link>
          </p>
          {config.demo && !register && (
            <div className="demo-section">
              <div className="line-label">
                <span />
                TAKE A LOOK AROUND
                <span />
              </div>
              <button
                className="button secondary full-width"
                disabled={busy}
                onClick={() => demo('student')}
              >
                <Sparkles size={17} /> Explore the student demo <ArrowRight size={17} />
              </button>
              <div className="demo-role-links">
                <button disabled={busy} onClick={() => demo('lecturer')}>
                  Faculty demo
                </button>
                <span>·</span>
                <button disabled={busy} onClick={() => demo('admin')}>
                  Admin demo
                </button>
              </div>
              <small>A fictional campus. Real conversations to explore.</small>
            </div>
          )}
          <p className="auth-terms">Be kind. Stay curious. Make yourself at home.</p>
        </div>
      </section>
    </div>
  );
}
