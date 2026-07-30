import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getAuthToken } from '@/lib/api/config';
import {
  courseService,
  categoryService,
  instructorService,
  blogService,
  userCourseService,
  wishlistService,
  paymentService,
  cartService,
  settingsService,
  contactService,
  searchService,
  authService,
  homeService,
  notificationService,
  messageService,
  quizService,
  badgeService,
  dashboardService,
  newsletterService,
  instructorApplicationService,
  lessonCommentService,
  reportsService,
} from '@/lib/api/services';
import type { CoursesParams, ContactRequest, InstructorApplication, SocialLoginRequest, ResetPasswordRequest } from '@/lib/api/types';

// ============ Course Hooks ============
export const useCourses = (params?: CoursesParams) => {
  return useQuery({
    queryKey: ['courses', params],
    queryFn: () => courseService.getAll(params),
  });
};

export const useCourse = (id: number) => {
  return useQuery({
    queryKey: ['course', id],
    queryFn: () => courseService.getById(id),
    enabled: !!id,
  });
};

export const useFeaturedCourses = () => {
  return useQuery({
    queryKey: ['featuredCourses'],
    queryFn: () => courseService.getFeatured(),
  });
};

export const usePopularCourses = () => {
  return useQuery({
    queryKey: ['popularCourses'],
    queryFn: () => courseService.getPopular(),
  });
};

export const useCourseCurriculum = (courseId: number) => {
  return useQuery({
    queryKey: ['courseCurriculum', courseId],
    queryFn: () => courseService.getCurriculum(courseId),
    enabled: !!courseId,
  });
};

export const useCourseProgress = (courseId: number) => {
  return useQuery({
    queryKey: ['courseProgress', courseId],
    queryFn: () => courseService.getProgress(courseId),
    enabled: !!courseId,
  });
};

export const useCourseReviews = (courseId: number, page = 1, limit = 10) => {
  return useQuery({
    queryKey: ['courseReviews', courseId, page, limit],
    queryFn: () => courseService.getReviews(courseId, page, limit),
    enabled: !!courseId,
  });
};

export const useCourseCertificate = (courseId: number) => {
  return useQuery({
    queryKey: ['courseCertificate', courseId],
    queryFn: () => courseService.getCertificate(courseId),
    enabled: !!courseId,
  });
};

export const useCourseLevels = () => {
  return useQuery({
    queryKey: ['courseLevels'],
    queryFn: () => courseService.getLevels(),
  });
};

export const useCourseLanguages = () => {
  return useQuery({
    queryKey: ['courseLanguages'],
    queryFn: () => courseService.getLanguages(),
  });
};

export const useCompleteLesson = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ lessonId, courseId }: { lessonId: number; courseId: number }) =>
      courseService.completeLesson(lessonId, courseId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['courseProgress'] });
      queryClient.invalidateQueries({ queryKey: ['myCourses'] });
      // The player reads is_completed (checkmarks + "X of N completed") from the
      // curriculum, so refetch it too — otherwise the UI stays at 0 after completing.
      queryClient.invalidateQueries({ queryKey: ['courseCurriculum'] });
      queryClient.invalidateQueries({ queryKey: ['course'] });
    },
  });
};

export const useAddReview = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ courseId, rating, review }: { courseId: number; rating: number; review?: string }) =>
      courseService.addReview(courseId, rating, review),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['courseReviews', variables.courseId] });
    },
  });
};

// ============ Lesson Comment Hooks ============
export const useLessonComments = (lessonId: number) => {
  return useQuery({
    queryKey: ['lessonComments', lessonId],
    queryFn: () => lessonCommentService.getAll(lessonId),
    enabled: !!lessonId,
  });
};

export const useAddLessonComment = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ lessonId, comment }: { lessonId: number; comment: string }) =>
      lessonCommentService.add(lessonId, comment),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['lessonComments', variables.lessonId] });
    },
  });
};

