import { describe, it, expect, beforeEach, vi } from 'vitest';
import { http, HttpResponse } from 'msw';
import { server } from '@/test/mocks/server';
import { apiFetch, apiPost, buildUrl, getAuthToken, API_MODULES } from './config';

// Tests for the low-level HTTP helpers in config.ts:
// Authorization header injection, the { status:false, message } envelope, the
// 401 session-expiry handling, and URL/query building.

describe('config.ts — API helpers', () => {
  beforeEach(() => {
    localStorage.clear();
    sessionStorage.clear();
  });

  describe('getAuthToken', () => {
    it('prefers localStorage (remember-me) over sessionStorage', () => {
      sessionStorage.setItem('auth_token', 'session-token');
      localStorage.setItem('auth_token', 'local-token');
      expect(getAuthToken()).toBe('local-token');
    });

    it('falls back to sessionStorage when localStorage is empty', () => {
      sessionStorage.setItem('auth_token', 'session-token');
      expect(getAuthToken()).toBe('session-token');
    });

    it('returns null when no token is stored', () => {
      expect(getAuthToken()).toBeNull();
    });
  });

  describe('buildUrl', () => {
    it('appends only defined, non-empty query params', () => {
      const url = buildUrl('/api/api_courses', '/list', {
        page: 1,
        search: 'react',
        empty: '',
        missing: undefined,
      });
      expect(url).toContain('/api/api_courses/list?');
      expect(url).toContain('page=1');
      expect(url).toContain('search=react');
      expect(url).not.toContain('empty=');
      expect(url).not.toContain('missing=');
    });

    it('returns the bare URL (no query string) when no params are given', () => {
      const url = buildUrl('/api/api_courses', '/list');
      expect(url).toMatch(/\/api\/api_courses\/list$/);
      expect(url).not.toContain('?');
    });
  });

  describe('apiFetch', () => {
    it('adds an Authorization: Bearer header when a token is present', async () => {
      localStorage.setItem('auth_token', 'my-token');
      let seenAuth: string | null = null;

      server.use(
        http.get('*/api/api_courses/list', ({ request }) => {
          seenAuth = request.headers.get('Authorization');
          return HttpResponse.json({ status: true, data: [] });
        })
      );

      await apiFetch(API_MODULES.COURSES, '/list');
      expect(seenAuth).toBe('Bearer my-token');
    });

    it('omits the Authorization header when no token is present', async () => {
      let hadAuth = true;
      server.use(
        http.get('*/api/api_courses/list', ({ request }) => {
          hadAuth = request.headers.has('Authorization');
          return HttpResponse.json({ status: true, data: [] });
        })
      );

      await apiFetch(API_MODULES.COURSES, '/list');
      expect(hadAuth).toBe(false);
    });

    it('returns a { status:false, message } envelope on 401 and clears the token', async () => {
      localStorage.setItem('auth_token', 'expired-token');
      localStorage.setItem('user', '{"id":1}');

      // Keep window.location.pathname off /login so handle401 would redirect;
      // stub location so jsdom doesn't attempt a real navigation.
      const original = window.location;
      Object.defineProperty(window, 'location', {
        configurable: true,
        value: { ...original, pathname: '/profile', href: '' },
      });

      server.use(http.get('*/api/api_courses/list', () => new HttpResponse(null, { status: 401 })));

      const res = await apiFetch(API_MODULES.COURSES, '/list');

      expect(res.status).toBe(false);
      expect(res.message).toMatch(/session expired/i);
      expect(localStorage.getItem('auth_token')).toBeNull();
      expect(localStorage.getItem('user')).toBeNull();
      expect(window.location.href).toBe('/login');

      Object.defineProperty(window, 'location', { configurable: true, value: original });
    });

    it('throws a friendly error on a non-ok, non-401 response', async () => {
      server.use(http.get('*/api/api_courses/list', () => new HttpResponse(null, { status: 500 })));
      await expect(apiFetch(API_MODULES.COURSES, '/list')).rejects.toThrow(/error occurred/i);
    });
  });

  describe('apiPost', () => {
    it('url-encodes the body and returns the parsed envelope', async () => {
      let receivedBody = '';
      server.use(
        http.post('*/api/api_frontend/login', async ({ request }) => {
          receivedBody = await request.text();
          return HttpResponse.json({ status: true, data: { id: 1 } });
        })
      );

      const res = await apiPost(API_MODULES.AUTH, '/login', { email: 'a@b.c', password: 'x y' });

      expect(receivedBody).toContain('email=a%40b.c');
      expect(receivedBody).toContain('password=x+y');
      expect(res.status).toBe(true);
    });
  });
});
