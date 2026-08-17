# CLAUDE.md

إرشادات للعمل على هذا المستودع.

## ما هذا المشروع

منصة **تقدر** التعليمية (`taqdaredu.com`) — تطبيق **CodeIgniter 3** على PHP 8.2
و MariaDB، مبني على قاعدة **Academy LMS** التجارية (Creativeitem) وموسع
بطبقة `Taqdar_*` هي جوهر المنتج. الواجهة مصيرة من الخادم بقوالب PHP —
لا SPA ولا خطوة بناء ولا npm.

هذا المستودع مرآة لجذر الموقع المنشور: `/home/taqdaredu.com/public_html`.

## البنية

```
index.php                 نقطة الدخول — تعرف ENVIRONMENT من $_SERVER['CI_ENV']
.htaccess                 تحويلات، ترويسات أمان، والبيئة المحلية
application/
  controllers/            متحكمات
  models/                 نماذج
  views/                  frontend · backend · components · lessons · payment-global
  helpers/                دوال مساعدة (تحمل تلقائيا — انظر autoload.php)
  config/                 routes.php · config.php · database.php* · taqdar_secret.php*
  libraries/              مكتبات خارجية: Stripe · xendit · razorpay · openpayu · phpword · phpqrcode
system/                   نواة CodeIgniter 3 — طرف ثالث، لا تعدل
assets/                   frontend · backend · global · taqdar (سمة تقدر) · playing-page
uploads/                  محتوى المستخدمين — خارج git
languages/                ملفات الترجمة
```

`*` مستثنى من git — انظر [SETUP.md](SETUP.md).

## طبقتان لا واحدة

الفهم المفتاح: `Academy LMS` الأصلي و `Taqdar` يتعايشان في الشجرة نفسها.

| الأصل (Academy LMS) | طبقة تقدر |
|---|---|
| `Home.php` · `Admin.php` · `Api.php` | `Taqdar.php` · `Taqdar_admin.php` · `Taqdar_gate.php` · `Taqdar_pay.php` · `Taqdar_cron_events.php` |
| `Crud_model.php` (226 ك.ب) · `User_model.php` | `Taqdar_repo_model.php` · `Taqdar_wallet_model.php` · `Taqdar_billing_model.php` · `Taqdar_tap_model.php` · `Taqdar_teacher_model.php` · `Taqdar_parent_model.php` · `Taqdar_marking_model.php` · `Taqdar_content_model.php` |
| `views/frontend`, `views/backend` | `views/frontend/tq_*` و `assets/taqdar/` |

الميزات الجديدة تكتب في طبقة `Taqdar_*`. تعديل `Crud_model.php` أو
`Admin.php` يمس شيفرة مشتركة مع مسارات LMS الأصلية، فتوسع بحذر وبفهم
من يناديها.

**لا إضافات مثبتة.** `application/controllers/addons/` مجلد فارغ وجدول
`addons` بلا صف، فكل `addon_status(...)` كاذبة أبدا. أي كتلة مشروطة بها
شيفرة ميتة، ومسار يشير إلى `addons/…` رابط مكسور.

## لوحة الإدارة

اللوحة **مصدر الحقيقة** لكل ما ينشر. بنيتها في
[navigation.php](application/views/backend/admin/navigation.php)، ثماني
مجموعات تجيب سؤال العمل لا سؤال من كتب الشيفرة:

المنهج · الإتقان والتقييم · الأشخاص · التعليم المباشر · المالية ·
المحتوى والموقع · التواصل · النظام.

- الشاشة الأولى `taqdar_admin/overview` — و`admin/dashboard` محولة إليها.
- **الوحدات الموصوفة**: `Taqdar_admin_model::spec()` يصف الجدول والحقول،
  و`tqa_list.php` + `tqa_form.php` تعرضانه. وحدة جديدة = مفتاح في `spec()`
  وبند في `navigation.php`، بلا شاشة تكتب.
  أنواع الحقول: `text` · `textarea` · `lines` · `number` · `seconds` ·
  `money` · `bool` · `datetime` · `enum` · `ref` (رقم من `options()`) ·
  `pick` (**مفتاح نصي** من `options()`) · `multiref` (قائمة معرفات بفواصل
  في عمود نصي) · `refswitch` (عمود واحد يفسر حسب حقل آخر). ومفاتيح تصف
  الشاشة لا العمود: `section` يفصل مجموعة، و`show_when` يخفي حقلا لا
  يعنيه اختيار آخر، و`unique` يفحص الفرادة قبل أن ترميها القاعدة،
  و`form_extra`/`form_js` يضمان لوحة تخص الوحدة، و`status_fn` يضيف عمود
  حال محسوبا في القائمة.