// ============ Quiz Hooks ============
export const useQuiz = (lessonId: number) => {
  return useQuery({
    queryKey: ['quiz', lessonId],
    queryFn: () => quizService.get(lessonId),
    enabled: !!lessonId,
  });
};

export const useSubmitQuiz = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ lessonId, answers }: { lessonId: number; answers: Record<number, number> }) =>
      quizService.submit(lessonId, answers),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['quizResults', variables.lessonId] });
    },
  });
};

export const useQuizResults = (lessonId: number) => {
  return useQuery({
    queryKey: ['quizResults', lessonId],
    queryFn: () => quizService.getResults(lessonId),
    enabled: !!lessonId,
  });
};

// ============ Category Hooks ============
export const useCategories = () => {
  return useQuery({
    queryKey: ['categories'],
    queryFn: categoryService.getAll,
  });
};

export const useCategory = (id: number) => {
  return useQuery({
    queryKey: ['category', id],
    queryFn: () => categoryService.getById(id),
    enabled: !!id,
  });
};

export const useCategoryCourses = (id: number) => {
  return useQuery({
    queryKey: ['categoryCourses', id],
    queryFn: () => categoryService.getCourses(id),
    enabled: !!id,
  });
};

// ============ Instructor Hooks ============
export const useInstructors = (page = 1, limit = 12) => {
  return useQuery({
    queryKey: ['instructors', page, limit],
    queryFn: () => instructorService.getAll(page, limit),
  });
};

export const useInstructor = (id: number) => {
  return useQuery({
    queryKey: ['instructor', id],
    queryFn: () => instructorService.getById(id),
    enabled: !!id,
  });
};

export const useTopInstructors = (limit = 8) => {
  return useQuery({
    queryKey: ['topInstructors', limit],
    queryFn: () => instructorService.getTop(limit),
  });
};

// ============ Instructor Application Hooks ============
export const useSubmitInstructorApplication = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: InstructorApplication) => instructorApplicationService.submit(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['instructorApplicationStatus'] });
    },
  });
};

export const useInstructorApplicationStatus = () => {
  return useQuery({
    queryKey: ['instructorApplicationStatus'],
    queryFn: instructorApplicationService.getStatus,
  });
};

// ============ Blog Hooks ============
export const useBlogs = (page = 1, limit = 10, categoryId?: number) => {
  return useQuery({
    queryKey: ['blogs', page, limit, categoryId],
    queryFn: () => blogService.getAll(page, limit, categoryId),
  });
};

export const useBlog = (id: number) => {
  return useQuery({
    queryKey: ['blog', id],
    queryFn: () => blogService.getById(id),
    enabled: !!id,
  });
};

export const useBlogCategories = () => {
  return useQuery({
    queryKey: ['blogCategories'],
    queryFn: blogService.getCategories,
  });
};

export const usePopularBlogs = (limit = 5) => {
  return useQuery({
    queryKey: ['popularBlogs', limit],
    queryFn: () => blogService.getPopular(limit),
  });
};

export const useRecentBlogs = (limit = 5) => {
  return useQuery({
    queryKey: ['recentBlogs', limit],
    queryFn: () => blogService.getRecent(limit),
  });
};

export const useAddBlogComment = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ blogId, comment, parentId }: { blogId: number; comment: string; parentId?: number }) =>
      blogService.addComment(blogId, comment, parentId),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['blog', variables.blogId] });
    },
  });
};

// ============ User Course Hooks ============
export const useMyCourses = () => {
  return useQuery({
    queryKey: ['myCourses'],
    queryFn: userCourseService.getMyCourses,
  });
};

export const useEnrollFree = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (courseId: number) => userCourseService.enrollFree(courseId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['myCourses'] });
      queryClient.invalidateQueries({ queryKey: ['course'] });
    },
  });
};

// ============ Wishlist Hooks ============
export const useWishlist = () => {
  return useQuery({
    queryKey: ['wishlist'],
    queryFn: wishlistService.get,
  });
};

