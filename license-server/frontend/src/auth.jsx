import { createContext, useContext, useEffect, useState } from 'react';
import { get, post } from './api.js';
import {
  bootstrapAuthSession,
  clearAuthSessionState,
  loginWithPassword,
  logoutAuthSession,
  refreshAuthSession,
} from './auth-controller.js';
import { buildAuthContextValue } from './auth-context-value.js';
import { subscribeToInvalidAuthSession } from './auth-invalid-listener.js';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [admin, setAdmin] = useState(null);
  const [session, setSession] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let active = true;

    async function bootstrapSession() {
      await bootstrapAuthSession({
        getSession: get,
        setAdmin,
        setSession,
        setLoading,
        isActive() {
          return active;
        },
      });
    }

    bootstrapSession();
    const unsubscribeInvalidSession = subscribeToInvalidAuthSession({
      clearAuthState() {
        clearAuthSessionState({ setAdmin, setSession });
      },
      isActive() {
        return active;
      },
    });

    return () => {
      active = false;
      unsubscribeInvalidSession();
    };
  }, []);

  async function login(email, password) {
    return loginWithPassword({
      postSession: post,
      email,
      password,
      setAdmin,
      setSession,
    });
  }

  async function refreshSession() {
    return refreshAuthSession({
      getSession: get,
      setAdmin,
      setSession,
    });
  }

  async function logout() {
    return logoutAuthSession({
      postSession: post,
      setAdmin,
      setSession,
    });
  }

  const value = buildAuthContextValue({
    admin,
    session,
    loading,
    login,
    logout,
    refreshSession,
  });

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth deve ser usado dentro de AuthProvider');
  }

  return context;
}
