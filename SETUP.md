# تشغيل المشروع محلّيًا

منصّة **تقدّر** (`taqdaredu.com`) — تطبيق CodeIgniter 3 على PHP 8.2 و MariaDB.
هذا الدليل يفترض XAMPP على ويندوز؛ أي لينكس/ماك يعمل بنفس الخطوات مع تبديل المسارات.

---

## المتطلّبات

| | الإنتاج | المُتحقَّق منه محلّيًا |
|---|---|---|
| PHP | 8.2.29 | 8.2.12 (XAMPP 8.2.12) |
| قاعدة البيانات | MariaDB 10.11.10 | MariaDB 10.4.32 |
| الخادم | LiteSpeed | Apache 2.4.58 |

امتدادات PHP المطلوبة: `mysqli`, `curl`, `gd`, `mbstring`, `zip`, `openssl`, `fileinfo` — كلّها مفعّلة افتراضيًا في XAMPP.

---

## 1. الملفّات السرّية

ملفّان مستثنيان من المستودع لأنّه عامّ. انسخ القالبين واملأهما:

```bash
cp application/config/database.php.example      application/config/database.php
cp application/config/taqdar_secret.php.example  application/config/taqdar_secret.php
```

- **`database.php`** — بيانات الاتصال (انظر الخطوة 2).
- **`taqdar_secret.php`** — سرّ توقيع توكنات الـAPI. للتطوير المحلّي ولّد سرًّا جديدًا:
  ```bash
  php -r "echo bin2hex(random_bytes(32));"
  ```
  استخدم سرّ الإنتاج فقط إن كنت تحتاج توكنات صالحة على الخادمين معًا.

---

## 2. قاعدة البيانات

اسم القاعدة والمستخدم مطابقان للإنتاج عمدًا، فيعمل `database.php` نفسه في الموضعين بلا تعديل.

```sql
CREATE DATABASE taqd_lms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'taqd_lmsuser'@'localhost' IDENTIFIED BY '<كلمة-المرور>';
GRANT ALL PRIVILEGES ON taqd_lms.* TO 'taqd_lmsuser'@'localhost';
FLUSH PRIVILEGES;
```

ثمّ استورد نسخة من الخادم:

```bash
# على الخادم
mysqldump --single-transaction --quick --routines --triggers \
          --default-character-set=utf8mb4 --hex-blob \
          -u taqd_lmsuser -p taqd_lms > taqd_lms.sql

# محلّيًا
mysql -u root --default-character-set=utf8mb4 taqd_lms < taqd_lms.sql
```

> **عميل MariaDB 10.4 وأقدم:** مخرَج `mysqldump` من 10.11 يبدأ بسطر
> `/*M!999999\- enable the sandbox mode */` يرفضه العميل القديم بـ
> `Unknown command '\-'`. احذف السطر الأوّل قبل الاستيراد.

القاعدة السليمة: **75 جدولًا و triggerان** (`trg_parent_links_consent_*`).

---

## 3. الخادم

### Apache — مضيف افتراضي

المشروع يفترض أنّه في جذر المضيف: `.htaccess` يعيد كتابة `^(.*)$` إلى
`index.php` وقواعد التحويل تبدأ بـ `/`. تشغيله داخل مجلّد فرعي من `htdocs`
يكسر التوجيه، فالمضيف الافتراضي ضرورةٌ لا تفضيل.

في `C:\xampp\apache\conf\extra\httpd-vhosts.conf`:

```apache
Listen 8081
<VirtualHost *:8081>
    ServerName localhost
    DocumentRoot "C:/work/projects/taqdaredu"
    <Directory "C:/work/projects/taqdaredu">
        Options Indexes FollowSymLinks Includes ExecCGI
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog  "logs/taqdaredu-error.log"
    CustomLog "logs/taqdaredu-access.log" common
</VirtualHost>
```

`AllowOverride All` شرطٌ لعمل `.htaccess`؛ بدونه لا تُقرأ قواعد التوجيه أصلًا.

أعد تشغيل Apache، ثمّ افتح **http://localhost:8081**.

### ما يتكفّل به `.htaccess` محلّيًا

| السلوك | آليّته |
|---|---|
| لا إجبار على HTTPS | شرطان يستثنيان `localhost` و `127.0.0.1` |
| إظهار الأخطاء | `SetEnvIf Host … CI_ENV=development` |

