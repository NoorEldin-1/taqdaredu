# CLAUDE.md

إرشادات للعمل على هذا المستودع.

## ما هذا المشروع

منصّة **تقدّر** التعليمية (`taqdaredu.com`) — تطبيق **CodeIgniter 3** على PHP 8.2
و MariaDB، مبنيّ على قاعدة **Academy LMS** التجارية (Creativeitem) ومُوسَّع
بطبقة `Taqdar_*` هي جوهر المنتج. الواجهة مُصيَّرة من الخادم بقوالب PHP —
لا SPA ولا خطوة بناء ولا npm.

هذا المستودع مرآةٌ لجذر الموقع المنشور: `/home/taqdaredu.com/public_html`.

## البنية

```
index.php                 نقطة الدخول — تعرّف ENVIRONMENT من $_SERVER['CI_ENV']
.htaccess                 تحويلات، ترويسات أمان، والبيئة المحلّية
application/
  controllers/            متحكّمات
  models/                 نماذج
  views/                  frontend · backend · components · lessons · payment-global
  helpers/                دوالّ مساعدة (تُحمَّل تلقائيًا — انظر autoload.php)
  config/                 routes.php · config.php · database.php* · taqdar_secret.php*
  libraries/              مكتبات خارجيّة: Stripe · xendit · razorpay · openpayu · phpword · phpqrcode
system/                   نواة CodeIgniter 3 — طرف ثالث، لا تُعدَّل
assets/                   frontend · backend · global · taqdar (سِمة تقدّر) · playing-page
uploads/                  محتوى المستخدمين — خارج git
languages/                ملفّات الترجمة
```

`*` مستثنى من git — انظر [SETUP.md](SETUP.md).

## طبقتان لا واحدة

الفهم المفتاح: `Academy LMS` الأصلي و `Taqdar` يتعايشان في الشجرة نفسها.

| الأصل (Academy LMS) | طبقة تقدّر |
|---|---|
| `Home.php` · `Admin.php` · `User.php` · `Api.php` | `Taqdar.php` · `Taqdar_admin.php` · `Taqdar_gate.php` · `Taqdar_cron_events.php` |
| `Crud_model.php` (226 ك.ب) · `User_model.php` | `Taqdar_repo_model.php` · `Taqdar_wallet_model.php` · `Taqdar_billing_model.php` · `Taqdar_teacher_model.php` · `Taqdar_parent_model.php` · `Taqdar_marking_model.php` |
| `views/frontend`, `views/backend` | `views/frontend/tq_*` و `assets/taqdar/` |

الميزات الجديدة تُكتب في طبقة `Taqdar_*`. تعديل `Crud_model.php` أو
`Admin.php` يمسّ شيفرةً مشتركةً مع مسارات LMS الأصلية، فتوسَّع بحذرٍ وبفهم
من يناديها.

## التوجيه

`application/config/routes.php` هو المرجع، وترتيبه مقصود:

1. مسارات الإضافات (شهادات، حزم دورات، كتب إلكترونية، حجز مدرّسين).
2. **مسارات الكتابة قبل مسارات العرض.** `(:any)` في CI3 تعني `[^/]+` أي
   مقطعًا واحدًا؛ فبلا قاعدة صريحة يسقط `teacher/upload/save` إلى
   `Taqdar::teacher('upload','save')` — تُعرَض الشاشة ردًّا على طلب حفظ،
   بلا حفظٍ ولا خطأ. القواعد الصريحة هي ما يمنع هذا الصمت.
3. بوّابات الأدوار: `/student/*` · `/teacher/*` · `/parent/*` تُوجَّه كلّها
   إلى `Taqdar.php`. البادئة القديمة `taqdar/*` محوَّلة إليها بـ301 من
   `.htaccess`، والمرادفات في `routes.php` احتياطٌ لو تغيّر التحويل.
4. صفحات عامّة نظيفة: `/plans` · `/courses` · `/about` … إلخ.

عند إضافة صفحة: قاعدة في `routes.php` **و** دالّة في المتحكّم **و** قالب في
`views/`. ولو كانت كتابةً، ضع قاعدتها قبل قواعد العرض.

## الأدوار والحرّاس

أربعة أدوار: **طالب · معلّم · وليّ أمر · إدارة**. داخل `Taqdar.php`:

