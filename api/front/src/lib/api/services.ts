import {
  apiFetch,
  apiPost,
  apiPostFormData,
  apiPut,
  apiDelete,
  publicFetch,
  API_MODULES,
  API_BASE_URL,
  buildUrl,
  ApiResponse
} from './config';
import type {
  User,
  Course,
  CoursesParams,
  Category,
  Instructor,
  Blog,
  BlogCategory,
  BlogComment,
  Review,
  ReviewStats,
  Cart,
  SiteSettings,
  Page,
  FAQ,
  ContactRequest,
  SearchResults,
  Section,
  Lesson,
  HomePageData,
  FilterOptions,
  CourseProgress,
  CourseCurriculum,
  Notification,
  NotificationSettings,
  MessageThread,
  Message,
  PaymentMethod,
  PaymentHistory,
  Invoice,
  CouponResult,
  Quiz,
  QuizResult,
  Badge,
  UserBadge,
  Certificate,
  DashboardData,
  CourseComparison,
  InstructorApplication,
  InstructorApplicationStatus,
  LessonComment,
  Language,
  Currency,
  CustomPage,
  SocialLoginRequest,
  ResetPasswordRequest,
  InstructorDashboard,
  InstructorSalesReport,
  StudentDashboard,
  StudentProgress,
  StudentActivity,
} from './types';

// ============ Authentication Services ============
export const authService = {
  login: async (email: string, password: string) => {
    return apiPost<User>(API_MODULES.AUTH, '/login', { email, password });
  },

  register: async (firstName: string, lastName: string, email: string, password: string) => {
    return apiPost<User>(API_MODULES.AUTH, '/register', {
      first_name: firstName,
      last_name: lastName,
      email,
      password,
    });
  },

  forgotPassword: async (email: string) => {
    return apiPost<{ message: string }>(API_MODULES.AUTH, '/forgot_password', { email });
  },

  socialLogin: async (data: SocialLoginRequest) => {
    return apiPost<User>(API_MODULES.AUTH, '/social_login', data as unknown as Record<string, string | number>);
  },

  getProfile: async () => {
    return apiFetch<User>(API_MODULES.AUTH, '/profile');
  },

  updateProfile: async (data: Partial<User>) => {
    const formData: Record<string, string | number | undefined> = {};
    if (data.first_name) formData.first_name = data.first_name;
    if (data.last_name) formData.last_name = data.last_name;
    if (data.biography) formData.biography = data.biography;
    if (data.title) formData.title = data.title;
    return apiPost<User>(API_MODULES.AUTH, '/profile', formData);
  },

  uploadProfileImage: async (file: File) => {
    const formData = new FormData();
    formData.append('image', file);
    return apiPostFormData<{ image: string }>(API_MODULES.AUTH, '/profile', formData);
  },

  changePassword: async (currentPassword: string, newPassword: string) => {
    return apiPost<{ message: string }>(API_MODULES.AUTH, '/change_password', {
      current_password: currentPassword,
      new_password: newPassword,
    });
  },
};

// ============ Course Services ============
export const courseService = {
  getAll: async (params?: CoursesParams) => {
    return publicFetch<Course[]>(API_MODULES.COURSES, '/list', params as Record<string, string | number | boolean | undefined>);
  },

  getById: async (id: number) => {
    // Use apiFetch (sends the auth token when logged in) so the backend can set
    // is_enrolled correctly. Falls back to guest data when no token is present.
    return apiFetch<Course>(API_MODULES.COURSES, `/detail/${id}`);
  },

  getCurriculum: async (courseId: number) => {
    // Authenticated fetch: the backend only includes paid lessons' video_url when
    // it can see the enrolled user via the token. publicFetch sent no token, so
    // enrolled buyers saw paid lessons as locked.
    return apiFetch<CourseCurriculum>(API_MODULES.COURSES, `/curriculum/${courseId}`);
  },

  getReviews: async (courseId: number, page = 1, limit = 10) => {
    return publicFetch<{ reviews: Review[]; stats: ReviewStats }>(
      API_MODULES.COURSES, 
      `/reviews/${courseId}`,
      { page, limit }
    );
  },

  addReview: async (courseId: number, rating: number, review?: string) => {
    const data: Record<string, string | number | undefined> = { rating };
    if (review) data.review = review;
    return apiPost<Review>(API_MODULES.COURSES, `/review/${courseId}`, data);
  },

  getProgress: async (courseId: number) => {
    return apiFetch<CourseProgress>(API_MODULES.COURSES, `/progress/${courseId}`);
  },

  completeLesson: async (lessonId: number, courseId: number) => {
    return apiPost<{ message: string }>(API_MODULES.COURSES, '/complete_lesson', { 
      lesson_id: lessonId, 
      course_id: courseId 
    });
  },

  getCertificate: async (courseId: number) => {
    return apiFetch<Certificate>(API_MODULES.COURSES, `/certificate/${courseId}`);
  },

  getFeatured: async () => {
    return publicFetch<Course[]>(API_MODULES.COURSES, '/featured');
  },

  getPopular: async () => {
    return publicFetch<Course[]>(API_MODULES.COURSES, '/popular');
  },

  getLevels: async () => {
    return publicFetch<{ value: string; label: string }[]>(API_MODULES.COURSES, '/levels');
  },

  getLanguages: async () => {
    return publicFetch<{ code: string; name: string }[]>(API_MODULES.COURSES, '/languages');
  },
};