export const useToggleWishlist = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (courseId: number) => wishlistService.toggle(courseId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['wishlist'] });
      queryClient.invalidateQueries({ queryKey: ['course'] });
    },
  });
};

// ============ Cart Hooks ============
export const useCart = () => {
  return useQuery({
    queryKey: ['cart'],
    queryFn: cartService.get,
    // Only the server cart needs auth; guests use the local guest-cart. Skipping the
    // request for guests avoids a 401 (which logs a console error → Best-Practices hit).
    enabled: !!getAuthToken(),
  });
};

export const useAddToCart = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (courseId: number) => cartService.add(courseId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['cart'] });
    },
  });
};

export const useRemoveFromCart = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (courseId: number) => cartService.remove(courseId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['cart'] });
    },
  });
};

export const useClearCart = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: () => paymentService.clearCart(),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['cart'] });
    },
  });
};

// ============ Payment Hooks ============
export const usePaymentMethods = () => {
  return useQuery({
    queryKey: ['paymentMethods'],
    queryFn: paymentService.getMethods,
  });
};

export const usePaymentHistory = () => {
  return useQuery({
    queryKey: ['paymentHistory'],
    queryFn: paymentService.getHistory,
  });
};

export const useInvoice = (paymentId: number) => {
  return useQuery({
    queryKey: ['invoice', paymentId],
    queryFn: () => paymentService.getInvoice(paymentId),
    enabled: !!paymentId,
  });
};

export const useApplyCoupon = () => {
  return useMutation({
    mutationFn: (couponCode: string) => paymentService.applyCoupon(couponCode),
  });
};

export const useCheckout = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ paymentMethod, couponCode }: { paymentMethod: string; couponCode?: string }) =>
      paymentService.checkout(paymentMethod, couponCode),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['cart'] });
      queryClient.invalidateQueries({ queryKey: ['myCourses'] });
    },
  });
};

// ============ Notification Hooks ============
export const useNotifications = (page = 1, limit = 20, unreadOnly = false) => {
  return useQuery({
    queryKey: ['notifications', page, limit, unreadOnly],
    queryFn: () => notificationService.getAll(page, limit, unreadOnly),
  });
};

export const useUnreadNotificationsCount = () => {
  return useQuery({
    queryKey: ['unreadNotificationsCount'],
    queryFn: notificationService.getUnreadCount,
  });
};

export const useMarkNotificationRead = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (notificationId: number) => notificationService.markAsRead(notificationId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
      queryClient.invalidateQueries({ queryKey: ['unreadNotificationsCount'] });
    },
  });
};

export const useMarkAllNotificationsRead = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: () => notificationService.markAllAsRead(),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
      queryClient.invalidateQueries({ queryKey: ['unreadNotificationsCount'] });
    },
  });
};

export const useDeleteNotification = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (notificationId: number) => notificationService.delete(notificationId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
    },
  });
};

export const useNotificationSettings = () => {
  return useQuery({
    queryKey: ['notificationSettings'],
    queryFn: notificationService.getSettings,
  });
};

export const useUpdateNotificationSettings = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: notificationService.updateSettings,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notificationSettings'] });
    },
  });
};

// ============ Message Hooks ============
export const useMessageThreads = () => {
  return useQuery({
    queryKey: ['messageThreads'],
    queryFn: messageService.getThreads,
  });
};

export const useMessageThread = (threadCode: string, page = 1, limit = 50) => {
  return useQuery({
    queryKey: ['messageThread', threadCode, page, limit],
    queryFn: () => messageService.getThread(threadCode, page, limit),
    enabled: !!threadCode,
  });
};

export const useSendMessage = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ receiverId, message, threadCode }: { receiverId: number; message: string; threadCode?: string }) =>
      messageService.send(receiverId, message, threadCode),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['messageThreads'] });
      queryClient.invalidateQueries({ queryKey: ['messageThread'] });
    },
  });
};

