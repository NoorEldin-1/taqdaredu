import { describe, it, expect, beforeEach } from 'vitest';
import { http, HttpResponse } from 'msw';
import { server } from '@/test/mocks/server';
import { courseService, authService } from './services';

// Tests for the typed service layer: correct endpoints/verbs, typed data
// shapes, and pass-through of the backend envelope + 401 handling.

describe('services.ts', () => {
  beforeEach(() => {
    localStorage.clear();
    sessionStorage.clear();
  });

  describe('courseService.getAll', () => {
    it('fetches the courses list and returns typed data', async () => {
      const res = await courseService.getAll();
      expect(res.status).toBe(true);
      expect(Array.isArray(res.data)).toBe(true);
      expect(res.data!.length).toBeGreaterThan(0);
      expect(res.data![0]).toHaveProperty('title');
      expect(res.data![0]).toHaveProperty('price');
    });

    it('passes filter params through to the query string', async () => {
      let seenUrl = '';
      server.use(
        http.get('*/api/api_courses/list', ({ request }) => {
          seenUrl = request.url;
          return HttpResponse.json({ status: true, data: [], pagination: { current_page: 1, per_page: 12, total: 0, total_pages: 0 } });
        })
      );

      await courseService.getAll({ page: 2, category_id: 5, level: 'beginner', search: 'fiber' });

      expect(seenUrl).toContain('page=2');
      expect(seenUrl).toContain('category_id=5');
      expect(seenUrl).toContain('level=beginner');
      expect(seenUrl).toContain('search=fiber');
    });

    it('surfaces pagination metadata', async () => {
      const res = await courseService.getAll();
      expect(res.pagination).toBeDefined();
      expect(res.pagination).toHaveProperty('total');
    });
  });

  describe('courseService.getById', () => {
    it('requests /detail/:id and returns the course', async () => {
      const res = await courseService.getById(7);
      expect(res.status).toBe(true);
      expect(res.data!.id).toBe(7);
    });

    it('sends the auth token when the user is logged in', async () => {
      localStorage.setItem('auth_token', 'logged-in-token');
      let seenAuth: string | null = null;
      server.use(
        http.get('*/api/api_courses/detail/:id', ({ request, params }) => {
          seenAuth = request.headers.get('Authorization');
          return HttpResponse.json({ status: true, data: { id: Number(params.id), title: 'T', price: 0 } });
        })
      );

      await courseService.getById(3);
      expect(seenAuth).toBe('Bearer logged-in-token');
    });
  });

  describe('authService.login', () => {
    it('returns a user + token on valid credentials', async () => {
      const res = await authService.login('jane@example.com', 'correct-password');
      expect(res.status).toBe(true);
      expect(res.data!.token).toBeTruthy();
      expect(res.data!.email).toBe('jane@example.com');
    });

    it('returns status:false with a message on bad credentials', async () => {
      const res = await authService.login('jane@example.com', 'WRONG');
      expect(res.status).toBe(false);
      expect(res.message).toMatch(/invalid/i);
      expect((res.data as { token?: string } | undefined)?.token).toBeUndefined();
    });
  });

  describe('authService.getProfile (protected)', () => {
    it('clears the session and returns an error envelope on 401', async () => {
      localStorage.setItem('auth_token', 'expired');
      localStorage.setItem('user', '{"id":1}');
      const original = window.location;
      Object.defineProperty(window, 'location', {
        configurable: true,
        value: { ...original, pathname: '/profile', href: '' },
      });

      server.use(http.get('*/api/api_frontend/profile', () => new HttpResponse(null, { status: 401 })));

      const res = await authService.getProfile();
      expect(res.status).toBe(false);
      expect(localStorage.getItem('auth_token')).toBeNull();

      Object.defineProperty(window, 'location', { configurable: true, value: original });
    });
  });
});