كلاهما مشروط بالمضيف، فلا أثر لهما في الإنتاج.

### `base_url`

`application/config/config.php` يشتقّ `base_url` من `$_SERVER['HTTP_HOST']`
في كلّ طلب. لا شيء يُضبَط يدويًا، والمشروع يعمل على أيّ مضيف أو منفذ.

---

## 4. الوسائط المرفوعة

`uploads/` مستثنى من git (محتوى مستخدمين لا كود). المستودع يحمل هيكل
المجلّدات و حُرّاسها (`index.html`, `.htaccess`) فقط. لصور حقيقية محلّيًا:

```bash
rsync -avz taqda9296@88.222.221.162:public_html/uploads/ ./uploads/
```

بدونها تعمل الواجهة لكن تظهر الصور مكسورة.

---

## 5. الوصول إلى الخادم والنشر

```bash
ssh taqda9296@88.222.221.162          # جذر الموقع: ~/public_html
```

الوصول بمفتاح عامّ يُضاف من CyberPanel → Websites → taqdaredu.com → SSH Access.
لوحة CyberPanel على `https://88.222.221.162:8090`.

### النشر

ادفع إلى `main`، ثمّ على الخادم:

```bash
cd ~/public_html
bash deploy.sh
```

| خيار | أثره |
|---|---|
| `--dry-run` | يعرض الكوميتات والملفّات التي ستتغيّر ثمّ يخرج |
| `--no-backup` | يتخطّى نسخ قاعدة البيانات |

السكربت ينسخ القاعدة إلى `~/backups` (يُبقي آخر ١٠)، يسحب `origin/main`،
ينظّف كاش CodeIgniter و LiteSpeed، يضبط صلاحيّات المجلّدات القابلة للكتابة،
ثمّ **يفحص الموقع فعليًّا** ويفشل إن لم ترجع `/` و `/plans` و `/login` بـ200
أو إن انكشف `/.git/config`.

الأسرار و `uploads/` مُتجاهَلة في git فلا يمسّها النشر. للرجوع:

```bash
git log --oneline -10
git reset --hard <sha>
```

> **أوّل مرّة فقط** — `~/public_html` نُشِر بالرفع اليدويّ فليس مستودعًا.
> انسخ سكربت التهيئة ثمّ شغّله على طورين:
> ```bash
> scp server/bootstrap-git.sh taqdaredu:public_html/
> ssh taqdaredu 'cd public_html && bash bootstrap-git.sh'          # فحص
> ssh taqdaredu 'cd public_html && bash bootstrap-git.sh --apply'  # تطبيق
> ```

---

## التحقّق السريع

| الفحص | المتوقَّع |
|---|---|
| `curl -I http://localhost:8081/` | `200` و `Content-Type: text/html; charset=UTF-8` |
| `SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='taqd_lms'` | `75` |
| `curl -o /dev/null -w '%{http_code}' http://localhost:8081/plans` | `200` |
| `curl -o /dev/null -w '%{http_code}' http://localhost:8081/student` | `302` (تحويل إلى الدخول) |
| `curl -o /dev/null -w '%{http_code}' http://localhost:8081/courses` | `301` (إلى `/plans`) |

---

## أعطال شائعة

**تحويل لا ينتهي إلى `https://localhost:8081`**
`.htaccess` غير مقروء (`AllowOverride` ليس `All`) أو `mod_rewrite` معطّل.

**صفحة بيضاء بلا رسالة**
`CI_ENV` لم تصل إلى PHP، فبقيت البيئة `production` وابتُلع الخطأ.
تحقّق من `mod_setenvif`، واقرأ `application/logs/` و سجلّ أخطاء Apache.

**`Unable to connect to your database server`**
المستخدم `taqd_lmsuser` غير موجود محلّيًا، أو `database.php` لم يُنسَخ من قالبه.

**نصّ عربيّ مشوّه**
استُورِدت النسخة بترميز غير `utf8mb4`. أعد الاستيراد مع
`--default-character-set=utf8mb4` في طرفَي التصدير والاستيراد.

**`Got error 176 "Read page with wrong checksum" from storage engine Aria`**
عطبٌ في جداول صلاحيات MySQL المحلّية، لا علاقة له بالمشروع:
```sql
REPAIR TABLE mysql.db, mysql.tables_priv;
```