export const useStartThread = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (receiverId: number) => messageService.startThread(receiverId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['messageThreads'] });
    },
  });
};

export const useUnreadMessagesCount = () => {
  return useQuery({
    queryKey: ['unreadMessagesCount'],
    queryFn: messageService.getUnreadCount,
  });
};

// ============ Reports Hooks ============
export const useInstructorDashboard = () => {
  return useQuery({
    queryKey: ['instructorDashboard'],
    queryFn: reportsService.getInstructorDashboard,
  });
};

export const useInstructorSales = (dateFrom?: string, dateTo?: string) => {
  return useQuery({
    queryKey: ['instructorSales', dateFrom, dateTo],
    queryFn: () => reportsService.getInstructorSales(dateFrom, dateTo),
  });
};

export const useInstructorCourses = () => {
  return useQuery({
    queryKey: ['instructorCourses'],
    queryFn: reportsService.getInstructorCourses,
  });
};

export const useInstructorStudents = () => {
  return useQuery({
    queryKey: ['instructorStudents'],
    queryFn: reportsService.getInstructorStudents,
  });
};

export const useInstructorPayouts = () => {
  return useQuery({
    queryKey: ['instructorPayouts'],
    queryFn: reportsService.getInstructorPayouts,
  });
};

export const useStudentDashboard = () => {
  return useQuery({
    queryKey: ['studentDashboard'],
    queryFn: reportsService.getStudentDashboard,
  });
};

export const useStudentProgress = () => {
  return useQuery({
    queryKey: ['studentProgress'],
    queryFn: reportsService.getStudentProgress,
  });
};

export const useStudentCertificates = () => {
  return useQuery({
    queryKey: ['studentCertificates'],
    queryFn: reportsService.getStudentCertificates,
  });
};

export const useStudentActivity = () => {
  return useQuery({
    queryKey: ['studentActivity'],
    queryFn: reportsService.getStudentActivity,
  });
};

// ============ Badge Hooks ============
export const useBadges = () => {
  return useQuery({
    queryKey: ['badges'],
    queryFn: badgeService.getAll,
  });
};

export const useMyBadges = () => {
  return useQuery({
    queryKey: ['myBadges'],
    queryFn: badgeService.getMy,
  });
};

// ============ Dashboard Hook ============
export const useDashboard = () => {
  return useQuery({
    queryKey: ['dashboard'],
    queryFn: dashboardService.getData,
  });
};

// ============ Home Page Hook ============
export const useHomePage = () => {
  return useQuery({
    queryKey: ['homePage'],
    queryFn: homeService.getData,
  });
};

// ============ Settings Hooks ============
export const useSiteSettings = () => {
  return useQuery({
    queryKey: ['siteSettings'],
    queryFn: settingsService.get,
    staleTime: 1000 * 60 * 60, // 1 hour
  });
};

export const usePage = (slug: string) => {
  return useQuery({
    queryKey: ['page', slug],
    queryFn: () => settingsService.getPage(slug),
    enabled: !!slug,
  });
};

export const useFAQs = () => {
  return useQuery({
    queryKey: ['faqs'],
    queryFn: settingsService.getFAQs,
  });
};

export const useTestimonials = () => {
  return useQuery({
    queryKey: ['testimonials'],
    queryFn: settingsService.getTestimonials,
  });
};

export const useLanguages = () => {
  return useQuery({
    queryKey: ['languages'],
    queryFn: settingsService.getLanguages,
  });
};

export const useCurrencies = () => {
  return useQuery({
    queryKey: ['currencies'],
    queryFn: settingsService.getCurrencies,
  });
};

export const useCustomPages = () => {
  return useQuery({
    queryKey: ['customPages'],
    queryFn: settingsService.getCustomPages,
  });
};

export const useFilterOptions = () => {
  return useQuery({
    queryKey: ['filterOptions'],
    queryFn: settingsService.getFilterOptions,
  });
};

