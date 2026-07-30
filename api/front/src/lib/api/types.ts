// ============ Authentication Types ============
export interface User {
  id: number;
  user_id?: number;
  first_name: string;
  last_name: string;
  email: string;
  token: string;
  image?: string;
  biography?: string;
  title?: string;
  skills?: string[];
  social_links?: Record<string, string>;
  is_instructor?: boolean;
}

export interface LoginRequest {
  email: string;
  password: string;
}

export interface RegisterRequest {
  first_name: string;
  last_name?: string;
  email: string;
  password: string;
}

export interface ResetPasswordRequest {
  email: string;
  otp: string;
  new_password: string;
}

export interface SocialLoginRequest {
  provider: string;
  social_id: string;
  email: string;
  first_name: string;
  last_name?: string;
  image?: string;
}

// ============ Course Types ============
export interface Course {
  id: number;
  title: string;
  slug?: string;
  short_description?: string;
  description?: string;
  thumbnail?: string;
  thumbnail_url?: string;
  image_url?: string;
  video_url?: string | null;
  video_type?: string | null;
  price: number;
  discounted_price?: number;
  is_free?: boolean;
  level?: string;
  language?: string;
  duration?: string;
  total_lessons?: number;
  total_sections?: number;
  rating?: number;
  total_ratings?: number;
  total_students?: number;
  requirements?: string[];
  outcomes?: string[];
  category?: Category;
  sub_category?: Category;
  instructor?: Instructor;
  sections?: Section[];
  curriculum?: CourseCurriculum;
  recent_reviews?: Review[];
  is_wishlisted?: boolean;
  is_enrolled?: boolean;
  progress?: number;
  is_featured?: boolean;
  is_upcoming?: boolean;
  start_date?: string;
  created_at?: string;
  updated_at?: string;
}

export interface CoursesParams {
  page?: number;
  limit?: number;
  category_id?: number;
  subcategory_id?: number;
  instructor_id?: number;
  level?: string;
  language?: string;
  price_type?: string;
  min_price?: number;
  max_price?: number;
  min_rating?: number;
  search?: string;
  sort?: string;
}

export interface CourseProgress {
  progress_percentage: number;
  completed_lessons: number;
  total_lessons: number;
  sections: {
    id: number;
    title: string;
    completed: boolean;
    lessons: {
      id: number;
      title: string;
      completed: boolean;
    }[];
  }[];
}

export interface CourseCurriculum {
  sections: {
    id: number;
    title: string;
    lessons: {
      id: number;
      title: string;
      duration?: string;
      type?: string;
      lesson_type?: string;
      is_free?: boolean;
      is_completed?: boolean;
      video_url?: string | null;
      video_type?: string | null;
      summary?: string;
      attachment_url?: string | null;
      attachment_type?: string | null;
    }[];
  }[];
}

// ============ Category Types ============
export interface Category {
  id: number;
  name: string;
  slug?: string;
  icon?: string;
  thumbnail?: string;
  course_count?: number;
  sub_categories?: Category[];
}

// ============ Instructor Types ============
export interface Instructor {
  id: number;
  first_name: string;
  last_name: string;
  name?: string;
  email?: string;
  image?: string;
  title?: string;
  biography?: string;
  rating?: number;
  total_students?: number;
  total_courses?: number;
  total_reviews?: number;
  skills?: string[];
  is_following?: boolean;
  social_links?: {
    facebook?: string;
    twitter?: string;
    linkedin?: string;
    website?: string;
  };
  courses?: Course[];
}

export interface InstructorApplication {
  address?: string;
  phone?: string;
  message?: string;
}

export interface InstructorApplicationStatus {
  status: 'pending' | 'approved' | 'rejected';
  message?: string;
  submitted_at?: string;
}

// ============ Section & Lesson Types ============
export interface Section {
  id: number;
  title: string;
  order?: number;
  lessons?: Lesson[];
  total_duration?: string;
  completed?: boolean;
}

export interface Lesson {
  id: number;
  title: string;
  duration?: string;
  type?: string;
  is_free?: boolean;
  is_completed?: boolean;
  video_url?: string | null;
  video_type?: string | null;
  summary?: string;
  article_content?: string;
  attachment_url?: string | null;
  attachment_type?: string | null;
  attachments?: Attachment[];
  has_quiz?: boolean;
}