- **نصوص الموقع العام** تحرر من `taqdar_admin/content`. القالب يكتب
  `tq_text('<الصفحة>', '<المفتاح>', 'النص الافتراضي')`، والمفتاح نفسه يسجل في
  `Taqdar_content_model::registry()`. الافتراضي يبقى في القالب: قاعدة فارغة
  تعني أن الصفحة تعرض ما كانت تعرضه حرفا بحرف.
- **التصميم**: [assets/taqdar/css/admin.css](assets/taqdar/css/admin.css)
  يبني هيكل `tqa-*` من `tokens.css` نفسها التي تبني بها البوابات، **ويعيد
  تعريف أوليات Bootstrap 4** (`card` · `btn` · `table` · `form-control` ·
  `nav-tabs` · `modal`) من التوكنات ذاتها — فالشاشة الموروثة تخرج بمظهر
  الشاشة الجديدة بلا أن تلمس. ولا تحمل `base.css` هنا: إعادة ضبطها للعناصر
  العامة تكسر Bootstrap.

## التوجيه

`application/config/routes.php` هو المرجع، وترتيبه مقصود:

1. **مسارات الكتابة قبل مسارات العرض.** `(:any)` في CI3 تعني `[^/]+` أي
   مقطعا واحدا؛ فبلا قاعدة صريحة يسقط `teacher/upload/save` إلى
   `Taqdar::teacher('upload','save')` — تعرض الشاشة ردا على طلب حفظ،
   بلا حفظ ولا خطأ. القواعد الصريحة هي ما يمنع هذا الصمت.
2. بوابات الأدوار: `/student/*` · `/teacher/*` · `/parent/*` توجه كلها
   إلى `Taqdar.php`. البادئة القديمة `taqdar/*` محولة إليها بـ301 من
   `.htaccess`، والمرادفات في `routes.php` احتياط لو تغير التحويل.
3. صفحات عامة نظيفة: `/catalog` · `/plans` · `/about` … إلخ.

## الكتالوج والباقات — صفحتان لا واحدة

سؤالان لا سؤال واحد: **ماذا تقدم المنصة؟** و**بكم؟** وكانت الصفحة الواحدة
تخلطهما، والكتب والمسابقات لها أبوابها المنفصلة بمرشحات لا تشبه بعضها.

| | `/catalog` — المواد والبرامج | `/plans` — الباقات |
|---|---|---|
| السؤال | ماذا يعرض؟ | بكم؟ |
| المصدر | [Taqdar_catalog_model](application/models/Taqdar_catalog_model.php) | `Taqdar_billing_model::plans()` |
| المحتوى | باقة · برنامج · كتاب · مسابقة | الباقات وحدها في كاروسل |
| المفردة | `/plan/<code>` · `/path/<slug>` · `/book/<slug>` · `/competition/<slug>` | — |

- **الحال في الرابط وحده.** المرشحات والبحث ورقم الصفحة معاملات `GET`،
  و`site.js` يكتبها بـ`pushState` ثم يجلب `catalog/results` (رد JSON فيه
  الشبكة واللوحة وسطر العد) — فلا حال ثانية في المتصفح تفترق عما يفهمه
  الخادم، ولا يقع «الصفحة الثانية تنسى البحث».
- **الترشيح في الخادم لا في المتصفح.** نسختان من قواعد الترشيح تفترقان عند
  أول تعديل. وعدادات المرشحات لا تحسب في المتصفح أصلا: نزل منها اثنا عشر
  عنصرا من إحدى وثلاثين.
- **يعمل بلا جافاسكربت.** كل خيار `<a>` وكل بحث نموذج `GET`.
- **الطالب يفتح على مرحلته.** طالب مسجل له صف يفتح `/catalog` عاريا فيحول
  بـ302 إلى `‎?cat=<مرحلته>‎` — و`Taqdar_catalog_model::with_scope()` هي
  التي تقرر، و`stages_of_grade()` تشتق المرحلة من اسم الصف (المرحلة) ومن
  المعروض (المسارات المتخصصة: قدرات · مهارات رقمية) مجموعين، فلا جدول
  ثالث يصف الصفوف بالمراحل ولا مسار متخصص ببرنامج واحد يحجب المرحلة كلها.
  وثلاثة قيود: **المرحلة لا الصف** — الكتاب والمسابقة بلا `grade_id`،
  فترشيح بالصف يمسح المحتوى المجاني كله؛ **ولا يحقن إن اختار الزائر شيئا**
  (`tqs_cat_bare`)؛ **ولا إن كانت المرحلة بلا عنصر واحد**. والتحويل لا
  العرض الصامت: `site.js` يبني كل رابط تال من `location.search` وحده، فرابط
  عار فوق نتيجة مرشحة يجعل أول بحث حي يوسعها بلا أن يطلب أحد.
  و**المخرج `scope=all`**: `tqs_cat_query` يكتبه في كل رابط يعود عاريا
  (نزع الرقاقة · «مسح الكل» · «اعرض كل المراحل»)، وبلاه يعيد الحقن نفسه
  فيضغط الطالب «مسح الكل» ولا يمسح شيء.
