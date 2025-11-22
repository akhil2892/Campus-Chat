import { useEffect, useRef } from 'react';
import { Link } from 'react-router-dom';
import { ArrowUpRight, Check, Hash, MessageCircle, Sparkles, Users, X } from 'lucide-react';

export const fullName = user => `${user?.firstName || ''} ${user?.lastName || ''}`.trim();
export const initials = user => `${user?.firstName?.[0] || ''}${user?.lastName?.[0] || ''}` || '?';
export function relativeTime(date) {
  if (!date) return 'New';
  const minutes = Math.max(0, Math.floor((Date.now() - new Date(date)) / 60_000));
  if (minutes < 1) return 'Just now';
  if (minutes < 60) return `${minutes}m ago`;
  if (minutes < 1440) return `${Math.floor(minutes / 60)}h ago`;
  if (minutes < 10080) return `${Math.floor(minutes / 1440)}d ago`;
  return new Date(date).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });
}
export function Avatar({ user, size = 'md', online = false }) {
  return <span className={`avatar avatar-${size} tone-${user?.color || 'sage'}`} title={fullName(user)}>{user?.avatar ? <img src={user.avatar} alt={fullName(user)} /> : initials(user)}{online && <i className="online-dot" />}</span>;
}
export function Brand({ compact = false }) {
  return <Link className="brand" to="/"><span className="brand-symbol"><MessageCircle size={22} strokeWidth={2} /><span /></span>{!compact && <span>campus<span className="brand-light">chat</span><sup>✳</sup></span>}</Link>;
}
export function CampusArt({ className = '' }) {
  return <svg className={`campus-art ${className}`} viewBox="0 0 420 260" fill="none" aria-hidden="true">
    <ellipse cx="217" cy="233" rx="169" ry="13" fill="#C8DECA" opacity=".55" />
    <circle cx="335" cy="62" r="27" fill="#F3CE7C" /><path d="M50 58h50m-37-12h32m248 55h38" stroke="#A8C9B0" strokeWidth="3" strokeLinecap="round" />
    <path d="M83 214V107h74v107M267 214V107h74v107" fill="#DDE3C6" stroke="#537B62" strokeWidth="2.5" />
    <path d="m72 107 50-29 48 29m85 0 45-29 54 29" fill="#B5CEB3" stroke="#537B62" strokeWidth="2.5" strokeLinejoin="round" />
    <path d="M146 213V82h128v131" fill="#FCF6E5" stroke="#537B62" strokeWidth="2.5" />
    <path d="m136 83 74-46 75 46z" fill="#719979" stroke="#537B62" strokeWidth="2.5" strokeLinejoin="round" />
    <path d="M210 37V16m0 0h25l-5 8h-20" stroke="#537B62" strokeWidth="2.5" strokeLinejoin="round" /><path d="M211 17h21l-4 6h-17" fill="#EBAA80" />
    <circle cx="210" cy="76" r="14" fill="#F7E7BE" stroke="#537B62" strokeWidth="2" /><path d="M210 68v9l6 3" stroke="#537B62" strokeWidth="2" strokeLinecap="round" />
    {[97,125,281,309].map(x => <g key={x}><path d={`M${x} 127h13v20h-13zm0 37h13v20h-13z`} fill="#97B9A0" stroke="#537B62" strokeWidth="1.5" /></g>)}
    {[163,193,223,253].map(x => <path key={x} d={`M${x} 104h10v22h-10zm0 37h10v17h-10z`} fill="#ACCCB4" stroke="#537B62" strokeWidth="1.5" />)}
    <path d="M192 214v-34a18 18 0 0 1 36 0v34" fill="#76967B" stroke="#537B62" strokeWidth="2" /><path d="M210 165v49" stroke="#537B62" strokeWidth="2" />
    <path d="M136 213h148m-119 0-25 22h136l-26-22" stroke="#537B62" strokeWidth="2" fill="#EADCC2" /><path d="M157 221h106m-114 7h123" stroke="#BAAD92" strokeWidth="1.5" />
    <path d="M54 221v-64m-3 23-16-13m19 1 14-15" stroke="#537B62" strokeWidth="3" strokeLinecap="round" /><path d="M21 153c0-21 13-42 30-42s37 22 37 43-17 32-35 32-32-13-32-33Z" fill="#8DB396" /><path d="M52 144v77m0-48-15-15m15 3 12-14" stroke="#537B62" strokeWidth="2.5" strokeLinecap="round" />
    <path d="M365 222v-47" stroke="#537B62" strokeWidth="3" /><path d="M340 171c0-16 10-34 25-34s24 18 24 34-11 23-24 23-25-7-25-23Z" fill="#729C7B" /><path d="M365 160v64m0-43-10-11" stroke="#537B62" strokeWidth="2.5" strokeLinecap="round" />
    <path d="M83 225h27m196 0h24" stroke="#537B62" strokeWidth="2.5" strokeLinecap="round" /><path d="M96 219v-8m-5 3 5 5 5-5m216 5v-8m-5 3 5 5 5-5" stroke="#8DA780" strokeWidth="2" />
    <rect x="293" y="22" width="47" height="29" rx="9" fill="#FFF9EE" stroke="#789885" strokeWidth="1.5" /><path d="m303 50-3 8 13-7" fill="#FFF9EE" stroke="#789885" strokeWidth="1.5" /><path d="M305 34h20m-20 6h13" stroke="#789885" strokeWidth="2" strokeLinecap="round" />
    <circle cx="109" cy="50" r="13" fill="#E5AD86" /><path d="m105 49 4 4 7-7" stroke="#FFF9EE" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" />
  </svg>;
}
export function PageHeading({ eyebrow, title, description, action }) {
  return <div className="page-heading"><div>{eyebrow && <div className="eyebrow">{eyebrow}</div>}<h1>{title}</h1>{description && <p>{description}</p>}</div>{action}</div>;
}
export function Empty({ icon: Icon = MessageCircle, title, text, action }) {
  return <div className="empty-state"><span className="empty-icon"><Icon size={28} /></span><h3>{title}</h3><p>{text}</p>{action}</div>;
}
export function ErrorMessage({ message }) { return message ? <div className="error-message" role="alert">{message}</div> : null; }
export function Loading({ text = 'Getting things ready…' }) { return <div className="loading"><span className="spinner" />{text}</div>; }
export function Modal({ title, subtitle, children, onClose }) {
  const dialog = useRef(null);
  useEffect(() => {
    const previous = document.activeElement;
    const bodyOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    dialog.current?.focus();
    const key = event => {
      if (event.key === 'Escape') onClose();
      if (event.key === 'Tab') {
        const elements = [...dialog.current.querySelectorAll('button, input, select, textarea, a[href], [tabindex="0"]')].filter(el => !el.disabled);
        const first = elements[0]; const last = elements[elements.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
      }
    };
    document.addEventListener('keydown', key);
    return () => { document.removeEventListener('keydown', key); document.body.style.overflow = bodyOverflow; previous?.focus(); };
  }, [onClose]);
  return <div className="modal-backdrop" onMouseDown={e => { if (e.target === e.currentTarget) onClose(); }}><section className="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title" ref={dialog} tabIndex={-1}><button className="icon-button modal-close" onClick={onClose} aria-label="Close dialog"><X size={20} /></button><span className="modal-mark"><Sparkles size={22} /></span><h2 id="modal-title">{title}</h2>{subtitle && <p className="modal-subtitle">{subtitle}</p>}{children}</section></div>;
}
export function MemberStack({ members = [], count }) {
  return <div className="member-stack">{members.slice(0, 3).map(user => <Avatar key={user._id} user={user} size="xs" />)}<span>{count ?? members.length} members</span></div>;
}
export function CommunityCard({ community, onJoin, busy = false }) {
  return <article className={`community-card tone-border-${community.color}`}><div className="community-card-top"><span className={`community-icon tone-${community.color}`}>{community.kind === 'room' ? <Hash size={23} /> : <Users size={23} />}</span><span className="tag">{community.topic}</span></div><h3>{community.name}</h3><p>{community.description || 'A little space to connect, share, and learn together.'}</p><div className="community-card-bottom"><MemberStack members={community.members} count={community.memberCount} />{community.joined ? <Link className="circle-link" to={`/messages/${community._id}`} aria-label={`Open ${community.name}`}><ArrowUpRight size={19} /></Link> : <button className="join-button" disabled={busy} onClick={() => onJoin(community._id)}>{busy ? 'Joining…' : 'Join'}<span>+</span></button>}</div></article>;
}
export function SuccessMark() { return <span className="success-mark"><Check size={16} /></span>; }