export interface Attachment {
  id: number;
  title: string;
  file_url: string;
  file_type?: string;
}

export interface LessonComment {
  id: number;
  user: {
    id: number;
    name: string;
    image?: string;
  };
  comment: string;
  created_at: string;
}

// ============ Quiz Types ============
export interface Quiz {
  id: number;
  title: string;
  questions: QuizQuestion[];
  pass_mark?: number;
}

export interface QuizQuestion {
  id: number;
  title: string;
  question?: string;
  type?: string;
  options: string[];
  correct_answer?: number;
}

export interface QuizAnswer {
  question_id: number;
  answer: number;
}

export interface QuizResult {
  score: number;
  total: number;
  correct?: number;
  passed?: boolean;
  correct_answers?: Record<number, number>;
}

// ============ Review Types ============
export interface Review {
  id: number;
  user: {
    id: number;
    name: string;
    image?: string;
  };
  rating: number;
  review?: string;
  created_at: string;
}

export interface ReviewStats {
  average_rating: number;
  total_reviews: number;
  rating_breakdown: {
    5: number;
    4: number;
    3: number;
    2: number;
    1: number;
  };
}

// ============ Blog Types ============
export interface Blog {
  id: number;
  title: string;
  slug?: string;
  excerpt?: string;
  content?: string;
  thumbnail?: string;
  image_url?: string;
  category?: BlogCategory;
  author?: {
    id: number;
    name: string;
    image?: string;
  };
  read_time?: string;
  views?: number;
  created_at: string;
  comments?: BlogComment[];
}

export interface BlogCategory {
  id: number;
  name: string;
  slug?: string;
  blog_count?: number;
}

export interface BlogComment {
  id: number;
  user: {
    id: number;
    name: string;
    image?: string;
  };
  comment: string;
  created_at: string;
  parent_id?: number;
  replies?: BlogComment[];
}

// ============ Cart & Wishlist Types ============
export interface CartItem {
  id: number;
  course: Course;
}

export interface Cart {
  items: CartItem[];
  total: number;
  discount?: number;
  coupon?: string;
}

// ============ Payment Types ============
export interface PaymentMethod {
  id: string;
  name: string;
  description?: string;
  icon?: string;
  is_active?: boolean;
  publishable_key?: string;
}

export interface PaymentInitiation {
  payment_token: string;
  amount: number;
  currency: string;
  payment_info?: {
    publishable_key?: string;
    amount?: number;
  };
}

export interface PaymentHistory {
  id: number;
  invoice_number?: string;
  date: string;
  amount: number;
  status: string;
  payment_method?: string;
  course?: Course;
}

export interface Invoice {
  id: number;
  invoice_number: string;
  date: string;
  items: {
    course: Course;
    price: number;
  }[];
  subtotal: number;
  discount: number;
  tax: number;
  total: number;
  payment_method: string;
  status: string;
}

export interface CouponResult {
  original_total: number;
  discount: number;
  discount_percentage: number;
  final_total: number;
}

export interface PurchaseHistory {
  id: number;
  invoice_number: string;
  date: string;
  total: number;
  status: string;
  courses: Course[];
}

// ============ Checkout Types ============
export interface CheckoutData {
  items: CartItem[];
  payment_gateways: PaymentMethod[];
  subtotal: number;
  tax: number;
  total: number;
  currency: string;
  currency_symbol: string;
}

export interface PaymentGateway {
  id: string;
  name: string;
  description?: string;
  icon?: string;
  is_active: boolean;
}

export interface PaymentInitiate {
  gateway: string;
  course_ids: number[];
  coupon_code?: string;
}

export interface PaymentVerify {
  session_id: string;
  transaction_id: string;
}

// ============ Notification Types ============
export interface Notification {
  id: number;
  title: string;
  message: string;
  type: string;
  is_read: boolean;
  data?: Record<string, unknown>;
  created_at: string;
}

export interface NotificationSettings {
  email_notifications?: boolean;
  push_notifications?: boolean;
  course_updates?: boolean;
  promotions?: boolean;
  messages?: boolean;
}

// ============ Message Types ============
export interface MessageThread {
  id: number;
  thread_code: string;
  participant: {
    id: number;
    name: string;
    image?: string;
  };
  last_message?: string;
  unread_count: number;
  updated_at: string;
}

export interface Message {
  id: number;
  sender_id: number;
  message: string;
  created_at: string;
  is_read: boolean;
}