- `require_login()` و `require_role($role)` — حرّاس العرض.
- `write_guard($role)` — حارس الكتابة؛ مسارات الحفظ POST فقط وترفض GET
  بـ `show_404()`.
- `teacher_owns_course()` · `teacher_owns_lesson()` · `parent_owns_child()`
  — تحقّق الملكيّة. أيّ مسار كتابةٍ جديد يمرّ بالمناسب منها، وإلّا صار
  تعديل صفٍّ لا يملكه المستخدم مجرّد تخمين مُعرِّف.

## قاعدة البيانات

`taqd_lms` — **75 جدولًا**. لا ORM ولا هجرات؛ استعلامات Query Builder
مباشرة في النماذج.

- **الجوهر:** `users` · `role` · `course` · `lesson` · `section` · `category` · `subjects` · `grades`
- **تقدّر:** `paths` · `plans` · `subscriptions` · `milestones` · `objectives` · `skill_state` · `wallets` · `wallet_entries` · `parent_links` · `review_queue` · `assessments` · `attempts`
- **الإعدادات:** `settings` (97 صفًّا) · `frontend_settings` · `payment_gateways` · `seo_fields` — **مفاتيح بوّابات الدفع و SMTP تعيش هنا، لا في الشيفرة.**
- **triggerان:** `trg_parent_links_consent_*` على `parent_links`.

جدول `language` (1872 صفًّا) يحمل الترجمات إلى جانب `languages/`.

## الأصول

`assets/taqdar/` هي سِمة تقدّر (`brand` · `css` · `fonts` · `js` · `site`) وهي
المقصودة عند تعديل مظهر واجهة المنصّة. `assets/frontend` و `assets/backend`
أصولُ قالب Academy LMS الأصلي. لا خطوة بناء: تُحرَّر ملفّات CSS و JS مباشرةً.

## قواعد التحرير

- **`system/` و `application/libraries/` طرفٌ ثالث** — لا تُعدَّل. الترقيع فيها
  يضيع مع أوّل تحديث.
- **النسخ الاحتياطية جامدة:** ملفّات `.orig` و `.pre-*` و `.bak-*` (مثل
  `Home.php.pre-404`, `Taqdar_repo_model.php.pre-dual`) ليست ملفّات فعّالة.
  لا تُحرَّر ولا تُقرأ كمصدر للحقيقة، و `.htaccess` يمنع الوصول إليها.
- **الأسرار لا تُرفع.** المستودع عامّ. `application/config/database.php` و
  `taqdar_secret.php` مستثنيان، ولكلٍّ قالب `.example`. أيّ سرٍّ جديد يتبع
  النمط نفسه أو يُخزَّن في `settings`.
- **الواجهة عربيّة RTL.** القوالب `dir="rtl"` وخطّ Cairo. أيّ نصّ ظاهر
  يُكتب عربيًّا، ولا يُفترض ترتيبٌ بصريّ من اليسار.

## التطوير والنشر

التشغيل المحلّي كاملًا في [SETUP.md](SETUP.md) — الخلاصة: مضيف Apache
افتراضيّ على المنفذ 8081 يشير إلى جذر المستودع، و `taqd_lms` مستوردة محلّيًا.

**لا CI/CD.** النشر أمرٌ واحد على الخادم:

```bash
ssh taqdaredu && cd ~/public_html && bash deploy.sh
```

[deploy.sh](deploy.sh) يسحب `origin/main` بـ `git reset --hard`، ينسخ قاعدة
البيانات احتياطيًّا، ينظّف كاش CodeIgniter و LiteSpeed، ثمّ يفحص الموقع فعليًّا
ويفشل إن لم يرجع 200. `--dry-run` يعرض ما سيتغيّر، و `git reset --hard <sha>`
يرجع. جذر الموقع **هو** نسخة العمل، فـ `.htaccess` يحجب `.git` و `server/`
و `*.md|sh|example` — الملفّ الحقيقيّ يُخدَم كما هو على هذا الخادم
(`/composer.json` يرجع 200)، فبلا الحجب تُسرَّب الشجرة كاملة.

لا اختبارات آليّة؛ التحقّق بالتصفّح المحلّي ثمّ على `https://taqdaredu.com`.

المهامّ الدورية تنادي `Taqdar_cron` و `Taqdar_cron_events` عبر مُقلِع يعيش
**فوق** جذر الويب. نسخه المرجعيّة محفوظة في [server/](server/) مع شرح
موضعها وكيفيّة تطبيقها.
