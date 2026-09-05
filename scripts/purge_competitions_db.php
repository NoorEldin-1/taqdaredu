<?php
/**
 * TQ-COMP-GONE — يرفع اثر المسابقات من قاعدة البيانات.
 *
 * الشيفرة حذفت من المستودع: لا صفحة ولا مسار ولا وحدة في اللوحة ولا نوع
 * في الكتالوج. ويبقى في الجداول ما **يعد** بالميزة وهي غير موجودة، وهو
 * اسوا من بقاء شيفرة ميتة: الشيفرة لا يقرؤها زائر، وهذا يقرؤه كل من فتح
 * صفحة الباقات او الشروط او الاسئلة الشائعة.
 *
 * وهو يعالج اربعة مواضع لا واحدا:
 *
 *   ١ `plans.features` — مزية «مسابقات نافس» تطبع في بطاقة الباقة على
 *     الرئيسية و`/plans` و`/catalog` وصفحة الباقة وجدول المقارنة. ومن
 *     يشتري باقة على وعد مزية غير موجودة له حق يطالب به.
 *   ٢ `plans.note` — سطر البطاقة نفسه.
 *   ٣ الشروط في `frontend_settings` — بند «ثامنا: المسابقات» **مع اعادة
 *     ترقيم ما بعده**: وثيقة تقفز من سابعا الى تاسعا تقرا ناقصة، وهي
 *     وثيقة يحتج بها.
 *   ٤ الاسئلة الشائعة — سؤال «ما مسابقات تقدر؟».
 *
 * **ولا يمس `competitions` و`competition_entries`.** الجدولان بيانات لا
 * وعد: لا شيء يقرؤهما بعد اليوم فلا يظهران لاحد، وحذفهما لا يرجع. ومن
 * ارادهما يسقطهما بيده — والسطر مكتوب في ذيل هذا الملف.
 *
 *     php scripts/purge_competitions_db.php            # عرض ما سيتغير
 *     php scripts/purge_competitions_db.php --apply    # التنفيذ في معاملة
 *
 * وهو مامون التكرار: تشغيله مرتين لا يغير شيئا في الثانية.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("CLI only.\n");
}

/* ---------- جذر الموقع: يعرف بعلامته لا بموضع السكربت ---------- */
$root = __DIR__;
while ($root !== dirname($root)) {
    if (is_file($root . '/index.php') && is_dir($root . '/application')) break;
    $root = dirname($root);
}
$cfg = $root . '/application/config/database.php';
if (!is_file($cfg)) exit("تعذر العثور على application/config/database.php من " . __DIR__ . "\n");

defined('BASEPATH') or define('BASEPATH', $root . '/system/');
defined('ENVIRONMENT') or define('ENVIRONMENT', isset($_SERVER['CI_ENV']) ? $_SERVER['CI_ENV'] : 'production');
require $cfg;
$conf = $db[isset($active_group) ? $active_group : 'default'];

$apply  = in_array('--apply', $argv, true);
$needle = 'مسابق';

$pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $conf['hostname'], $conf['database']),
    $conf['username'], $conf['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);

$report = [];
$note   = function ($where, $what) use (&$report) { $report[] = [$where, $what]; };

$pdo->beginTransaction();

/* ================= ١ · الباقات: المزية والسطر ===================== */

$rows = $pdo->query('SELECT id, code, features, note FROM plans')->fetchAll(PDO::FETCH_ASSOC);
$upd  = $pdo->prepare('UPDATE plans SET features = ?, note = ? WHERE id = ?');

foreach ($rows as $r) {
    $feat_old = (string) $r['features'];
    $note_old = (string) $r['note'];
    $feat_new = $feat_old;
    $note_new = $note_old;

    /* المزايا JSON — والبند يحذف من المصفوفة لا يبدل نصه: مزية تعدل
       تبقى مزية، والمقصود انها لم تعد تقدم. وان لم يفك الترميز تركت كما
       هي: صف مكسور يصلح بيد صاحبه، ولا يكتب فوقه حزرا. */
    if (strpos($feat_old, $needle) !== false) {
        $arr = json_decode($feat_old, true);
        if (is_array($arr)) {
            $keep = array_values(array_filter($arr, function ($f) use ($needle) {
                return !(is_string($f) && strpos($f, $needle) !== false);
            }));
            $feat_new = json_encode($keep, JSON_UNESCAPED_UNICODE);
            $note('plans #' . $r['id'] . ' [' . $r['code'] . '] features',
                  (count($arr) - count($keep)) . ' مزية ترفع، ويبقى ' . count($keep));
        } else {
            $note('plans #' . $r['id'] . ' [' . $r['code'] . '] features',
                  'JSON لا يفك — يترك ليصلح بيد');
        }
    }

    /* السطر نص حر: يقص منه ذكر المسابقات ويبقى ما سواه. وحذف السطر كله
       يترك بطاقة بلا وصف، وهو نقص ظاهر مكان نقص خفي. */
    if (strpos($note_old, $needle) !== false) {
        $t = preg_replace('/\s*(?:و|,|،)?\s*(?:تحديات\s+و)?مسابقات?(?:\s+نافس)?\s*/u', ' ', $note_old);
        $t = trim(preg_replace('/\s{2,}/u', ' ', $t));
        $t = trim($t, " \t:,-—·");
        $t = trim($t, '،');
        $note_new = trim($t);
        $note('plans #' . $r['id'] . ' [' . $r['code'] . '] note',
              $note_old . '  ==>  ' . ($note_new === '' ? '(فارغ)' : $note_new));
    }

    if (($feat_new !== $feat_old || $note_new !== $note_old) && $apply) {
        $upd->execute([$feat_new, $note_new, $r['id']]);
    }
}

/* ========= ٢ · نصوص الموقع الموروثة: الشروط والاسئلة ============== */

$rows = $pdo->query('SELECT id, `key`, value FROM frontend_settings')->fetchAll(PDO::FETCH_ASSOC);
$upd  = $pdo->prepare('UPDATE frontend_settings SET value = ? WHERE id = ?');

/* ترقيم البنود العربي: يزحف واحدا حين يرفع بند من وسط الوثيقة. */
$ordinals = ['أولا','ثانيا','ثالثا','رابعا','خامسا','سادسا','سابعا','ثامنا','تاسعا','عاشرا',
             'حادي عشر','ثاني عشر','ثالث عشر','رابع عشر','خامس عشر'];

foreach ($rows as $r) {
    $old = (string) $r['value'];
    if (strpos($old, $needle) === false) continue;
    $new = $old;

    /* ── الشروط: بند h2 كامل بفقراته حتى h2 التالي ── */
    if (preg_match('/<h2>[^<]*' . $needle . '[^<]*<\/h2>/u', $new)) {
        $new = preg_replace('/<h2>[^<]*' . $needle . '[^<]*<\/h2>.*?(?=<h2>|$)/us', '', $new);

        /* واعادة الترقيم: العناوين تقرا بترتيبها الحالي وتكتب بترتيبها
           الجديد. والكتابة دفعة واحدة لا واحدا واحدا — استبدال «تاسعا»
           بـ«ثامنا» ثم «عاشرا» بـ«تاسعا» يصيب ما كتبه الاستبدال قبله. */
        if (preg_match_all('/<h2>\s*([^:<]+?)\s*:/u', $new, $m, PREG_OFFSET_CAPTURE)) {
            $out = ''; $cursor = 0; $i = 0;
            foreach ($m[1] as $hit) {
                if (!in_array(trim($hit[0]), $ordinals, true)) continue;
                $want = isset($ordinals[$i]) ? $ordinals[$i] : trim($hit[0]);
                $out .= substr($new, $cursor, $hit[1] - $cursor) . $want;
                $cursor = $hit[1] + strlen($hit[0]);
                $i++;
            }
            $new = $out . substr($new, $cursor);
        }
        $note('frontend_settings #' . $r['id'] . ' [' . $r['key'] . ']',
              'بند المسابقات يرفع، وما بعده يعاد ترقيمه');
    }

    /* ── الاسئلة الشائعة: مصفوفة JSON من {question, answer} ── */
    if ($new === $old) {
        $arr = json_decode($old, true);
        if (is_array($arr)) {
            $keep = array_values(array_filter($arr, function ($q) use ($needle) {
                return !(is_array($q) && (
                    (isset($q['question']) && strpos((string) $q['question'], $needle) !== false) ||
                    (isset($q['answer'])   && strpos((string) $q['answer'],   $needle) !== false)));
            }));
            if (count($keep) !== count($arr)) {
                $new = json_encode($keep, JSON_UNESCAPED_UNICODE);
                $note('frontend_settings #' . $r['id'] . ' [' . $r['key'] . ']',
                      (count($arr) - count($keep)) . ' سؤال يرفع، ويبقى ' . count($keep));
            }
        }
    }

    if ($new !== $old) {
        if ($apply) $upd->execute([$new, $r['id']]);
    } else {
        $note('frontend_settings #' . $r['id'] . ' [' . $r['key'] . ']',
              'فيه ذكر لم يعرف شكله — يراجع بيد');
    }
}

if ($apply) $pdo->commit(); else $pdo->rollBack();

/* ========================= التقرير ================================ */

foreach ($report as $line) printf("  %-44s %s\n", $line[0], $line[1]);
printf("\n%d موضع — %s\n", count($report), $apply ? 'نفذ' : 'عرض فقط (اضف --apply)');

/* والجدولان يتركان قصدا. من ارادهما:
     DROP TABLE IF EXISTS competition_entries, competitions;
   وترتيبهما مقصود: البنود قبل ما تشير اليه. */
