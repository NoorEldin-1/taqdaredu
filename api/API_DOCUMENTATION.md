# Academy LMS - API Documentation v2.0

## Base URL
```
https://my-communication.uk
```

## Authentication
Most endpoints require authentication using JWT tokens.

**Header:**
```
Authorization: Bearer <token>
```

**Or Query Parameter:**
```
?auth_token=<token>
```

---

# 📚 TABLE OF CONTENTS

1. [Authentication](#authentication-api)
2. [Courses API](#courses-api)
3. [Payment API](#payment-api)
4. [Notifications API](#notifications-api)
5. [Messages API](#messages-api)
6. [Reports API](#reports-api)
7. [Admin API](#admin-api)
8. [Webhooks API](#webhooks-api)

---

# 🔐 AUTHENTICATION API

## Login
```http
POST /api_frontend/login
```
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| email | string | Yes | User email |
| password | string | Yes | User password |

**Response:**
```json
{
  "status": true,
  "data": {
    "id": 1,
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
  }
}
```

## Register
```http
POST /api_frontend/register
```
| Parameter | Type | Required |
|-----------|------|----------|
| first_name | string | Yes |
| last_name | string | Yes |
| email | string | Yes |
| password | string | Yes |

## Forgot Password
```http
POST /api_frontend/forgot_password
```
| Parameter | Type | Required |
|-----------|------|----------|
| email | string | Yes |

---

# 📚 COURSES API

## List Courses
```http
GET /api_courses/list
```
| Parameter | Type | Description |
|-----------|------|-------------|
| page | int | Page number (default: 1) |
| limit | int | Items per page (default: 20) |
| category_id | int | Filter by category |
| subcategory_id | int | Filter by subcategory |
| instructor_id | int | Filter by instructor |
| level | string | beginner/intermediate/advanced |
| language | string | Filter by language |
| price_type | string | free/paid/all |
| min_price | float | Minimum price |
| max_price | float | Maximum price |
| min_rating | float | Minimum rating (1-5) |
| search | string | Search keyword |
| sort | string | newest/popular/rating/price_low/price_high |

## Course Details
```http
GET /api_courses/detail/{course_id}
```

**Response:**
```json
{
  "status": true,
  "data": {
    "id": 1,
    "title": "Course Title",
    "description": "Full description",
    "price": 99.00,
    "discounted_price": 49.00,
    "thumbnail_url": "https://...",
    "instructor": {
      "id": 1,
      "name": "Instructor Name",
      "image": "https://..."
    },
    "curriculum": [...],
    "requirements": [...],
    "outcomes": [...],
    "recent_reviews": [...]
  }
}
```

## Course Curriculum
```http
GET /api_courses/curriculum/{course_id}
```

## Course Reviews
```http
GET /api_courses/reviews/{course_id}
```
| Parameter | Type | Description |
|-----------|------|-------------|
| page | int | Page number |
| limit | int | Items per page |

## Add Review 🔒
```http
POST /api_courses/review/{course_id}
```
| Parameter | Type | Required |
|-----------|------|----------|
| rating | int | Yes (1-5) |
| review | string | No |

## Course Progress 🔒
```http
GET /api_courses/progress/{course_id}
```

## Complete Lesson 🔒
```http
POST /api_courses/complete_lesson
```
| Parameter | Type | Required |
|-----------|------|----------|
| lesson_id | int | Yes |
| course_id | int | Yes |

## Get Certificate 🔒
```http
GET /api_courses/certificate/{course_id}
```

## Featured Courses
```http
GET /api_courses/featured
```

## Popular Courses
```http
GET /api_courses/popular
```

## Course Levels
```http
GET /api_courses/levels
```

## Course Languages
```http
GET /api_courses/languages
```

---

# 💳 PAYMENT API

## Payment Methods
```http
GET /api_payment/methods
```

**Response:**
```json
{
  "status": true,
  "data": [
    {
      "id": "stripe",
      "name": "Credit/Debit Card",
      "icon": "https://...",
      "publishable_key": "pk_..."
    },
    {
      "id": "paypal",
      "name": "PayPal",
      "icon": "https://..."
    }
  ]
}
```

## Initiate Payment 🔒
```http
POST /api_payment/initiate
```
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| course_id | int | Yes | Course to purchase |
| payment_method | string | Yes | stripe/paypal/razorpay |
| coupon_code | string | No | Discount coupon |

**Response:**
```json
{
  "status": true,
  "data": {
    "payment_token": "abc123...",
    "amount": 49.00,
    "currency": "USD",
    "payment_info": {
      "publishable_key": "pk_...",
      "amount": 4900
    }
  }
}
```

## Verify Payment 🔒
```http
POST /api_payment/verify
```
| Parameter | Type | Required |
|-----------|------|----------|
| payment_token | string | Yes |
| transaction_id | string | Yes |

## Payment History 🔒
```http
GET /api_payment/history
```

## Get Invoice 🔒
```http
GET /api_payment/invoice/{payment_id}
```

## Apply Coupon 🔒
```http
POST /api_payment/apply_coupon
```
| Parameter | Type | Required |
|-----------|------|----------|
| coupon_code | string | Yes |
| course_id | int | Yes |

## Offline Payment 🔒
```http
POST /api_payment/offline
```
| Parameter | Type | Required |
|-----------|------|----------|
| course_id | int | Yes |
| document | file | No |

## Cart Operations 🔒

### Get Cart
```http
GET /api_payment/cart
```

### Add to Cart
```http
POST /api_payment/cart/add
```
| Parameter | Type | Required |
|-----------|------|----------|
| course_id | int | Yes |

### Remove from Cart
```http
DELETE /api_payment/cart/remove/{course_id}
```

### Clear Cart
```http
DELETE /api_payment/cart/clear
```

### Checkout
```http
POST /api_payment/checkout
```
| Parameter | Type | Required |
|-----------|------|----------|
| payment_method | string | Yes |
| coupon_code | string | No |

---

# 🔔 NOTIFICATIONS API

## List Notifications 🔒
```http
GET /api_notifications/list
```
| Parameter | Type | Description |
|-----------|------|-------------|
| page | int | Page number |
| limit | int | Items per page |
| unread_only | int | 1 = unread only |

## Mark as Read 🔒
```http
PUT /api_notifications/read/{notification_id}
```

## Mark All as Read 🔒
```http
PUT /api_notifications/read_all
```

## Delete Notification 🔒
```http
DELETE /api_notifications/delete/{notification_id}
```

## Delete All 🔒
```http
DELETE /api_notifications/delete_all
```

## Get Settings 🔒
```http
GET /api_notifications/settings
```

## Update Settings 🔒
```http
PUT /api_notifications/settings
```

## Register Push Token 🔒
```http
POST /api_notifications/push_token
```
| Parameter | Type | Required |
|-----------|------|----------|
| token | string | Yes |
| device_type | string | Yes (android/ios/web) |

## Unread Count 🔒
```http
GET /api_notifications/unread_count
```

---

# 💬 MESSAGES API

## Get Threads 🔒
```http
GET /api_messages/threads
```

## Get Thread Messages 🔒
```http
GET /api_messages/thread/{thread_code}
```
| Parameter | Type | Description |
|-----------|------|-------------|
| page | int | Page number |
| limit | int | Items per page |

## Send Message 🔒
```http
POST /api_messages/send
```
| Parameter | Type | Required |
|-----------|------|----------|
| receiver_id | int | Yes |
| message | string | Yes |
| thread_code | string | No |

## Delete Message 🔒
```http
DELETE /api_messages/delete/{message_id}
```

## Delete Thread 🔒
```http
DELETE /api_messages/thread/{thread_code}
```

## Unread Count 🔒
```http
GET /api_messages/unread_count
```

## Start Thread 🔒
```http
POST /api_messages/start_thread
```
| Parameter | Type | Required |
|-----------|------|----------|
| receiver_id | int | Yes |

## Mark as Read 🔒
```http
PUT /api_messages/mark_read
```
| Parameter | Type | Description |
|-----------|------|-------------|
| thread_code | string | Mark entire thread |
| message_ids | array | Mark specific messages |

---

# 📊 REPORTS API

## Instructor Reports 🔒

### Dashboard
```http
GET /api_reports/instructor_dashboard
```

### Sales Report
```http
GET /api_reports/instructor_sales
```
| Parameter | Type | Description |
|-----------|------|-------------|
| date_from | date | Start date (Y-m-d) |
| date_to | date | End date (Y-m-d) |

### Courses Performance
```http
GET /api_reports/instructor_courses
```

### Students List
```http
GET /api_reports/instructor_students
```

### Payout History
```http
GET /api_reports/instructor_payouts
```

## Student Reports 🔒

### Dashboard
```http
GET /api_reports/student_dashboard
```

### Course Progress
```http
GET /api_reports/student_progress
```

### Certificates
```http
GET /api_reports/student_certificates
```

### Activity Timeline
```http
GET /api_reports/student_activity
```

---

# 👨‍💼 ADMIN API

## Admin Login
```http
POST /api_admin/login
```
| Parameter | Type | Required |
|-----------|------|----------|
| email | string | Yes |
| password | string | Yes |

## Dashboard 🔒👑
```http
GET /api_admin/dashboard
```

**Response:**
```json
{
  "status": true,
  "data": {
    "total_users": 1500,
    "total_courses": 45,
    "total_enrollments": 3200,
    "total_revenue": 45000,
    "recent_enrollments": [...],
    "revenue_chart": [...]
  }
}
```

## Users Management 🔒👑

### List Users
```http
GET /api_admin/users
```
| Parameter | Type | Description |
|-----------|------|-------------|
| page | int | Page number |
| limit | int | Items per page |
| role | string | admin/user/instructor |
| status | int | 0/1 |
| search | string | Search by name/email |

### Get User
```http
GET /api_admin/user/{user_id}
```

### Create User
```http
POST /api_admin/user
```

### Update User
```http
PUT /api_admin/user/{user_id}
```

### Delete User
```http
DELETE /api_admin/user/{user_id}
```

## Courses Management 🔒👑

### List Courses
```http
GET /api_admin/courses
```

### Get Course
```http
GET /api_admin/course/{course_id}
```

### Update Status
```http
PUT /api_admin/course/{course_id}/status
```
| Parameter | Type | Required |
|-----------|------|----------|
| status | string | Yes (active/pending/draft) |

### Delete Course
```http
DELETE /api_admin/course/{course_id}
```

## Categories Management 🔒👑

### List Categories
```http
GET /api_admin/categories
```

### Create Category
```http
POST /api_admin/category
```

### Update Category
```http
PUT /api_admin/category/{category_id}
```

### Delete Category
```http
DELETE /api_admin/category/{category_id}
```

## Payments 🔒👑
```http
GET /api_admin/payments
```

## Enrollments 🔒👑
```http
GET /api_admin/enrollments
POST /api_admin/enrollment
DELETE /api_admin/enrollment/{id}
```

## Payouts 🔒👑
```http
GET /api_admin/payouts
PUT /api_admin/payout/{id}/status
```

## Coupons 🔒👑
```http
GET /api_admin/coupons
POST /api_admin/coupon
PUT /api_admin/coupon/{id}
DELETE /api_admin/coupon/{id}
```

## Settings 🔒👑
```http
GET /api_admin/settings
PUT /api_admin/settings
```

## Reports 🔒👑
```http
GET /api_admin/reports/sales
GET /api_admin/reports/users
GET /api_admin/reports/courses
```

## Instructor Applications 🔒👑
```http
GET /api_admin/instructor_applications
POST /api_admin/instructor_application/{user_id}/action
```
| Parameter | Type | Required |
|-----------|------|----------|
| action | string | Yes (approve/reject) |
| reason | string | No (for rejection) |

---

# 🔗 WEBHOOKS API

## List Webhooks 🔒
```http
GET /api_webhooks/list
```

## Create Webhook 🔒
```http
POST /api_webhooks/create
```
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| url | string | Yes | Webhook URL (HTTPS) |
| name | string | No | Webhook name |
| events | array | No | Events to subscribe |

**Response:**
```json
{
  "status": true,
  "data": {
    "id": 1,
    "secret": "abc123...",
    "events": ["course.enrolled", "payment.success"]
  }
}
```

## Update Webhook 🔒
```http
PUT /api_webhooks/update/{webhook_id}
```

## Delete Webhook 🔒
```http
DELETE /api_webhooks/delete/{webhook_id}
```

## Test Webhook 🔒
```http
POST /api_webhooks/test/{webhook_id}
```

## Regenerate Secret 🔒
```http
POST /api_webhooks/regenerate_secret/{webhook_id}
```

## Available Events
```http
GET /api_webhooks/events
```

**Available Events:**
| Event | Description |
|-------|-------------|
| course.enrolled | User enrolls in a course |
| course.completed | User completes a course |
| payment.success | Payment successful |
| payment.failed | Payment failed |
| user.registered | New user registered |
| user.updated | User profile updated |
| course.created | New course created |
| course.updated | Course updated |
| lesson.completed | Lesson completed |
| certificate.issued | Certificate issued |

## Delivery History 🔒
```http
GET /api_webhooks/deliveries/{webhook_id}
```

## Retry Delivery 🔒
```http
POST /api_webhooks/retry/{delivery_id}
```

---

# 📋 ERROR RESPONSES

```json
{
  "status": false,
  "message": "Error description"
}
```

| HTTP Code | Description |
|-----------|-------------|
| 400 | Bad Request - Invalid parameters |
| 401 | Unauthorized - Invalid/missing token |
| 403 | Forbidden - Permission denied |
| 404 | Not Found - Resource doesn't exist |
| 409 | Conflict - Already exists |
| 429 | Too Many Requests - Rate limited |
| 500 | Server Error |

---

# 📌 LEGEND

- 🔒 = Requires Authentication
- 👑 = Requires Admin Role

---

# 📁 FILES TO UPLOAD

```
application/
├── controllers/
│   ├── Api_admin.php
│   ├── Api_courses.php
│   ├── Api_messages.php
│   ├── Api_notifications.php
│   ├── Api_payment.php
│   ├── Api_reports.php
│   └── Api_webhooks.php
├── models/
│   └── Api_admin_model.php
├── libraries/
│   └── Api_security.php
├── config/
│   └── routes.php (updated)
└── sql/
    └── api_migration.sql
```

# ⚙️ SETUP INSTRUCTIONS

1. Upload all files to server
2. Run database migration:
   ```sql
   mysql -u user -p database < application/sql/api_migration.sql
   ```
3. Test endpoints using Postman or curl