// ============ Category Services ============
export const categoryService = {
  getAll: async () => {
    const response = await fetch(`${API_BASE_URL}${API_MODULES.AUTH}/categories`);
    return response.json() as Promise<ApiResponse<Category[]>>;
  },

  getById: async (id: number) => {
    const response = await fetch(`${API_BASE_URL}${API_MODULES.AUTH}/category/${id}`);
    return response.json() as Promise<ApiResponse<Category>>;
  },

  getCourses: async (id: number) => {
    const response = await fetch(`${API_BASE_URL}${API_MODULES.AUTH}/category_courses/${id}`);
    return response.json() as Promise<ApiResponse<Course[]>>;
  },
};

// ============ Instructor Services ============
export const instructorService = {
  getAll: async (page = 1, limit = 12) => {
    const response = await fetch(buildUrl(API_MODULES.AUTH, '/instructors', { page, limit }));
    return response.json() as Promise<ApiResponse<Instructor[]>>;
  },

  getById: async (id: number) => {
    const response = await fetch(`${API_BASE_URL}${API_MODULES.AUTH}/instructor/${id}`);
    return response.json() as Promise<ApiResponse<Instructor>>;
  },

  getTop: async (limit = 8) => {
    const response = await fetch(buildUrl(API_MODULES.AUTH, '/top_instructors', { limit }));
    return response.json() as Promise<ApiResponse<Instructor[]>>;
  },
};

// ============ Instructor Application Services ============
export const instructorApplicationService = {
  submit: async (data: InstructorApplication) => {
    return apiPost<{ message: string }>(API_MODULES.AUTH, '/instructor_application', data as unknown as Record<string, string | number>);
  },

  getStatus: async () => {
    return apiFetch<InstructorApplicationStatus>(API_MODULES.AUTH, '/instructor_application_status');
  },
};

// ============ Blog Services ============
export const blogService = {
  getAll: async (page = 1, limit = 10, categoryId?: number) => {
    const params: Record<string, number> = { page, limit };
    if (categoryId) params.category_id = categoryId;
    const response = await fetch(buildUrl(API_MODULES.AUTH, '/blogs', params));
    return response.json() as Promise<ApiResponse<Blog[]>>;
  },

  getById: async (id: number) => {
    const response = await fetch(`${API_BASE_URL}${API_MODULES.AUTH}/blog/${id}`);
    return response.json() as Promise<ApiResponse<Blog>>;
  },

  getCategories: async () => {
    const response = await fetch(`${API_BASE_URL}${API_MODULES.AUTH}/blog_categories`);
    return response.json() as Promise<ApiResponse<BlogCategory[]>>;
  },

  getPopular: async (limit = 5) => {
    const response = await fetch(buildUrl(API_MODULES.AUTH, '/popular_blogs', { limit }));
    return response.json() as Promise<ApiResponse<Blog[]>>;
  },

  getRecent: async (limit = 5) => {
    const response = await fetch(buildUrl(API_MODULES.AUTH, '/recent_blogs', { limit }));
    return response.json() as Promise<ApiResponse<Blog[]>>;
  },

  addComment: async (blogId: number, comment: string, parentId?: number) => {
    const data: Record<string, string | number | undefined> = { comment };
    if (parentId) data.parent_id = parentId;
    return apiPost<BlogComment>(API_MODULES.AUTH, `/blog_comment/${blogId}`, data);
  },
};

