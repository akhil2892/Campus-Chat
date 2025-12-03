import { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react';
import { io } from 'socket.io-client';

export async function api(path, options = {}) {
  const isForm = options.body instanceof FormData;
  const response = await fetch(`/api${path}`, {
    credentials: 'include',
    ...options,
    headers: {
      ...(!isForm && options.body ? { 'Content-Type': 'application/json' } : {}),
      ...options.headers,
    },
    body: isForm ? options.body : options.body ? JSON.stringify(options.body) : undefined,
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(data.error || 'Unable to complete the request.');
  return data;
}
const Context = createContext(null);
const resourceCache = new Map();
export function AppProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [version, setVersion] = useState(0);
  const [online, setOnline] = useState([]);
  const [connected, setConnected] = useState(false);
  const [toast, setToast] = useState(null);
  const socket = useRef(null);
  const timer = useRef(null);
  const [theme, setTheme] = useState(() => localStorage.getItem('campus-theme') || 'light');
  const refresh = useCallback(() => setVersion((v) => v + 1), []);
  const notify = useCallback((message, type = 'success') => {
    clearTimeout(timer.current);
    setToast({ message, type });
    timer.current = setTimeout(() => setToast(null), 4500);
  }, []);
  useEffect(() => {
    let active = true;
    api('/auth/me')
      .then((u) => {
        if (active) setUser(u);
      })
      .catch(() => {})
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, []);
  useEffect(() => {
    document.documentElement.dataset.theme = theme;
    localStorage.setItem('campus-theme', theme);
  }, [theme]);
  useEffect(() => {
    if (!user?._id) return;
    const connection = io({ withCredentials: true });
    socket.current = connection;
    connection.on('connect', () => {
      setConnected(true);
      refresh();
    });
    connection.on('disconnect', () => setConnected(false));
    connection.on('presence', setOnline);
    connection.on('data:refresh', refresh);
    connection.on('message:new', (message) => {
      refresh();
      window.dispatchEvent(new CustomEvent('campus:message', { detail: message }));
    });
    connection.on('conversation:read', (detail) => {
      refresh();
      window.dispatchEvent(new CustomEvent('campus:read', { detail }));
    });
    connection.on('typing', (detail) =>
      window.dispatchEvent(new CustomEvent('campus:typing', { detail })),
    );
    return () => {
      connection.disconnect();
      socket.current = null;
      setOnline([]);
      setConnected(false);
    };
  }, [user?._id, refresh]);
  const signIn = async (path, body) => {
    const current = await api(path, { method: 'POST', body });
    setUser(current);
    refresh();
  };
  const signOut = async () => {
    await api('/auth/logout', { method: 'POST' });
    setUser(null);
    refresh();
  };
  return (
    <Context.Provider
      value={{
        user,
        setUser,
        loading,
        version,
        refresh,
        online,
        connected,
        socket,
        notify,
        toast,
        setToast,
        signIn,
        signOut,
        theme,
        setTheme,
      }}
    >
      {children}
    </Context.Provider>
  );
}
export const useApp = () => useContext(Context);
export function useResource(path, initial = []) {
  const { version, user } = useApp();
  const [data, setData] = useState(initial);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);
  const ref = useRef(initial);
  const previousPath = useRef(null);
  useEffect(() => {
    let active = true;
    if (previousPath.current !== path) {
      setData(ref.current);
      previousPath.current = path;
    }
    setLoading(true);
    setError('');
    if (!path) {
      setLoading(false);
      return;
    }
    const cacheKey = `${user?._id || 'guest'}:${version}:${path}`;
    if (!resourceCache.has(cacheKey)) {
      if (resourceCache.size > 100) resourceCache.clear();
      resourceCache.set(cacheKey, api(path));
    }
    resourceCache
      .get(cacheKey)
      .then((value) => {
        if (active) {
          setData(value);
          setError('');
        }
      })
      .catch((e) => {
        if (active) setError(e.message);
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, [path, version, user?._id]);
  return { data, setData, error, loading };
}
