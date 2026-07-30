// API Configuration
export const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? '';

// API Modules — paths dispatched through CodeIgniter at /api/<controller>/<endpoint>.
// (Previously /api_<module>/* aliased to these via .htaccess; we now call the
// canonical paths directly to avoid LSWS rewrite REQUEST_URI quirks.)
export const API_MODULES = {
  AUTH: '/api/api_frontend',
  COURSES: '/api/api_courses',
  PAYMENT: '/api/api_payment',
  NOTIFICATIONS: '/api/api_notifications',
  MESSAGES: '/api/api_messages',
  REPORTS: '/api/api_reports',
  ADMIN: '/api/api_admin',
  WEBHOOKS: '/api/api_webhooks',
} as const;

// API Response Types
export interface ApiResponse<T> {
  status: boolean;
  data?: T;
  message?: string;
  pagination?: {
    current_page: number;
    per_page: number;
    total: number;
    total_pages: number;
  };
}

// Helper to get auth token (persistent "remember me" → localStorage, otherwise sessionStorage)
export const getAuthToken = (): string | null => {
  return localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token');
};

// Handle 401 unauthorized — only redirect if the user had a token (expired session)
const handle401 = () => {
  const hadToken = !!(localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token'));
  localStorage.removeItem('auth_token');
  localStorage.removeItem('user');
  sessionStorage.removeItem('auth_token');
  sessionStorage.removeItem('user');
  if (hadToken && window.location.pathname !== '/login') {
    window.location.href = '/login';
  }
};

// Helper to build URL with query params
export const buildUrl = (
  module: string,
  endpoint: string,
  params?: Record<string, string | number | boolean | undefined>
): string => {
  const base = `${API_BASE_URL}${module}${endpoint}`;
  if (!params) return base;
  const searchParams = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== '') {
      searchParams.append(key, String(value));
    }
  });
  const qs = searchParams.toString();
  return qs ? `${base}?${qs}` : base;
};

// Simple URL builder for backward compatibility
export const buildSimpleUrl = (
  endpoint: string,
  params?: Record<string, string | number | undefined>
): string => {
  const base = `${API_BASE_URL}${endpoint}`;
  if (!params) return base;
  const searchParams = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== '') {
      searchParams.append(key, String(value));
    }
  });
  const qs = searchParams.toString();
  return qs ? `${base}?${qs}` : base;
};

// API Fetch helper with authentication (GET requests)
export const apiFetch = async <T>(
  module: string,
  endpoint: string,
  options: RequestInit = {},
  params?: Record<string, string | number | boolean | undefined>
): Promise<ApiResponse<T>> => {
  const token = getAuthToken();

  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    ...options.headers,
  };

  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  const url = params ? buildUrl(module, endpoint, params) : `${API_BASE_URL}${module}${endpoint}`;

  const response = await fetch(url, {
    ...options,
    headers,
  });

  if (response.status === 401) {
    handle401();
    return { status: false, message: 'Session expired. Please login again.' } as ApiResponse<T>;
  }

  if (!response.ok) {
    console.error(`API request failed: ${response.status} ${response.statusText}`);
    throw new Error('An error occurred. Please try again.');
  }

  return response.json();
};

// POST helper for form data
export const apiPost = async <T>(
  module: string,
  endpoint: string,
  data: Record<string, string | number | boolean | undefined>
): Promise<ApiResponse<T>> => {
  const token = getAuthToken();

  const headers: HeadersInit = {
    'Content-Type': 'application/x-www-form-urlencoded',
  };

  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  const body = new URLSearchParams();
  Object.entries(data).forEach(([key, value]) => {
    if (value !== undefined) {
      body.append(key, String(value));
    }
  });

  const response = await fetch(`${API_BASE_URL}${module}${endpoint}`, {
    method: 'POST',
    headers,
    body: body.toString(),
  });

  if (response.status === 401) {
    handle401();
    return { status: false, message: 'Session expired. Please login again.' } as ApiResponse<T>;
  }

  return response.json();
};

// POST helper for multipart form-data (file uploads). Do NOT set Content-Type —
// the browser sets it (with the multipart boundary) automatically for FormData.
export const apiPostFormData = async <T>(
  module: string,
  endpoint: string,
  formData: FormData
): Promise<ApiResponse<T>> => {
  const token = getAuthToken();

  const headers: HeadersInit = {};
  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  const response = await fetch(`${API_BASE_URL}${module}${endpoint}`, {
    method: 'POST',
    headers,
    body: formData,
  });

  if (response.status === 401) {
    handle401();
    return { status: false, message: 'Session expired. Please login again.' } as ApiResponse<T>;
  }

  return response.json();
};

// PUT helper for updates
export const apiPut = async <T>(
  module: string,
  endpoint: string,
  data?: Record<string, string | number | boolean | undefined>
): Promise<ApiResponse<T>> => {
  const token = getAuthToken();

  const headers: HeadersInit = {
    'Content-Type': 'application/x-www-form-urlencoded',
  };

  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  const body = new URLSearchParams();
  if (data) {
    Object.entries(data).forEach(([key, value]) => {
      if (value !== undefined) {
        body.append(key, String(value));
      }
    });
  }

  const response = await fetch(`${API_BASE_URL}${module}${endpoint}`, {
    method: 'PUT',
    headers,
    body: body.toString(),
  });

  if (response.status === 401) {
    handle401();
    return { status: false, message: 'Session expired. Please login again.' } as ApiResponse<T>;
  }

  return response.json();
};

// DELETE helper
export const apiDelete = async <T>(
  module: string,
  endpoint: string
): Promise<ApiResponse<T>> => {
  const token = getAuthToken();

  const headers: HeadersInit = {
    'Content-Type': 'application/json',
  };

  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  const response = await fetch(`${API_BASE_URL}${module}${endpoint}`, {
    method: 'DELETE',
    headers,
  });

  if (response.status === 401) {
    handle401();
    return { status: false, message: 'Session expired. Please login again.' } as ApiResponse<T>;
  }

  return response.json();
};

// Public fetch (no auth required)
export const publicFetch = async <T>(
  module: string,
  endpoint: string,
  params?: Record<string, string | number | boolean | undefined>
): Promise<ApiResponse<T>> => {
  const url = params ? buildUrl(module, endpoint, params) : `${API_BASE_URL}${module}${endpoint}`;

  const response = await fetch(url);

  if (!response.ok) {
    console.error(`API request failed: ${response.status} ${response.statusText}`);
    throw new Error('An error occurred. Please try again.');
  }

  return response.json();
};