// ============ User Course Services ============
export const userCourseService = {
  getMyCourses: async () => {
    return apiFetch<Course[]>(API_MODULES.AUTH, '/my_courses');
  },

  enrollFree: async (courseId: number) => {
    return apiPost<{ message: string }>(API_MODULES.AUTH, '/enroll_free_course', { course_id: courseId });
  },
};

// ============ Wishlist Services ============
export const wishlistService = {
  get: async () => {
    return apiFetch<Course[]>(API_MODULES.AUTH, '/wishlist');
  },

  toggle: async (courseId: number) => {
    return apiPost<{ is_wishlisted: boolean }>(API_MODULES.AUTH, '/wishlist_toggle', { course_id: courseId });
  },
};

// ============ Payment Services ============
export const paymentService = {
  // Payment Methods
  getMethods: async () => {
    return publicFetch<PaymentMethod[]>(API_MODULES.PAYMENT, '/methods');
  },

  // Payment History
  getHistory: async () => {
    return apiFetch<PaymentHistory[]>(API_MODULES.PAYMENT, '/history');
  },

  // Get Invoice
  getInvoice: async (paymentId: number) => {
    return apiFetch<Invoice>(API_MODULES.PAYMENT, `/invoice/${paymentId}`);
  },

  // Apply Coupon — previews the discount against the user's current cart total.
  applyCoupon: async (couponCode: string) => {
    return apiPost<CouponResult>(API_MODULES.PAYMENT, '/apply_coupon', {
      coupon_code: couponCode,
    });
  },

  // Offline Payment
  offlinePayment: async (courseId: number) => {
    return apiPost<{ message: string }>(API_MODULES.PAYMENT, '/offline', { course_id: courseId });
  },

  // Cart Operations
  getCart: async () => {
    return apiFetch<Cart>(API_MODULES.PAYMENT, '/cart');
  },

  addToCart: async (courseId: number) => {
    // CodeIgniter REST maps the method name with underscores: cart_add_post()
    return apiPost<Cart>(API_MODULES.PAYMENT, '/cart_add', { course_id: courseId });
  },

  removeFromCart: async (courseId: number) => {
    return apiDelete<{ message: string }>(API_MODULES.PAYMENT, `/cart_remove/${courseId}`);
  },

  clearCart: async () => {
    return apiDelete<{ message: string }>(API_MODULES.PAYMENT, '/cart_clear');
  },

  // Checkout
  checkout: async (paymentMethod: string, couponCode?: string) => {
    const data: Record<string, string | undefined> = { payment_method: paymentMethod };
    if (couponCode) data.coupon_code = couponCode;
    return apiPost<{ redirect_url?: string; checkout_token?: string; total?: number; enrolled?: boolean }>(API_MODULES.PAYMENT, '/checkout', data);
  },
};

// ============ Notification Services ============
export const notificationService = {
  getAll: async (page = 1, limit = 20, unreadOnly = false) => {
    return apiFetch<Notification[]>(API_MODULES.NOTIFICATIONS, '/list', undefined, { 
      page, 
      limit, 
      unread_only: unreadOnly ? 1 : 0 
    });
  },

  markAsRead: async (notificationId: number) => {
    return apiPut<{ message: string }>(API_MODULES.NOTIFICATIONS, `/read/${notificationId}`);
  },

  markAllAsRead: async () => {
    return apiPut<{ message: string }>(API_MODULES.NOTIFICATIONS, '/read_all');
  },

  delete: async (notificationId: number) => {
    return apiDelete<{ message: string }>(API_MODULES.NOTIFICATIONS, `/delete/${notificationId}`);
  },

  deleteAll: async () => {
    return apiDelete<{ message: string }>(API_MODULES.NOTIFICATIONS, '/delete_all');
  },

  getSettings: async () => {
    return apiFetch<NotificationSettings>(API_MODULES.NOTIFICATIONS, '/settings');
  },

  updateSettings: async (settings: NotificationSettings) => {
    return apiPut<NotificationSettings>(API_MODULES.NOTIFICATIONS, '/settings', settings as unknown as Record<string, string | number | boolean>);
  },

  registerPushToken: async (token: string, deviceType: 'android' | 'ios' | 'web') => {
    return apiPost<{ message: string }>(API_MODULES.NOTIFICATIONS, '/push_token', { 
      token, 
      device_type: deviceType 
    });
  },

  getUnreadCount: async () => {
    return apiFetch<{ count: number }>(API_MODULES.NOTIFICATIONS, '/unread_count');
  },
};

