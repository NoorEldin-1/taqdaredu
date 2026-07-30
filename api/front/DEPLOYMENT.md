# دليل رفع الموقع على السيرفر - Deployment Guide
# my-communication.uk

## هيكل الملفات على السيرفر (Server File Structure)

```
my-communication.uk/  (server root = public_html)
├── .htaccess              ← ملف جديد (من htaccess-production.txt)
├── index.html             ← React SPA (من front/dist/index.html)
├── index.php              ← CodeIgniter (موجود بالفعل)
├── favicon.ico            ← من front/dist/
├── robots.txt             ← موجود بالفعل
├── _app/                  ← React JS/CSS/images (من front/dist/_app/)
│   ├── index-*.js
│   ├── index-*.css
│   ├── logo-*.png
│   └── ...
├── application/           ← CodeIgniter (موجود بالفعل)
├── system/                ← CodeIgniter (موجود بالفعل)
├── uploads/               ← ملفات المستخدمين (موجود بالفعل)
├── assets/                ← أصول CodeIgniter (موجود بالفعل)
├── vendor/                ← Composer (موجود بالفعل)
└── ...
```

---

## الخطوات (Step by Step)

### الخطوة 1: بناء ملفات الإنتاج (Build)

على جهازك المحلي، شغّل:

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/MyCommcation/front
npm run build
```

سيتم إنشاء مجلد `dist/` يحتوي على:
- `index.html` — صفحة React الرئيسية
- `_app/` — ملفات JS و CSS والصور
- `favicon.ico`

### الخطوة 2: رفع ملفات React على السيرفر

عبر FTP أو File Manager في cPanel:

1. **ارفع `index.html`** من `front/dist/index.html` → إلى **جذر السيرفر** (`public_html/index.html`)

2. **ارفع مجلد `_app/`** بالكامل من `front/dist/_app/` → إلى **جذر السيرفر** (`public_html/_app/`)

3. **ارفع `favicon.ico`** من `front/dist/favicon.ico` → إلى **جذر السيرفر** (استبدل القديم إن وجد)

### الخطوة 3: تحديث ملف .htaccess

1. **انسخ احتياطي** من `.htaccess` الحالي على السيرفر:
   ```
   .htaccess → .htaccess.backup
   ```

2. **ارفع** محتوى ملف `front/htaccess-production.txt` كملف `.htaccess` جديد في **جذر السيرفر** (`public_html/.htaccess`)

### الخطوة 4: تأكد من إعدادات CodeIgniter

في ملف `application/config/config.php` على السيرفر، تأكد أن:

```php
// يجب أن يكون auto-detect (كما هو حالياً)
$config['base_url'] = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on") ? "https" : "http");
$config['base_url'] .= "://".$_SERVER['HTTP_HOST'];
$config['base_url'] .= str_replace(basename($_SERVER['SCRIPT_NAME']),"",$_SERVER['SCRIPT_NAME']);
```

### الخطوة 5: تأكد من إعدادات قاعدة البيانات

في `application/config/database.php` تأكد أن بيانات الاتصال صحيحة للسيرفر الحي.

---

## التحديثات المستقبلية (Future Updates)

عند أي تعديل على الكود:

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/MyCommcation/front
npm run build
```

ثم ارفع فقط:
- `dist/index.html` → `public_html/index.html`
- `dist/_app/` → `public_html/_app/`

**ملاحظة:** لا تحتاج لتعديل `.htaccess` مرة أخرى إلا إذا أضفت API routes جديدة.

---

## ملاحظات مهمة

1. **لا تحذف** ملفات CodeIgniter (`index.php`, `application/`, `system/`, `assets/`, `uploads/`, `vendor/`)
2. **ملفات React** (`index.html`, `_app/`) يمكن استبدالها بأمان في كل تحديث
3. **الصور والملفات المرفوعة** (`/uploads/`) تبقى كما هي — React يقرأها من نفس الدومين
4. **SSL**: تأكد أن `https://my-communication.uk` مفعّل على السيرفر

---

## استكشاف الأخطاء (Troubleshooting)

| المشكلة | الحل |
|---------|------|
| صفحة بيضاء | تأكد من رفع `index.html` و `_app/` في جذر السيرفر |
| 404 عند تحديث الصفحة | تأكد من `.htaccess` الجديد مرفوع بشكل صحيح |
| API لا يعمل | تأكد أن `index.php` (CodeIgniter) موجود في جذر السيرفر |
| الصور لا تظهر | تأكد أن مجلد `uploads/` موجود وله صلاحيات القراءة |
| لوحة التحكم (Admin) لا تعمل | تأكد أن route `/admin` موجود في `.htaccess` |

---

## أمر SCP (للرفع عبر Terminal)

```bash
# رفع عبر SCP (بدّل user و server بالبيانات الصحيحة)
scp front/dist/index.html user@server:/path/to/public_html/index.html
scp -r front/dist/_app/ user@server:/path/to/public_html/_app/
scp front/htaccess-production.txt user@server:/path/to/public_html/.htaccess
```

## أمر rsync (بديل أسرع)

```bash
rsync -avz --delete front/dist/_app/ user@server:/path/to/public_html/_app/
rsync -avz front/dist/index.html user@server:/path/to/public_html/index.html
```
