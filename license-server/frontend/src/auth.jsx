import { createContext, useContext, useEffect, useState } from 'react';
import { get, post } from './api';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [admin, setAdmin] = useState(null);
  const [session, setSession] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let active = true;

    async function bootstrapSession() {
      try {
        const data = await get('/auth/session', { skipAuthRedirect: true });
        if (!active) {
          return;
        }

        setAdmin(data.admin);
        setSession(data.session);
      } catch {
        if (!active) {
          return;
        }

        setAdmin(null);
        setSession(null);
      } finally {
        if (active) {
          setLoading(false);
        }
      }
    }

    function handleInvalidSession() {
      if (!active) {
        return;
      }

      setAdmin(null);
      setSession(null);
    }

    bootstrapSession();
    window.addEventListener('layer7:auth-invalid', handleInvalidSession);

    return () => {
      active = false;
      window.removeEventListener('layer7:auth-invalid', handleInvalidSession);
    };
  }, []);

  async function login(email, password) {
    const data = await post('/auth/login', { email, password }, { skipAuthRedirect: true });
    setAdmin(data.admin);
    setSession(data.session);
    return data;
  }

  async function refreshSession() {
    const data = await get('/auth/session', { skipAuthRedirect: true });
    setAdmin(data.admin);
    setSession(data.session);
    return data;
  }

  async function logout() {
    try {
      await post('/auth/logout', undefined, { skipAuthRedirect: true });
    } finally {
      setAdmin(null);
      setSession(null);
    }
  }

  const value = {
    admin,
    session,
    loading,
    isAuthenticated: !!admin,
    login,
    logout,
    refreshSession,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth deve ser usado dentro de AuthProvider');
  }

  return context;
}