// ============ Message Services ============
export const messageService = {
  getThreads: async () => {
    return apiFetch<MessageThread[]>(API_MODULES.MESSAGES, '/threads');
  },

  getThread: async (threadCode: string, page = 1, limit = 50) => {
    return apiFetch<Message[]>(API_MODULES.MESSAGES, `/thread/${threadCode}`, undefined, { page, limit });
  },

  send: async (receiverId: number, message: string, threadCode?: string) => {
    const data: Record<string, string | number | undefined> = { 
      receiver_id: receiverId, 
      message 
    };
    if (threadCode) data.thread_code = threadCode;
    return apiPost<Message>(API_MODULES.MESSAGES, '/send', data);
  },

  deleteMessage: async (messageId: number) => {
    return apiDelete<{ message: string }>(API_MODULES.MESSAGES, `/delete/${messageId}`);
  },

  deleteThread: async (threadCode: string) => {
    return apiDelete<{ message: string }>(API_MODULES.MESSAGES, `/thread/${threadCode}`);
  },

  getUnreadCount: async () => {
    return apiFetch<{ count: number }>(API_MODULES.MESSAGES, '/unread_count');
  },

  startThread: async (receiverId: number) => {
    return apiPost<MessageThread>(API_MODULES.MESSAGES, '/start_thread', { receiver_id: receiverId });
  },

  markAsRead: async (threadCode?: string, messageIds?: number[]) => {
    const data: Record<string, string | undefined> = {};
    if (threadCode) data.thread_code = threadCode;
    if (messageIds) data.message_ids = JSON.stringify(messageIds);
    return apiPut<{ message: string }>(API_MODULES.MESSAGES, '/mark_read', data);
  },
};

// ============ Reports Services ============
export const reportsService = {
  // Instructor Reports
  getInstructorDashboard: async () => {
    return apiFetch<InstructorDashboard>(API_MODULES.REPORTS, '/instructor_dashboard');
  },

  getInstructorSales: async (dateFrom?: string, dateTo?: string) => {
    const params: Record<string, string | undefined> = {};
    if (dateFrom) params.date_from = dateFrom;
    if (dateTo) params.date_to = dateTo;
    return apiFetch<InstructorSalesReport>(API_MODULES.REPORTS, '/instructor_sales', {}, params);
  },

  getInstructorCourses: async () => {
    return apiFetch<Course[]>(API_MODULES.REPORTS, '/instructor_courses');
  },

  getInstructorStudents: async () => {
    return apiFetch<{ students: User[]; total: number }>(API_MODULES.REPORTS, '/instructor_students');
  },

  getInstructorPayouts: async () => {
    return apiFetch<PaymentHistory[]>(API_MODULES.REPORTS, '/instructor_payouts');
  },

  // Student Reports
  getStudentDashboard: async () => {
    return apiFetch<StudentDashboard>(API_MODULES.REPORTS, '/student_dashboard');
  },

  getStudentProgress: async () => {
    return apiFetch<StudentProgress[]>(API_MODULES.REPORTS, '/student_progress');
  },

  getStudentCertificates: async () => {
    return apiFetch<Certificate[]>(API_MODULES.REPORTS, '/student_certificates');
  },

  getStudentActivity: async () => {
    return apiFetch<StudentActivity[]>(API_MODULES.REPORTS, '/student_activity');
  },
};

// ============ Quiz Services ============
export const quizService = {
  get: async (lessonId: number) => {
    return apiFetch<Quiz>(API_MODULES.AUTH, `/quiz/${lessonId}`);
  },

  submit: async (lessonId: number, answers: Record<number, number>) => {
    return apiPost<QuizResult>(API_MODULES.AUTH, '/quiz_submit', { 
      lesson_id: lessonId, 
      answers: JSON.stringify(answers) 
    });
  },

  getResults: async (lessonId: number) => {
    return apiFetch<QuizResult>(API_MODULES.AUTH, `/quiz_results/${lessonId}`);
  },
};