- الوسم كله من [taqdar_catalog_helper.php](application/helpers/taqdar_catalog_helper.php)
  — الصفحة الكاملة والجزء المحدث يطبعان من الدالة نفسها.
- خيارات المرشحات تشتق من **المعروض** لا من جداول التصنيف: في `paths` مادة
  برقم لا صف له في `subjects`، فقائمة مبنية من الجدول تسقطها.

**محتوى الباقة مستنتج لا مسرود.** لا حقل يربط درسا بباقة، ولا ينبغي — ولو
وجد لصار كل درس جديد يحتاج مرورا على كل باقة، ولنسي. والسلسلة:

```
plans.scope_ids ← صفوف  →  paths (grade_id, status='published')
                        →  paths.course_id  →  section · lesson
```

- **الباقة المعروضة هي `scope = 'grade'` وحدها.** `plans.php` و`tqs_bundles()`
  و`Taqdar_catalog_model::plans()` ثلاثتها ترشح بها. وباقة بنطاق آخر تشترى
  برابطها `/checkout/<code>` أو تمنح من اللوحة، ولا تظهر في صفحة عامة —
  و`Taqdar_admin_model::plan_visibility()` تقول ذلك في عمود «الظهور» بالقائمة
  وفي شاشة التعديل، فلا تحفظ باقة يظنها صاحبها منشورة وهي ليست كذلك.
- **`stage` مسمى قسم** (`category.slug`) لا نص حر: به يبوب `/plans` وبه تدخل
  الباقة مرشح الكتالوج. وباقة صفوف بلا مرحلة ترفض عند الحفظ.
- **النطاق ينسخ بنودا وقت التفعيل** — `subscribe()` ثم `activate()` يكتبان
  `subscription_items` صفا لكل صف، و`sync_enrolments()` يجسدها صفوف `enrol`.
  فتعديل الباقة غدا لا يوسع ما دفع ولا يضيقه.

و`/user/*` — لوحة المحاضر الموروثة — صارت تحويلا بـ301 إلى ما يقابلها في
بوابة المعلم ([User.php](application/controllers/User.php)). لا تعاد: كل
ما فيها له نظير في `/teacher`، ولوحتان للمعلم تعنيان مكانين يرفع فيهما
وأحدهما لا يعرف المسارات ولا الأهداف.

عند إضافة صفحة: قاعدة في `routes.php` **و** دالة في المتحكم **و** قالب في
`views/`. ولو كانت كتابة، ضع قاعدتها قبل قواعد العرض.

## الأدوار والحراس

أربعة أدوار: **طالب · معلم · ولي أمر · إدارة**. داخل `Taqdar.php`:

- `require_login()` و `require_role($role)` — حراس العرض.
- `write_guard($role)` — حارس الكتابة؛ مسارات الحفظ POST فقط وترفض GET
  بـ `show_404()`.
- `teacher_owns_course()` · `teacher_owns_lesson()` · `parent_owns_child()`
  — تحقق الملكية. أي مسار كتابة جديد يمر بالمناسب منها، وإلا صار
  تعديل صف لا يملكه المستخدم مجرد تخمين معرف.

## الدفع

طريقان لا أكثر، وكلاهما ينتهي إلى `Taqdar_billing_model`:

| | البطاقة — تاب | التحويل البنكي |
|---|---|---|
| الإعداد | `taqdar_admin/tap` | `taqdar_admin/bank` |
| الإعدادات | `settings` بالبادئة `tq_tap_` | `settings` بالبادئة `tq_bank_` |
| التفعيل | `Taqdar_tap_model::settle()` تلقائيا | `activate_manually()` بيد المسؤول |

و`admin/payment_settings` الموروثة تعرض ست عشرة بوابة Academy كلها
`status = 1` — **ولا واحدة منها تمس اشتراكات تقدر**: محرك الفوترة لا يقرأ
`payment_gateways` في سطر واحد. النافع فيها «عملة النظام» وحدها، وهي عملة
الدفع في تاب.

