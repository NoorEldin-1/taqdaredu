import { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import type { User } from '@/lib/api/types';

interface AuthContextType {
  user: User | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  login: (user: User, remember?: boolean) => void;
  updateUser: (patch: Partial<User>) => void;
  logout: () => void;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const AuthProvider = ({ children }: { children: ReactNode }) => {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    // Check for stored user on mount (persistent localStorage first, then session-only)
    const storedUser = localStorage.getItem('user') || sessionStorage.getItem('user');
    const token = localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token');

    if (storedUser && token) {
      try {
        setUser(JSON.parse(storedUser));
      } catch {
        localStorage.removeItem('user');
        localStorage.removeItem('auth_token');
        sessionStorage.removeItem('user');
        sessionStorage.removeItem('auth_token');
      }
    }
    setIsLoading(false);
  }, []);

  const login = (userData: User, remember: boolean = true) => {
    setUser(userData);
    // "Remember me" → persist across sessions in localStorage; otherwise keep only for this tab session
    const store = remember ? localStorage : sessionStorage;
    const other = remember ? sessionStorage : localStorage;
    other.removeItem('user');
    other.removeItem('auth_token');
    store.setItem('user', JSON.stringify(userData));
    if (userData.token) {
      store.setItem('auth_token', userData.token);
    }
  };

  // Merge a partial update into the current user and re-persist to whichever
  // store currently holds the session (localStorage for "remember me").
  const updateUser = (patch: Partial<User>) => {
    setUser((prev) => {
      if (!prev) return prev;
      const merged = { ...prev, ...patch };
      const store = localStorage.getItem('auth_token') ? localStorage : sessionStorage;
      store.setItem('user', JSON.stringify(merged));
      return merged;
    });
  };

  const logout = () => {
    setUser(null);
    localStorage.removeItem('user');
    localStorage.removeItem('auth_token');
    sessionStorage.removeItem('user');
    sessionStorage.removeItem('auth_token');
  };

  return (
    <AuthContext.Provider
      value={{
        user,
        isAuthenticated: !!user,
        isLoading,
        login,
        updateUser,
        logout,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};