// ============ Contact Hook ============
export const useSubmitContact = () => {
  return useMutation({
    mutationFn: (data: ContactRequest) => contactService.submit(data),
  });
};

// ============ Newsletter Hook ============
export const useSubscribeNewsletter = () => {
  return useMutation({
    mutationFn: (email: string) => newsletterService.subscribe(email),
  });
};

// ============ Search Hook ============
export const useSearch = (query: string) => {
  return useQuery({
    queryKey: ['search', query],
    queryFn: () => searchService.search(query),
    enabled: query.length > 2,
  });
};

// ============ Auth Hooks ============
export const useLogin = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ email, password }: { email: string; password: string }) =>
      authService.login(email, password),
    onSuccess: (response) => {
      if (response.status && response.data?.token) {
        localStorage.setItem('auth_token', response.data.token);
        localStorage.setItem('user', JSON.stringify(response.data));
        queryClient.invalidateQueries({ queryKey: ['profile'] });
      }
    },
  });
};

export const useRegister = () => {
  return useMutation({
    mutationFn: ({ firstName, lastName, email, password }: { firstName: string; lastName: string; email: string; password: string }) =>
      authService.register(firstName, lastName, email, password),
  });
};

export const useForgotPassword = () => {
  return useMutation({
    mutationFn: (email: string) => authService.forgotPassword(email),
  });
};

export const useSocialLogin = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: SocialLoginRequest) => authService.socialLogin(data),
    onSuccess: (response) => {
      if (response.status && response.data?.token) {
        localStorage.setItem('auth_token', response.data.token);
        localStorage.setItem('user', JSON.stringify(response.data));
        queryClient.invalidateQueries({ queryKey: ['profile'] });
      }
    },
  });
};

export const useProfile = () => {
  return useQuery({
    queryKey: ['profile'],
    queryFn: authService.getProfile,
    enabled: !!localStorage.getItem('auth_token'),
  });
};

export const useUpdateProfile = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (data: Partial<{ first_name: string; last_name: string; biography: string; title: string }>) =>
      authService.updateProfile(data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['profile'] });
    },
  });
};

export const useChangePassword = () => {
  return useMutation({
    mutationFn: ({ currentPassword, newPassword }: { currentPassword: string; newPassword: string }) =>
      authService.changePassword(currentPassword, newPassword),
  });
};

// ============ Backward Compatibility Hooks ============
// These are kept for backward compatibility with existing components

export const useTopCourses = (limit = 8) => {
  return useQuery({
    queryKey: ['topCourses', limit],
    queryFn: () => courseService.getPopular(),
  });
};

export const useLatestCourses = (limit = 8) => {
  return useQuery({
    queryKey: ['latestCourses', limit],
    queryFn: () => courseService.getAll({ limit, sort: 'newest' }),
  });
};

export const useFreeCourses = (limit = 8) => {
  return useQuery({
    queryKey: ['freeCourses', limit],
    queryFn: () => courseService.getAll({ limit, price_type: 'free' }),
  });
};

export const useUpcomingCourses = (limit = 8) => {
  return useQuery({
    queryKey: ['upcomingCourses', limit],
    queryFn: () => courseService.getAll({ limit }),
  });
};

export const useDiscountedCourses = (limit = 8) => {
  return useQuery({
    queryKey: ['discountedCourses', limit],
    queryFn: () => courseService.getAll({ limit }),
  });
};

export const usePaymentGateways = () => {
  return useQuery({
    queryKey: ['paymentGateways'],
    queryFn: paymentService.getMethods,
  });
};

export const usePurchaseHistory = () => {
  return useQuery({
    queryKey: ['purchaseHistory'],
    queryFn: paymentService.getHistory,
  });
};

// Alias for marking lesson complete (backward compatibility)
export const useMarkLessonComplete = () => {
  return useCompleteLesson();
};