**الترتيب في كل شراء:** اشتراك معلق + فاتورة أولا
(`Taqdar_billing_model::subscribe()`)، ثم تدفع الفاتورة. فالفاتورة هي
المرساة — بها يدفع الطالب باقة أو مسارا أو فاتورة قديمة اختار لها التحويل
ثم عدل، بلا فرع ثان. والعكس — دفعة قبل صف — يعني من دفع ثم سقط الاتصال
دفع بلا شيء عندنا يقابله.

**ثلاث قواعد في [Taqdar_tap_model.php](application/models/Taqdar_tap_model.php)
لا تخرق:**

1. **المفاتيح في `settings` لا في الشيفرة.** المستودع عام والنشر
   `git reset --hard`.
2. **لا يصدق طلب وارد.** عودة الطالب (`payment/tap/return`) والويبهوك
   (`payment/tap/webhook`) لا يحملان إلا معرف الدفعة، وهو **مفتاح جلب**:
   القرار على `GET /charges/{id}` بالمفتاح السري وحده. فمن اخترع `tap_id`
   أو صنع جسم ويبهوك لم يفعل شيئا. والتوقيع (`hashstring`) يفحص ويسجل
   ولا يقرر.
3. **المبلغ يقابل الفاتورة.** لكل محاولة صف في `payment_attempts` بقيمة
   الفاتورة بالهللات وقت البدء، وما ترده تاب يقابله — وإلا فلا تفعيل
   وحالها `mismatch`.

والبادئة `payment/` في المسارين مقصودة: `csrf_exclude_uris` يستثني
`payment/.*` وحدها، والويبهوك يأتي بلا كعكة ولا رمز.

**بلا مفاتيح لا شيء يتغير:** `ready()` كاذبة، فلا يعرض للطالب خيار البطاقة
ولا زر «ادفع الآن»، ويبقى التحويل البنكي وحده كما كان.

ثلاثة أبواب تسد ثلاث فجوات: الويبهوك لمن أغلق المتصفح بعد الدفع،
و`taqdar_cron reconcile` (كل ربع ساعة) لمن لم يصله ويبهوك، وزر «اسأل تاب»
في شاشة اللوحة لمن يقول «دفعت ولم يفتح». والثلاثة تنادي `settle()` نفسها،
وهي مأمونة التكرار.

## قاعدة البيانات

`taqd_lms` — **77 جدولا**. لا ORM ولا هجرات؛ استعلامات Query Builder
مباشرة في النماذج.

- **الجوهر:** `users` · `role` · `course` · `lesson` · `section` · `category` · `subjects` · `grades`
- **تقدر:** `paths` · `plans` · `subscriptions` · `invoices` · `payment_attempts` · `milestones` · `objectives` · `skill_state` · `wallets` · `wallet_entries` · `parent_links` · `review_queue` · `assessments` · `attempts` · `site_content`
- **الإعدادات:** `settings` (102 صفا) · `frontend_settings` · `payment_gateways` · `seo_fields` — **مفاتيح بوابات الدفع و SMTP تعيش هنا، لا في الشيفرة.**
- **triggerان:** `trg_parent_links_consent_*` على `parent_links`.

**أعمدة تخطئ الظن فيها** (كلها كتبت مرة على أسماء مفترضة فرجعت جداول فارغة
بلا خطأ): `attempts` صف لكل **محاولة تقييم** لا لكل سؤال — مفتاحه
`student_id` وفيه `passed` و`submitted_at`، والصواب والخطأ لكل سؤال في
`answers` (`attempt_id` + `question_id` + `is_correct`). و`skill_state.level`
مدرج **0..100** لا كسرا عشريا، ومفتاح صاحبه `student_id` لا `user_id`.

بعض الجداول تنشأ وقت التشغيل لا بهجرة: `site_content` من
`Taqdar_content_model::ensure_schema()`، و`payment_attempts` من
`Taqdar_tap_model::ensure_schema()`، و`tutoring_sessions` من
`Taqdar_sessions_model`، و`wallet_entries` من `install_schema()`. فأي
استعلام إداري عليها يلف بـ`safe_scalar`/`safe_rows` — جدول لم يستعمل بعد
يرمي استثناء يبيض الشاشة، ورقم ناقص أهون.

**CSRF مفعل** (`csrf_protection = TRUE`) والمستودع فيه ٢٣٢ نموذج مكتوب
بيد. الحقل يحقن عالميا من [includes_top.php](application/views/backend/includes_top.php)
عند الإرسال؛ ومع ذلك يكتب `tq_csrf()` صراحة في كل نموذج جديد — نموذج يعتمد
على JS ليحفظ يسقط صامتا متى تعثر ملف.

