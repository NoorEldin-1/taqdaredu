import { describe, it, expect, beforeEach } from 'vitest';
import { renderHook, act, waitFor } from '@testing-library/react';
import { ReactNode } from 'react';
import { AuthProvider, useAuth } from './AuthContext';
import type { User } from '@/lib/api/types';

const wrapper = ({ children }: { children: ReactNode }) => <AuthProvider>{children}</AuthProvider>;

const makeUser = (over: Partial<User> = {}): User => ({
  id: 1,
  first_name: 'Jane',
  last_name: 'Doe',
  email: 'jane@example.com',
  token: 'jwt-token-123',
  ...over,
});

describe('AuthContext', () => {
  beforeEach(() => {
    localStorage.clear();
    sessionStorage.clear();
  });

  it('starts unauthenticated with user = null', async () => {
    const { result } = renderHook(() => useAuth(), { wrapper });
    await waitFor(() => expect(result.current.isLoading).toBe(false));

    expect(result.current.user).toBeNull();
    expect(result.current.isAuthenticated).toBe(false);
  });

  it('sets the user after login and reports isAuthenticated', async () => {
    const { result } = renderHook(() => useAuth(), { wrapper });
    await waitFor(() => expect(result.current.isLoading).toBe(false));

    act(() => result.current.login(makeUser()));

    expect(result.current.user).not.toBeNull();
    expect(result.current.user!.email).toBe('jane@example.com');
    expect(result.current.isAuthenticated).toBe(true);
  });

  it('persists to localStorage when remember = true', async () => {
    const { result } = renderHook(() => useAuth(), { wrapper });
    await waitFor(() => expect(result.current.isLoading).toBe(false));

    act(() => result.current.login(makeUser({ token: 'remember-token' }), true));

    expect(localStorage.getItem('auth_token')).toBe('remember-token');
    expect(sessionStorage.getItem('auth_token')).toBeNull();
    expect(JSON.parse(localStorage.getItem('user')!).email).toBe('jane@example.com');
  });

  it('persists to sessionStorage (only) when remember = false', async () => {
    const { result } = renderHook(() => useAuth(), { wrapper });
    await waitFor(() => expect(result.current.isLoading).toBe(false));

    act(() => result.current.login(makeUser({ token: 'session-token' }), false));

    expect(sessionStorage.getItem('auth_token')).toBe('session-token');
    expect(localStorage.getItem('auth_token')).toBeNull();
  });

  it('clears user and both stores on logout', async () => {
    const { result } = renderHook(() => useAuth(), { wrapper });
    await waitFor(() => expect(result.current.isLoading).toBe(false));

    act(() => result.current.login(makeUser()));
    act(() => result.current.logout());

    expect(result.current.user).toBeNull();
    expect(result.current.isAuthenticated).toBe(false);
    expect(localStorage.getItem('auth_token')).toBeNull();
    expect(localStorage.getItem('user')).toBeNull();
    expect(sessionStorage.getItem('auth_token')).toBeNull();
  });

  it('rehydrates the user from storage on mount', async () => {
    localStorage.setItem('auth_token', 'stored-token');
    localStorage.setItem('user', JSON.stringify(makeUser({ first_name: 'Restored' })));

    const { result } = renderHook(() => useAuth(), { wrapper });
    await waitFor(() => expect(result.current.isLoading).toBe(false));

    expect(result.current.isAuthenticated).toBe(true);
    expect(result.current.user!.first_name).toBe('Restored');
  });

  it('updateUser merges a patch and re-persists to the active store', async () => {
    const { result } = renderHook(() => useAuth(), { wrapper });
    await waitFor(() => expect(result.current.isLoading).toBe(false));

    act(() => result.current.login(makeUser(), true));
    act(() => result.current.updateUser({ title: 'Senior Engineer' }));

    expect(result.current.user!.title).toBe('Senior Engineer');
    expect(JSON.parse(localStorage.getItem('user')!).title).toBe('Senior Engineer');
  });

  it('useAuth throws when used outside an AuthProvider', () => {
    // Suppress the expected React error boundary console noise.
    expect(() => renderHook(() => useAuth())).toThrow(/within an AuthProvider/i);
  });
});
