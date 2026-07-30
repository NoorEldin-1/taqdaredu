import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';

// --- Mocks (hoisted by Vitest) -------------------------------------------------
const mockUseCourses = vi.fn();
const mockUseCategories = vi.fn();

vi.mock('@/hooks/useApi', () => ({
  useCourses: (...args: unknown[]) => mockUseCourses(...args),
  useCategories: (...args: unknown[]) => mockUseCategories(...args),
  // Prices go through useCurrency(), which reads the admin currency symbol from
  // site settings. Unresolved settings render the bare number — see lib/currency.
  useSiteSettings: () => ({ data: undefined }),
}));

// Layout + SEO components pull in their own API calls / providers — stub them
// so this test focuses on the Courses page's own render logic.
vi.mock('@/components/layout/Navbar', () => ({ default: () => <nav data-testid="navbar" /> }));
vi.mock('@/components/layout/Footer', () => ({ default: () => <footer data-testid="footer" /> }));
vi.mock('@/components/Seo', () => ({ default: () => null }));

// framer-motion → thin passthrough so animation props don't reach the DOM.
vi.mock('framer-motion', async () => {
  const React = await import('react');
  const passthrough = (tag: string) =>
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    React.forwardRef(({ children, ...props }: any, ref: any) => {
      const {
        initial, animate, exit, transition, whileHover, whileTap, whileInView,
        layout, variants, viewport, ...rest
      } = props;
      return React.createElement(tag, { ...rest, ref }, children);
    });
  return {
    motion: new Proxy({}, { get: (_t, tag: string) => passthrough(tag) }),
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    AnimatePresence: ({ children }: any) => React.createElement(React.Fragment, null, children),
  };
});

import Courses from './Courses';

const renderPage = () => render(<MemoryRouter><Courses /></MemoryRouter>);

const course = (id: number, title: string) => ({
  id, title, price: 100, level: 'beginner', status: 'active',
  category: { id: 1, name: 'تطوير الويب' },
  instructor: { id: 1, first_name: 'Ada', last_name: 'Lovelace' },
  rating: 4.5, total_ratings: 10, total_students: 42,
});

describe('Courses page', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockUseCategories.mockReturnValue({ data: { status: true, data: [{ id: 1, name: 'تطوير الويب' }] } });
  });

  it('shows a loading spinner while courses are loading', () => {
    mockUseCourses.mockReturnValue({ data: undefined, isLoading: true });
    const { container } = renderPage();

    expect(container.querySelector('.animate-spin')).not.toBeNull();
    expect(screen.queryByText(/لا توجد دورات مطابقة/)).not.toBeInTheDocument();
  });

  it('renders the list of courses returned by the API', () => {
    mockUseCourses.mockReturnValue({
      isLoading: false,
      data: {
        status: true,
        data: [course(1, 'مقدمة في تطوير الويب'), course(2, 'أساسيات التصميم')],
        pagination: { current_page: 1, per_page: 12, total: 2, total_pages: 1 },
      },
    });

    renderPage();

    expect(screen.getByText('مقدمة في تطوير الويب')).toBeInTheDocument();
    expect(screen.getByText('أساسيات التصميم')).toBeInTheDocument();
    // The "N courses found" toolbar reflects the pagination total.
    expect(screen.getByText('2')).toBeInTheDocument();
  });

  it('shows an empty state when the API returns no courses', () => {
    mockUseCourses.mockReturnValue({
      isLoading: false,
      data: { status: true, data: [], pagination: { current_page: 1, per_page: 12, total: 0, total_pages: 0 } },
    });

    renderPage();
    expect(screen.getByText(/لا توجد دورات مطابقة/)).toBeInTheDocument();
  });

  it('degrades to the empty state when the query errored (no data)', () => {
    // On error the react-query hook yields data: undefined → component shows the
    // same "no courses" state rather than crashing.
    mockUseCourses.mockReturnValue({ isLoading: false, isError: true, data: undefined });

    renderPage();
    expect(screen.getByText(/لا توجد دورات مطابقة/)).toBeInTheDocument();
  });
});