جدول `permissions` **فارغ**، و`has_permission()` ترجع `true` لمن لا صف له:
كل مسؤول يرى كل شيء حتى يضبط له صف من `admin/admins`.

جدول `language` (1872 صفا) يحمل الترجمات إلى جانب `languages/`.

## الأصول

`assets/taqdar/` هي سمة تقدر (`brand` · `css` · `fonts` · `js` · `site`) وهي
المقصودة عند تعديل مظهر واجهة المنصة. `assets/frontend` و `assets/backend`
أصول قالب Academy LMS الأصلي. لا خطوة بناء: تحرر ملفات CSS و JS مباشرة.

`tokens.css` **مصدر الهوية الوحيد** — يقرأ منه محركان: `base.css` +
`layout.css` + `components.css` للموقع والبوابات (أصناف `tq-*`)، و
`admin.css` للوحة (أصناف `tqa-*` + طبقة تصحح Bootstrap). أي لون أو مسافة
أو نصف قطر يكتب في `tokens.css` وحده؛ قيمة مباشرة في ملف آخر تخرج عن
الهوية بلا أن يظهر ذلك إلا بالمقارنة البصرية.

## قواعد التحرير

- **`system/` و `application/libraries/` طرف ثالث** — لا تعدل. الترقيع فيها
  يضيع مع أول تحديث.
- **النسخ الاحتياطية جامدة:** ملفات `.orig` و `.pre-*` و `.bak-*` (مثل
  `Home.php.pre-404`, `Taqdar_repo_model.php.pre-dual`) ليست ملفات فعالة.
  لا تحرر ولا تقرأ كمصدر للحقيقة، و `.htaccess` يمنع الوصول إليها.
- **الأسرار لا ترفع.** المستودع عام. `application/config/database.php` و
  `taqdar_secret.php` مستثنيان، ولكل قالب `.example`. أي سر جديد يتبع
  النمط نفسه أو يخزن في `settings`.
- **الواجهة عربية RTL.** القوالب `dir="rtl"` وخط Cairo. أي نص ظاهر
  يكتب عربيا، ولا يفترض ترتيب بصري من اليسار.
- **بلا تشكيل.** لا فتحة ولا ضمة ولا كسرة ولا شدة ولا تنوين
  (U+064B–U+0652) في أي نص — لا في القوالب ولا في سلاسل الشيفرة ولا في
  الجداول. النص المشكل يشتت القارئ ويثقل السطر، وقد جرد النظام كله منه
  مرة واحدة. التطويل U+0640 ليس تشكيلا فلم يمس.
  النص الجديد يكتب مجردا ابتداء. ولتنظيف ما يدخل من قاعدة البيانات:
  `php scripts/strip_tashkeel_db.php` للعرض، و`--apply` للتنفيذ — وهو
  مأمون التكرار ويقرأ المخطط وقت التشغيل فيلتقط الجداول الجديدة وحدها.
  المستثنى عمدا: `audit_log` (سجل يحفظ القيم كما كانت، وإعادة كتابته
  تزوير)، و`pdf.worker.min.js` (جداول تفكيك الأشكال العربية — حذفها
  يخرب عرض العربي في PDF).

## التطوير والنشر

التشغيل المحلي كاملا في [SETUP.md](SETUP.md) — الخلاصة: مضيف Apache
افتراضي على المنفذ 8081 يشير إلى جذر المستودع، و `taqd_lms` مستوردة محليا.

**لا CI/CD.** النشر أمر واحد على الخادم:

```bash
ssh taqdaredu && cd ~/public_html && bash deploy.sh
```

[deploy.sh](deploy.sh) يسحب `origin/main` بـ `git reset --hard`، ينسخ قاعدة
البيانات احتياطيا، ينظف كاش CodeIgniter و LiteSpeed، ثم يفحص الموقع فعليا
ويفشل إن لم يرجع 200. `--dry-run` يعرض ما سيتغير، و `git reset --hard <sha>`
يرجع. جذر الموقع **هو** نسخة العمل، فـ `.htaccess` يحجب `.git` و `server/`
و `*.md|sh|example` — الملف الحقيقي يخدم كما هو على هذا الخادم
(`/composer.json` يرجع 200)، فبلا الحجب تسرب الشجرة كاملة.

لا اختبارات آلية؛ التحقق بالتصفح المحلي ثم على `https://taqdaredu.com`.

المهام الدورية تنادي `Taqdar_cron` و `Taqdar_cron_events` عبر مقلع يعيش
**فوق** جذر الويب. نسخه المرجعية محفوظة في [server/](server/) مع شرح
موضعها وكيفية تطبيقها.
