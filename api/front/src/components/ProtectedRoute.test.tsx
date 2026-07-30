import { describe, it, expect, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import { AuthProvider } from '@/contexts/AuthContext';
import ProtectedRoute from './ProtectedRoute';
import type { User } from '@/lib/api/types';

// Renders the app's routing tree around ProtectedRoute so we can assert the
// real redirect-to-/login vs render-children behaviour.
const renderAt = (path: string) =>
  render(
    <AuthProvider>
      <MemoryRouter initialEntries={[path]}>
        <Routes>
          <Route path="/login" element={<div>Login Page</div>} />
          <Route
            path="/profile"
            element={
              <ProtectedRoute>
                <div>Secret Profile</div>
              </ProtectedRoute>
            }
          />
        </Routes>
      </MemoryRouter>
    </AuthProvider>
  );

const seedSession = (user: Partial<User> = {}) => {
  localStorage.setItem('auth_token', 'token-abc');
  localStorage.setItem(
    'user',
    JSON.stringify({ id: 1, first_name: 'Jane', last_name: 'Doe', email: 'jane@example.com', token: 'token-abc', ...user })
  );
};

describe('ProtectedRoute', () => {
  beforeEach(() => {
    localStorage.clear();
    sessionStorage.clear();
  });

  it('redirects to /login when the user is not authenticated', async () => {
    renderAt('/profile');
    await waitFor(() => expect(screen.getByText('Login Page')).toBeInTheDocument());
    expect(screen.queryByText('Secret Profile')).not.toBeInTheDocument();
  });

  it('renders the protected content when the user is authenticated', async () => {
    seedSession();
    renderAt('/profile');
    await waitFor(() => expect(screen.getByText('Secret Profile')).toBeInTheDocument());
    expect(screen.queryByText('Login Page')).not.toBeInTheDocument();
  });
});
