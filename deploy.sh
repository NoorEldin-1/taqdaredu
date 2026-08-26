#!/bin/bash
# ============================================================
#  منصّة تقدّر — سكربت النشر اليدويّ
#  ------------------------------------------------------------
#  يُشغَّل على خادم الإنتاج بصلاحية مالك الموقع (taqda9296)،
#  فيسحب آخر كودٍ من GitHub ويطبّق كلّ ما يلزم بعده.
#
#  الاستعمال:
#      ssh taqda9296@88.222.221.162
#      cd ~/public_html
#      bash deploy.sh
#
#  خيارات:
#      bash deploy.sh --no-backup       تخطي نسخ قاعدة البيانات
#      bash deploy.sh --dry-run         عرض ما سيتغير ثم الخروج
#      bash deploy.sh --discard-server  المتابعة رغم شغل غير مرفوع هنا
#                                       (يفقد — لا يستعمل إلا بقصد)
#
#  كلّ خطوة عديمة الأثر عند التكرار — آمنٌ تشغيله في كلّ نشر.
#
#  لا تُنفَّذ هجرات ولا `composer install`: التطبيق CodeIgniter 3،
#  مخطّط قاعدة البيانات يُدار يدويًّا، و composer.json لا يعلن أيّ
#  اعتماد (المكتبات موضوعة في application/libraries داخل الشجرة).
#  فالنشر هنا = مزامنة ملفّات + تنظيف كاش، لا أكثر.
#
#  أوّل مرّة فقط: ~/public_html ليس مستودعًا بعد. شغّل
#      bash server/bootstrap-git.sh
#  ولا يمكن تشغيله من هنا لأنّه هو من يجلب هذا الملفّ أصلًا.
# ============================================================

set -euo pipefail