// ============ Lesson Comment Services ============
export const lessonCommentService = {
  getAll: async (lessonId: number) => {
    return apiFetch<LessonComment[]>(API_MODULES.AUTH, `/lesson_comments/${lessonId}`);
  },

  add: async (lessonId: number, comment: string) => {
    return apiPost<LessonComment>(API_MODULES.AUTH, '/lesson_comment', { lesson_id: lessonId, comment });
  },
};

// ============ Settings & Pages Services ============
export const settingsService = {
  get: async () => {
    const response = await fetch(`${API_BASE_URL}${API_MODULES.AUTH}/settings`);
    return response.json() as Promise<ApiResponse<SiteSettings>>;
  },

  getPage: async (slug: string) => {
    const response = await fetch(`${API_BASE_URL}${API_MODULES.AUTH}/page/${slug}`);
    return response.json() as Promise<ApiResponse<Page>>;
  },

  getFAQs: async () => {
    const response = await fetch(`${API_BASE_URL}${API_MODULES.AUTH}/faqs`);
    return response.json() as Promise<ApiResponse<FAQ[]>>;
  },

  getTestimonials: async () => {
    const response = await fetch(`${API_BASE_URL}${API_MODULES.AUTH}/testimonials`);
    return response.json() as Promise<ApiResponse<Array<{ id: number; name: string; image: string; rating: number; review: string; course_title?: string }>>>;
  },

  getLanguages: async () => {
    const response = await fetch(`${API_BASE_URL}${API_MODULES.AUTH}/languages`);
    return response.json() as Promise<ApiResponse<Language[]>>;
  },

  getCurrencies: async () => {
    const response = await fetch(`${API_BASE_URL}${API_MODULES.AUTH}/currencies`);
    return response.json() as Promise<ApiResponse<Currency[]>>;
  },

  getCustomPages: async () => {
    const response = await fetch(`${API_BASE_URL}${API_MODULES.AUTH}/custom_pages`);
    return response.json() as Promise<ApiResponse<CustomPage[]>>;
  },

  getFilterOptions: async () => {
    const response = await fetch(`${API_BASE_URL}${API_MODULES.AUTH}/filter_options`);
    return response.json() as Promise<ApiResponse<FilterOptions>>;
  },
};

// ============ Home Page Service ============
export const homeService = {
  getData: async () => {
    const response = await fetch(`${API_BASE_URL}${API_MODULES.AUTH}/home`);
    return response.json() as Promise<ApiResponse<HomePageData>>;
  },
};

// ============ Badge Services ============
export const badgeService = {
  getAll: async () => {
    const response = await fetch(`${API_BASE_URL}${API_MODULES.AUTH}/badges`);
    return response.json() as Promise<ApiResponse<Badge[]>>;
  },

  getMy: async () => {
    return apiFetch<UserBadge[]>(API_MODULES.AUTH, '/my_badges');
  },
};

// ============ Dashboard Service ============
export const dashboardService = {
  getData: async () => {
    return apiFetch<DashboardData>(API_MODULES.AUTH, '/dashboard');
  },
};

// ============ Contact Service ============
export const contactService = {
  submit: async (data: ContactRequest) => {
    return apiPost<{ message: string }>(API_MODULES.AUTH, '/contact', data as unknown as Record<string, string | number>);
  },
};

// ============ Newsletter Service ============
export const newsletterService = {
  subscribe: async (email: string) => {
    return apiPost<{ message: string }>(API_MODULES.AUTH, '/newsletter_subscribe', { email });
  },
};

// ============ Search Service ============
export const searchService = {
  search: async (query: string) => {
    const response = await fetch(buildUrl(API_MODULES.AUTH, '/search', { q: query }));
    return response.json() as Promise<ApiResponse<SearchResults>>;
  },
};

// ============ Cart Services (Backward Compatibility) ============
export const cartService = {
  get: async () => paymentService.getCart(),
  add: async (courseId: number) => paymentService.addToCart(courseId),
  remove: async (courseId: number) => paymentService.removeFromCart(courseId),
  applyCoupon: async (code: string) => paymentService.applyCoupon(code),
};