// ============ Reports Types ============
export interface InstructorDashboard {
  total_students: number;
  total_courses: number;
  total_earnings: number;
  pending_payouts: number;
  recent_enrollments: {
    student: User;
    course: Course;
    enrolled_at: string;
  }[];
  earnings_chart: {
    date: string;
    amount: number;
  }[];
}

export interface InstructorSalesReport {
  total_sales: number;
  total_revenue: number;
  sales: {
    date: string;
    course: Course;
    amount: number;
    student: User;
  }[];
}

export interface StudentDashboard {
  enrolled_courses: number;
  completed_courses: number;
  in_progress_courses: number;
  certificates_earned: number;
  recent_courses: Course[];
  learning_time: number;
}

export interface StudentProgress {
  course: Course;
  progress_percentage: number;
  completed_lessons: number;
  total_lessons: number;
  last_accessed: string;
}

export interface StudentActivity {
  id: number;
  type: string;
  description: string;
  course?: Course;
  created_at: string;
}

// ============ Settings Types ============
export interface SiteSettings {
  site_name?: string;
  site_logo?: string;
  light_logo?: string;
  dark_logo?: string;
  site_favicon?: string;
  currency?: string;
  currency_symbol?: string;
  phone?: string;
  email?: string;
  address?: string;
  facebook?: string;
  twitter?: string;
  linkedin?: string;
  youtube?: string;
  instagram?: string;
  tiktok?: string;
  social_links?: {
    facebook?: string;
    twitter?: string;
    linkedin?: string;
    youtube?: string;
    instagram?: string;
  };
  about_text?: string;
  footer_text?: string;
  banner_image?: string;
  blog_page_banner?: string;
  [key: string]: unknown;
}

export interface Language {
  code: string;
  name: string;
  is_rtl?: boolean;
}

export interface Currency {
  code: string;
  name: string;
  symbol: string;
  exchange_rate?: number;
}

// ============ Page Types ============
export interface Page {
  id: number;
  title: string;
  slug: string;
  content: string;
}

export interface CustomPage {
  id: number;
  title: string;
  slug: string;
  content: string;
  meta_title?: string;
  meta_description?: string;
}

// ============ FAQ Types ============
export interface FAQ {
  id: number;
  question: string;
  answer: string;
  order?: number;
}

// ============ Contact Types ============
export interface ContactRequest {
  first_name: string;
  last_name?: string;
  email: string;
  phone?: string;
  message: string;
  i_agree?: number;
}

// ============ Search Types ============
export interface SearchResults {
  courses?: Course[];
  instructors?: Instructor[];
  blogs?: Blog[];
}

// ============ Home Page Types ============
export interface HomePageData {
  banner?: {
    title: string;
    subtitle: string;
    image?: string;
    video?: string;
  };
  top_courses?: Course[];
  latest_courses?: Course[];
  free_courses?: Course[];
  top_categories?: Category[];
  top_instructors?: Instructor[];
  upcoming_courses?: Course[];
  stats?: {
    total_courses: number;
    total_students: number;
    total_instructors: number;
    total_countries: number;
  };
  recent_blogs?: Blog[];
  faqs?: FAQ[];
  sections_visibility?: Record<string, boolean>;
}

// ============ Filter Options Types ============
export interface FilterOptions {
  categories: Category[];
  languages: { code: string; name: string }[];
  levels: { value: string; label: string }[];
  price_options: { value: string; label: string }[];
  sort_options: { value: string; label: string }[];
}

// ============ Badge Types ============
export interface Badge {
  id: number;
  name: string;
  description: string;
  icon: string;
  requirements?: string;
}

export interface UserBadge extends Badge {
  earned_at: string;
}

// ============ Certificate Types ============
export interface Certificate {
  id: number;
  course_id: number;
  course_title: string;
  user_name: string;
  completion_date: string;
  certificate_url: string;
  certificate_code: string;
}

// ============ Dashboard Types ============
export interface DashboardData {
  enrolled_courses: number;
  completed_courses: number;
  in_progress_courses: number;
  certificates_earned: number;
  recent_courses: Course[];
  notifications: Notification[];
  recent_purchases: PurchaseHistory[];
}

// ============ Course Comparison Types ============
export interface CourseComparison {
  courses: Course[];
  comparison_fields: {
    field: string;
    values: string[];
  }[];
}