# ملفوفٌ في دالّة ليُقرأ السكربت كاملًا قبل أن تبدأ خطوةُ `git reset`،
# فلا يُستبدَل الملفّ من تحت المفسّر وهو ينفّذه.
deploy() {
  cd "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

  local DO_BACKUP=1 DRY_RUN=0 DISCARD=0
  for arg in "$@"; do
    case "$arg" in
      --no-backup)      DO_BACKUP=0 ;;
      --dry-run)        DRY_RUN=1 ;;
      --discard-server) DISCARD=1 ;;
      *) echo "خيار غير معروف: $arg"; exit 2 ;;
    esac
  done

  # مفسّر LiteSpeed نفسه الذي يخدم الويب، لا /usr/bin/php: الإصدار
  # والإضافات يجب أن تطابق ما يعمل به الموقع فعلًا.
  local PHP=/usr/local/lsws/lsphp82/bin/php
  [ -x "$PHP" ] || PHP=$(command -v php)

  echo "📁 المشروع: $(pwd)"
  echo "🐘 PHP:     $("$PHP" -r 'echo PHP_VERSION;')"
  echo ""

  # ── 1/7  فحوص ما قبل النشر ─────────────────────────────────
  #   الفشل هنا قبل أن يتغيّر أيّ شيء، لا بعد نصف نشر.
  echo "🔎 [1/7] فحوص ما قبل النشر..."

  if [ ! -d .git ]; then
    echo "❌ ليس مستودع git. شغّل أوّلًا:  bash server/bootstrap-git.sh"
    exit 1
  fi
  if [ ! -f index.php ] || [ ! -d application ]; then
    echo "❌ لا يبدو أنّ هذا جذر الموقع (لا index.php أو application/)."
    exit 1
  fi

  #   الأسرار مستثناةٌ من git، فهي تنجو من `reset --hard`. لكنّ غيابها
  #   يعني موقعًا معطوبًا بعد النشر، والاكتشاف الآن أرخص.
  local missing=0
  for f in application/config/database.php application/config/taqdar_secret.php; do
    if [ ! -f "$f" ]; then
      echo "❌ مفقود: $f  — انسخه من ${f}.example واملأه."
      missing=1
    fi
  done
  [ "$missing" -eq 0 ] || exit 1

  git fetch --quiet origin main
  local LOCAL REMOTE
  LOCAL=$(git rev-parse HEAD 2>/dev/null || echo none)
  REMOTE=$(git rev-parse origin/main)

  if [ "$LOCAL" = "$REMOTE" ]; then
    echo "   المستودع محدَّث بالفعل عند ${REMOTE:0:8}."
  else
    echo "   ${LOCAL:0:8} → ${REMOTE:0:8}"
    git --no-pager log --oneline "$LOCAL..$REMOTE" 2>/dev/null | sed 's/^/     /' || true
  fi

  #   شغل على الخادم لا وجود له على GitHub — و`reset --hard` يمحوه.
  #   صورتان مختلفتان، ولا تغني إحداهما عن الأخرى:
  #
  #     AHEAD  commits عملت هنا ولم ترفع. الشجرة معها **نظيفة**،
  #            فلا يمسكها فحص `git diff` أدناه. وكان السطر الوحيد
  #            الذي يقارن يطبع `LOCAL..REMOTE` — أي ما ينقص الخادم
  #            من origin، والاتجاه المعاكس لا يظهر قط. فمر خادم
  #            عليه اثنا عشر commit غير مرفوعة بلا تحذير واحد
  #            (2026-08-26)، ونجا بالفحص اليدوي لا بهذا السكربت.
  #     DIRTY  تعديل على ملف متتبع بلا commit. لا يجلبه `git fetch`
  #            مهما فعل — لأنه لم يصر كائنا في المستودع بعد.
  #
  #   والملفات غير المتتبعة تنجو من `reset --hard` فلا تعد هنا.
  local AHEAD DIRTY=0
  AHEAD=$(git rev-list --count origin/main..HEAD 2>/dev/null || echo 0)
  git diff --quiet HEAD 2>/dev/null || DIRTY=1

  if [ "$AHEAD" -gt 0 ]; then
    echo ""
    echo "   ⛔ $AHEAD commit على الخادم ليست على origin/main — وستصير غير مبلوغة:"
    git --no-pager log --oneline "origin/main..HEAD" | sed 's/^/     /'
    echo "      الفرع هنا: $(git rev-parse --abbrev-ref HEAD)"
  fi

  if [ "$DIRTY" -eq 1 ]; then
    echo ""
    echo "   ⛔ تعديلات على ملفات متتبعة بلا commit — ستمحى:"
    git --no-pager diff --stat HEAD | sed 's/^/     /'
  fi

  if [ "$DRY_RUN" -eq 1 ]; then
    echo ""
    if [ "$AHEAD" -gt 0 ] || [ "$DIRTY" -eq 1 ]; then
      echo "🧪 --dry-run — لم يتغير شيء. (وللعلم: النشر الفعلي كان سيتوقف)"
    else
      echo "🧪 --dry-run — لم يتغير شيء."
    fi
    exit 0
  fi

  #   الوقوف لا التحذير: تحذير يقرأ بعد أن يمضي السكربت لا يرجع شيئا.
  if [ "$AHEAD" -gt 0 ] || [ "$DIRTY" -eq 1 ]; then
    if [ "$DISCARD" -eq 1 ]; then
      echo ""
      echo "   ⚠️  --discard-server — يتابع، وما سبق يفقد بطلبك."
    else
      echo ""
      echo "   ⛔ توقف النشر. لم يتغير شيء."
      echo ""
      echo "      احفظ ما هنا قبل أي شيء:"
      [ "$AHEAD" -gt 0 ] && echo "        git push origin HEAD:<فرع>      # ثم ادمجه في main"
      [ "$DIRTY" -eq 1 ] && echo "        git diff HEAD > ~/server.patch  # لا يجلبه fetch"
      echo ""
      echo "      ولا تدمج بـ force ولا بترجيح جانب على آخر: الشغلان يجتمعان."
      echo "      وإن كان الفقد مقصودا فعلا:  bash deploy.sh --discard-server"
      exit 3
    fi
  fi

  # ── 2/7  نسخة قاعدة البيانات ───────────────────────────────
  #   النشر لا يمسّ قاعدة البيانات، لكنّ النسخة قبل كلّ تغييرٍ تأمينٌ
  #   رخيص. بيانات الاتصال تُقرأ من database.php نفسه، فلا كلمة مرور
  #   مكتوبة هنا ولا في سطر الأوامر (‎--defaults-extra-file‎).
  if [ "$DO_BACKUP" -eq 1 ]; then
    echo "💾 [2/7] نسخة احتياطية لقاعدة البيانات..."
    local BK=~/backups
    mkdir -p "$BK"

    local CNF; CNF=$(mktemp)
    chmod 600 "$CNF"
    "$PHP" -r '
      define("BASEPATH", 1); define("ENVIRONMENT", "production");
      $db = [];
      require "application/config/database.php";
      $d = $db["default"];
      printf("[client]\nhost=%s\nuser=%s\npassword=%s\n", $d["hostname"], $d["username"], $d["password"]);
    ' > "$CNF"
    local DBNAME; DBNAME=$("$PHP" -r '
      define("BASEPATH", 1); define("ENVIRONMENT", "production");
      $db = [];
      require "application/config/database.php";
      echo $db["default"]["database"];
    ')

    local OUT="$BK/${DBNAME}_$(date +%Y%m%d-%H%M%S).sql.gz"
    if mysqldump --defaults-extra-file="$CNF" --single-transaction --quick \
                 --routines --triggers --default-character-set=utf8mb4 --hex-blob \
                 "$DBNAME" 2>/dev/null | gzip -9 > "$OUT"; then
      echo "   $OUT ($(du -h "$OUT" | cut -f1))"
    else
      echo "   ⚠️  فشل التفريغ — يُتابَع النشر (لا يمسّ القاعدة أصلًا)."
      rm -f "$OUT"
    fi
    rm -f "$CNF"

    #   الإبقاء على آخر ١٠ فقط، وإلّا امتلأ القرص بصمت.
    ls -1t "$BK"/*.sql.gz 2>/dev/null | tail -n +11 | xargs -r rm -f
  else
    echo "⏭️  [2/7] تخطّي النسخ الاحتياطي (--no-backup)."
  fi

  # ── 3/7  سحب الكود ─────────────────────────────────────────
  #   reset --hard يُطابق الملفّات المتتبَّعة مع origin/main. لا يمسّ
  #   المُتجاهَل ولا غير المتتبَّع: database.php و taqdar_secret.php و
  #   uploads/ و application/cache/ كلّها تبقى كما هي.
  echo "⤵️  [3/7] سحب آخر كود من GitHub (main)..."
  git reset --hard origin/main
  echo "   الآن عند: $(git rev-parse --short HEAD) — $(git log -1 --format=%s)"

  # ── 4/7  تنظيف كاش CodeIgniter ─────────────────────────────
  #   كاش الاستعلامات والصفحات قد يحمل مخرجات الكود القديم. الحُرّاس
  #   (index.html) تبقى، وإلّا صار سرد المجلّد مكشوفًا.
  echo "🧹 [4/7] تنظيف كاش CodeIgniter..."
  find application/cache -mindepth 1 ! -name index.html ! -name .htaccess -delete 2>/dev/null || true

  # ── 5/7  تنظيف كاش LiteSpeed ───────────────────────────────
  #   LSCache يخدم HTML مخزَّنًا؛ بلا تفريغه يظلّ الزائر يرى القديم.
  echo "⚡ [5/7] تنظيف كاش LiteSpeed..."
  if [ -d ~/lscache ] && [ -w ~/lscache ]; then
    find ~/lscache -mindepth 1 -delete 2>/dev/null || true
    echo "   تمّ."
  else
    echo "   لا يوجد ~/lscache قابل للكتابة — تُخطَّى."
  fi

  # ── 6/7  المجلّدات القابلة للكتابة ─────────────────────────
  #   يكتب فيها الويب (رفع الملفّات، الكاش، السجلّ). ملفٌّ واحد بمالكٍ
  #   خاطئ يُسقط الرفع في خطأ صامت، فتُثبَّت الملكيّة والصلاحيّات كلّ مرّة.
  echo "🔐 [6/7] ضبط المجلّدات القابلة للكتابة..."
  mkdir -p uploads application/cache application/logs backups
  chmod -R u+rwX,g+rwX uploads application/cache application/logs backups 2>/dev/null || true
  echo "   تمّ."

  # ── 7/7  فحص دخانيّ ────────────────────────────────────────
  #   النشر الذي لا يُتحقَّق منه ليس نشرًا. يُطلَب الموقع فعلًا؛ فشل هنا
  #   يعني حالةً مكسورة تُعالَج فورًا بالرجوع (انظر الأسفل).
  echo "🩺 [7/7] فحص دخانيّ..."
  local ok=1
  # `/catalog` مع `/plans`: الكتالوج الموحد صفحة يقرأ فيها أربعة جداول
  # ونموذج ترشيح كامل، فهو أكثر ما يكسره تعديل — وصفحة الباقات وحدها
  # لا تمر به.
  for path in / /catalog /plans /login; do
    local code
    code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 30 "https://taqdaredu.com${path}" || echo 000)
    printf "   %-10s → %s\n" "$path" "$code"
    [ "$code" = "200" ] || ok=0
  done

  #   قواعد الحجب تفشل صامتةً لو انكسر .htaccess، وانكشاف .git تسريبٌ
  #   للشجرة كاملة — فيُتحقَّق منه صراحةً في كلّ نشر.
  local gitcode
  gitcode=$(curl -s -o /dev/null -w '%{http_code}' --max-time 30 https://taqdaredu.com/.git/config || echo 000)
  printf "   %-10s → %s%s\n" "/.git/config" "$gitcode" \
    "$([ "$gitcode" = "403" ] || [ "$gitcode" = "404" ] && echo " (محجوب ✓)" || echo "  ❌ مكشوف!")"
  { [ "$gitcode" = "403" ] || [ "$gitcode" = "404" ]; } || ok=0

  echo ""
  if [ "$ok" -eq 1 ]; then
    echo "✅ اكتمل النشر — $(git rev-parse --short HEAD)"
  else
    echo "❌ النشر تمّ لكنّ الفحص الدخانيّ فشل. للرجوع فورًا:"
    echo "     git reset --hard $LOCAL"
    echo "   ثمّ راجع application/logs/ و ~/logs/ لسبب العطل."
    exit 1
  fi
}

deploy "$@"

# ============================================================
#  مرجع — تُنفَّذ يدويًّا عند الحاجة فقط، ليست جزءًا من نشرٍ روتينيّ
#
#  • الرجوع إلى إصدارٍ سابق:
#        git log --oneline -10
#        git reset --hard <sha>
#    الأسرار و uploads/ لا تتأثّر (مُتجاهَلة).
#
#  • استعادة قاعدة البيانات من نسخة:
#        gunzip -c ~/backups/taqd_lms_<ختم>.sql.gz | mysql -u taqd_lmsuser -p taqd_lms
#    عميل MariaDB القديم يختنق بسطر sandbox الأوّل — احذفه إن ظهر
#    الخطأ `Unknown command '\-'`.
#
#  • بعد تغيير الأسرار (database.php / taqdar_secret.php): عدّلها على
#    الخادم مباشرةً. هي مُتجاهَلة في git فلن يجلبها أيّ نشر، وتغيير
#    taqdar_secret.php يُبطل كلّ التوكنات القائمة.
#
#  • المهامّ الدورية (crontab) لا يلمسها النشر. نسختها المرجعيّة في
#    server/crontab.taqda9296. لتطبيق تغييرٍ عليها:
#        crontab - < server/crontab.taqda9296
#
#  • تغيير المخطّط: لا هجرات في CodeIgniter 3. طبّق SQL يدويًّا على
#    الخادم بعد نسخةٍ احتياطية، وسجّل ما فعلت في رسالة الكوميت الذي
#    يعتمد عليه.
#
#  • تحديث uploads/ محلّيًا من الإنتاج (لا علاقة له بالنشر):
#        rsync -avz taqda9296@88.222.221.162:public_html/uploads/ ./uploads/
# ============================================================
