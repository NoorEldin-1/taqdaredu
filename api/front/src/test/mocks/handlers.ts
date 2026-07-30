import { http, HttpResponse } from 'msw';

// MSW request handlers mirroring the backend contract from src/lib/api/config.ts:
// base paths are /api/api_<module>/<endpoint> and every response uses the
// envelope { status, data?, message?, pagination? }.

const course = (id: number, over: Record<string, unknown> = {}) => ({
  id,
  title: `Test Course ${id}`,
  price: 100,
  level: 'beginner',
  status: 'active',
  category: { id: 1, name: 'تطوير الويب' },
  instructor: { id: 1, first_name: 'Ada', last_name: 'Lovelace' },
  rating: 4.5,
  total_ratings: 10,
  total_students: 42,
  ...over,
});

export const handlers = [
  // ---- Auth ----
  http.post('*/api/api_frontend/login', async ({ request }) => {
    const body = new URLSearchParams(await request.text());
    const email = body.get('email');
    const password = body.get('password');
    if (email === 'jane@example.com' && password === 'correct-password') {
      return HttpResponse.json({
        status: true,
        data: {
          id: 1,
          first_name: 'Jane',
          last_name: 'Doe',
          email,
          token: 'mock.jwt.token',
          is_instructor: false,
        },
      });
    }
    return HttpResponse.json({ status: false, message: 'Invalid email or password' });
  }),

  http.post('*/api/api_frontend/register', async () =>
    HttpResponse.json({ status: true, data: { id: 2, first_name: 'New', last_name: 'User', email: 'new@user.com', token: 't' } })
  ),

  http.get('*/api/api_frontend/profile', ({ request }) => {
    const auth = request.headers.get('Authorization');
    if (!auth) return new HttpResponse(null, { status: 401 });
    return HttpResponse.json({ status: true, data: { id: 1, first_name: 'Jane', last_name: 'Doe', email: 'jane@example.com', token: 't' } });
  }),

  // ---- Courses ----
  http.get('*/api/api_courses/list', ({ request }) => {
    const url = new URL(request.url);
    const category = url.searchParams.get('category_id');
    const data = category === '1' ? [course(1)] : [course(1), course(2), course(3)];
    return HttpResponse.json({
      status: true,
      data,
      pagination: { current_page: 1, per_page: 12, total: data.length, total_pages: 1 },
    });
  }),

  http.get('*/api/api_courses/detail/:id', ({ params }) =>
    HttpResponse.json({ status: true, data: course(Number(params.id)) })
  ),

  // ---- Categories (served off the AUTH module base) ----
  http.get('*/api/api_frontend/categories', () =>
    HttpResponse.json({ status: true, data: [{ id: 1, name: 'تطوير الويب' }, { id: 2, name: 'التصميم' }] })
  ),

  // ---- Settings (used as the "is the app up" probe) ----
  http.get('*/api/api_frontend/settings', () =>
    HttpResponse.json({ status: true, data: { site_name: 'تقدر' } })
  ),
];

// A handler that forces a 401 for the courses list — used to assert the
// session-expiry path in config.ts (handle401).
export const unauthorizedCoursesHandler = http.get('*/api/api_courses/list', () =>
  new HttpResponse(null, { status: 401 })
);

// A handler that forces a network/500 failure for the courses list.
export const failingCoursesHandler = http.get('*/api/api_courses/list', () =>
  new HttpResponse(null, { status: 500, statusText: 'Internal Server Error' })
);
