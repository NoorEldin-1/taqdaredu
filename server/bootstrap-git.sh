#!/bin/bash
# ============================================================
#  تهيئة المستودع على الخادم — تُشغَّل مرّةً واحدةً فقط
#  ------------------------------------------------------------
#  ~/public_html نُشِر بالرفع اليدويّ لا بـ git، فلا مستودع فيه.
#  هذا السكربت يحوّله إلى نسخة عاملة من origin/main دون أن يمسّ
#  ملفًّا واحدًا من محتوى الإنتاج، ليعمل `deploy.sh` بعده.
#
#  الاستعمال:
#      ssh taqda9296@88.222.221.162
#      cd ~/public_html
#      bash server/bootstrap-git.sh          # الفحص أوّلًا
#      bash server/bootstrap-git.sh --apply  # بعد مراجعة الفرق
#
#  الطور الأوّل (الافتراضي) يفحص ويعرض ولا يغيّر شيئًا في الملفّات.
#  الطور الثاني يطابق الملفّات المتتبَّعة مع المستودع.
#
#  البيضة والدجاجة: هذا الملفّ يصل إلى الخادم بالنسخ اليدويّ
#  (scp) أوّل مرّة — فهو من يجلب المستودع الذي يحويه.
# ============================================================

set -euo pipefail

REPO=https://github.com/NoorEldin-1/taqdaredu.git
APPLY=0
[ "${1:-}" = "--apply" ] && APPLY=1

# أوّل مرّة يُنسَخ هذا الملفّ إلى جذر الموقع مباشرةً (لا server/ بعد،
# فالمستودع الذي يحويه لم يصل)؛ وبعد التهيئة يعيش في server/. فيُبحَث
# عن الجذر بعلامته لا بموضع الملفّ.
SELF_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT=""
for cand in "$SELF_DIR" "$SELF_DIR/.." "$PWD"; do
  if [ -f "$cand/index.php" ] && [ -d "$cand/application" ]; then
    ROOT="$(cd "$cand" && pwd)"; break
  fi
done
[ -n "$ROOT" ] || {
  echo "❌ تعذّر العثور على جذر الموقع (index.php + application/)."
  echo "   شغّل السكربت من داخل ~/public_html."
  exit 1
}
cd "$ROOT"
echo "📁 المشروع: $(pwd)"

# ── 1  إنشاء المستودع وتحصينه قبل أن يوجد فيه شيء ───────────
#   ترتيب مقصود: .git/.htaccess يُكتب فور إنشاء المجلّد وقبل الجلب،
#   فلا توجد لحظةٌ واحدة يكون فيها المستودع موجودًا ومكشوفًا.
#   وهو ضروري: /composer.json يُخدَم بـ200 على هذا الخادم، أي أنّ
#   الملفّات الحقيقية تُقدَّم كما هي — و/.git/config منها.
if [ ! -d .git ]; then
  echo "🌱 [1] إنشاء مستودع فارغ..."
  git init -q -b main
else
  echo "🌱 [1] المستودع موجود — يُستكمل."
fi

cat > .git/.htaccess <<'DENY'
# حصانةٌ ثانية إلى جانب قواعد .htaccess في الجذر. لو انكسرت تلك،
# يبقى هذا المجلّد محجوبًا — وانكشافه تسريبٌ للشجرة كاملة.
Require all denied
DENY
echo "   .git/.htaccess: Require all denied"

# ── 2  الأصل البعيد ─────────────────────────────────────────
if git remote get-url origin >/dev/null 2>&1; then
  git remote set-url origin "$REPO"
else
  git remote add origin "$REPO"
fi
echo "🔗 [2] origin = $REPO"

# ── 3  الجلب ────────────────────────────────────────────────
echo "⤵️  [3] جلب main..."
git fetch --quiet origin main

# ── 4  مطابقة الفهرس دون لمس الملفّات ───────────────────────
#   `reset --mixed` يضبط HEAD والفهرس على origin/main ويترك شجرة
#   العمل كما هي. فيصير `git status` فرقًا حقيقيًّا بين ما على
#   الخادم وما في المستودع — وهو ما نريد مراجعته قبل أيّ كتابة.
echo "📇 [4] مطابقة الفهرس مع origin/main (الملفّات كما هي)..."
git reset --mixed --quiet origin/main

echo ""
echo "════════ الفرق بين الخادم و origin/main ════════"
echo ""
echo "— ملفّات متتبَّعة مختلفة على الخادم (ستُستبدَل):"
git --no-pager diff --stat HEAD | sed 's/^/    /' || true
echo ""
echo "— ملفّات في المستودع غير موجودة على الخادم (ستُضاف):"
git --no-pager diff --name-status --diff-filter=D HEAD | sed 's/^D\t/    + /' || true
echo ""
echo "— ملفّات على الخادم خارج المستودع (لن تُمَسّ):"
git status --porcelain --untracked-files=normal | grep '^??' | head -20 | sed 's/^?? /    /' || true
echo "═══════════════════════════════════════════════"
echo ""

if [ "$APPLY" -eq 0 ]; then
  cat <<'NEXT'
🧪 طور الفحص — لم يتغيّر أيّ ملفّ.

راجع القائمة أعلاه. المتوقَّع أن يكون الاختلاف محصورًا في .htaccess
(استثناءات المضيف المحلّي + حجب .git) وفي الملفّات المضافة: deploy.sh
و CLAUDE.md و SETUP.md و server/ والقوالب.

فإن كان كذلك، طبّق:

    bash server/bootstrap-git.sh --apply
NEXT
  exit 0
fi

# ── 5  التطبيق ──────────────────────────────────────────────
#   الأسرار و uploads/ مُتجاهَلة في .gitignore فلا يمسّها reset،
#   وغير المتتبَّع لا يُحذَف. ومع ذلك تُؤخَذ نسخةٌ من .htaccess: هو
#   الملفّ الوحيد المتتبَّع الذي عُدِّل، وكسرُه يُسقط الموقع كلّه.
echo "💾 [5] نسخة من .htaccess قبل الاستبدال..."
cp -p .htaccess ".htaccess.pre-git-$(date +%Y%m%d-%H%M%S)"

echo "🔄 [6] مطابقة الملفّات المتتبَّعة..."
git reset --hard origin/main

echo ""
echo "✅ التهيئة تمّت — $(git rev-parse --short HEAD)"
echo ""
echo "تحقّق الآن من أنّ المستودع محجوب، ثمّ استعمل deploy.sh:"
echo "    curl -s -o /dev/null -w '%{http_code}\\n' https://taqdaredu.com/.git/config   # 403"
echo "    bash deploy.sh"
