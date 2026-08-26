<?php
/**
 * معلمو الموقع العام: يحل المعلمون الحقيقيون محل بذرة العرض.
 *
 * صفحة `/teachers` كانت تعرض خمسة أسماء مخترعة (`seed-t1..t5@taqdaredu.local`)
 * بصور مرسومة في سمة الموقع (`teacher-1..5.webp`) — أي واجهة عامة تعد
 * بمعلمين لا حساب لأحدهم ولا صورة له. وهذا المرور يضع محلها ثلاثة عشر
 * حسابا فعليا: بريد وكلمة مرور يدخل بهما صاحبه من `/login`، وصورة مرفوعة
 * فعلا في `uploads/user_image/`، وظهور في دليل `/teachers`.
 *
 * ── لماذا نقل لا حذف ─────────────────────────────────────────────────────
 * حسابات البذرة ليست معلقة في الهواء: تحتها خمسة برامج منشورة في `paths`
 * للصف الرابع الابتدائي، وخمسة كورسات، وفترات إتاحة وحصص ومحادثات. فحذف
 * الصف وحده يترك `paths.teacher_id` يشير إلى لا أحد، ويخلي الكتالوج من
 * محتوى ذلك الصف بلا أن يطلب أحد ذلك. فكل ما تحت المعلم القديم ينقل إلى
 * المعلم الجديد الذي يدرس **المادة نفسها** (`inherits` أدناه)، ثم يحذف
 * الصف. والنتيجة: الأسماء الوهمية تختفي والمنهج يبقى كما هو.
 *
 * ── مأمون التكرار ───────────────────────────────────────────────────────
 * الهوية هي البريد لا الرقم: تشغيله مرتين يحدث الحساب القائم ولا ينشئ
 * ثانيا. وكلمة المرور تولد **مرة واحدة** عند الإنشاء ولا تمس بعدها —
 * فمن غير كلمته لا يفاجأ بها تعود. و`--reset-pass` يولد جديدة صراحة.
 *
 * ── الصور ───────────────────────────────────────────────────────────────
 * المصدر مجلد PNG خارج جذر الويب، اسم كل ملف هو اسم صاحبه. والمرور يقصها
 * مربعة من الأعلى (الوجه في الثلث الأعلى من لقطة 1024x1280، والقص المركزي
 * الذي يفعله `object-fit:cover` يقطع الرأس) ثم يكتب نسختين حيث يقرؤهما
 * `tqs_person_img()`: الأصل في `uploads/user_image/<hash>.jpg` والمصغرة
 * 220px في `optimized/` — وهي التي تحمل في البطاقة.
 *
 * الاستعمال (من جذر الموقع):
 *     php scripts/seed_site_teachers.php                 # عرض الخطة، بلا كتابة
 *     php scripts/seed_site_teachers.php --apply         # التنفيذ
 *     php scripts/seed_site_teachers.php --apply --reset-pass
 *     php scripts/seed_site_teachers.php --photos=/path/to/png-dir
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit("CLI only.\n"); }

/* ---------- جذر الموقع: يعرف بعلامته لا بموضع السكربت ---------- */
$root = __DIR__;
while ($root !== dirname($root)) {
    if (is_file($root . '/index.php') && is_dir($root . '/application')) break;
    $root = dirname($root);
}
$cfg = $root . '/application/config/database.php';
if (!is_file($cfg)) exit("تعذر العثور على application/config/database.php\n");

defined('BASEPATH')    or define('BASEPATH', $root . '/system/');
defined('ENVIRONMENT') or define('ENVIRONMENT', isset($_SERVER['CI_ENV']) ? $_SERVER['CI_ENV'] : 'production');
require $cfg;
$conf = $db[isset($active_group) ? $active_group : 'default'];

$apply  = in_array('--apply', $argv, true);
$repass = in_array('--reset-pass', $argv, true);
$photos = getenv('HOME') . '/tq_teacher_photos';
foreach ($argv as $a) if (strpos($a, '--photos=') === 0) $photos = substr($a, 9);

/* ── الطاقم ───────────────────────────────────────────────────────────────
   `photo`    اسم ملف PNG في مجلد الصور — وهو مصدر الاسم أيضا.
   `inherits` رقم حساب البذرة الذي ينقل محتواه إلى هذا المعلم (اختياري).
   المراحل الثلاث كلها ممثلة: مرشح `/teachers` يعرض `primary|middle|secondary`،
   وطاقم بمرحلة واحدة يجعل خيارين من ثلاثة يردان «لا نتائج». */
$roster = [
    ['photo' => 'محمد ابو النيل.png', 'first' => 'محمد إبراهيم', 'last' => 'محمد ابوالنيل',
     'email' => 'mohamed.abouelnil@taqdaredu.com', 'subject' => 'علوم',
     'title' => 'معلم العلوم', 'stage' => 'middle', 'bio' => 'أول متوسط',
     'rating' => 0, 'reviews' => 0, 'courses' => 0],
    ['photo' => null, 'first' => 'أحمد', 'last' => 'محمود محمد',
     'email' => 'ahmed.mahmoud@taqdaredu.com', 'subject' => 'علوم',
     'title' => 'معلم العلوم', 'stage' => 'primary', 'bio' => 'الرابع والخامس والسادس الابتدائي',
     'rating' => 0, 'reviews' => 0, 'courses' => 0],
    ['photo' => null, 'first' => 'أميرة', 'last' => 'محمد السيد الطويل',
     'email' => 'amira.eltawil@taqdaredu.com', 'subject' => 'علوم',
     'title' => 'معلمة العلوم', 'stage' => 'middle', 'bio' => 'ثالث متوسط',
     'rating' => 0, 'reviews' => 0, 'courses' => 0],
    ['photo' => 'عبد الرحمن خالد.png', 'first' => 'عبدالرحمن خالد', 'last' => 'احمد أبوزيد',
     'email' => 'abdelrahman.khaled@taqdaredu.com', 'subject' => 'علوم',
     'title' => 'معلم العلوم', 'stage' => 'middle', 'bio' => 'ثاني متوسط',
     'rating' => 0, 'reviews' => 0, 'courses' => 0],

    ['photo' => 'عبد الله الباز.png', 'first' => 'عبد الله', 'last' => 'الباز',
     'email' => 'abdullah.elbaz@taqdaredu.com', 'subject' => 'رياضيات',
     'title' => 'معلم الرياضيات', 'stage' => 'middle', 'bio' => 'أول متوسط والسادس الابتدائي',
     'rating' => 0, 'reviews' => 0, 'courses' => 0],
    ['photo' => null, 'first' => 'محمد صبري', 'last' => 'سعد محمد',
     'email' => 'mohamed.sabry@taqdaredu.com', 'subject' => 'رياضيات',
     'title' => 'معلم الرياضيات', 'stage' => 'middle', 'bio' => 'ثاني متوسط',
     'rating' => 0, 'reviews' => 0, 'courses' => 0],
    ['photo' => 'عماء الكومي.png', 'first' => 'عماد أحمد', 'last' => 'السيد حامد',
     'email' => 'emad.elkomy@taqdaredu.com', 'subject' => 'رياضيات',
     'title' => 'معلم الرياضيات', 'stage' => 'middle', 'bio' => 'ثالث متوسط',
     'rating' => 0, 'reviews' => 0, 'courses' => 0],
    ['photo' => 'حازم إسلام.png', 'first' => 'حازم إسلام', 'last' => 'عبد الرحيم علي',
     'email' => 'hazem.eslam@taqdaredu.com', 'subject' => 'رياضيات',
     'title' => 'معلم الرياضيات', 'stage' => 'primary', 'bio' => 'الرابع والخامس الابتدائي',
     'rating' => 0, 'reviews' => 0, 'courses' => 0],

    ['photo' => 'احمد الدسوقي.png', 'first' => 'أحمد', 'last' => 'الدسوقي',
     'email' => 'ahmed.aldesouky@taqdaredu.com', 'subject' => 'دراسات اجتماعية',
     'title' => 'معلم الدراسات الاجتماعية', 'stage' => 'middle', 'bio' => 'أول متوسط',
     'rating' => 0, 'reviews' => 0, 'courses' => 0],
    ['photo' => null, 'first' => 'عبد الرحمن', 'last' => 'زاهر',
     'email' => 'abdelrahman.zaher@taqdaredu.com', 'subject' => 'دراسات اجتماعية',
     'title' => 'معلم الدراسات الاجتماعية', 'stage' => 'middle', 'bio' => 'ثاني متوسط',
     'rating' => 0, 'reviews' => 0, 'courses' => 0],
    ['photo' => 'محمد العجمي.png', 'first' => 'محمد', 'last' => 'العجمي',
     'email' => 'mohamed.elagamy@taqdaredu.com', 'subject' => 'دراسات اجتماعية',
     'title' => 'معلم الدراسات الاجتماعية', 'stage' => 'middle', 'bio' => 'ثالث متوسط',
     'rating' => 0, 'reviews' => 0, 'courses' => 0],
    ['photo' => 'أحمد حسين قدح.png', 'first' => 'أحمد حسين', 'last' => 'قدح',
     'email' => 'ahmed.qadah@taqdaredu.com', 'subject' => 'دراسات اجتماعية',
     'title' => 'معلم الدراسات الاجتماعية', 'stage' => 'primary', 'bio' => 'الرابع والخامس والسادس الابتدائي',
     'rating' => 0, 'reviews' => 0, 'courses' => 0],

    ['photo' => null, 'first' => 'أحمد', 'last' => 'البلتاجي',
     'email' => 'ahmed.elbeltagy@taqdaredu.com', 'subject' => 'اللغة العربية',
     'title' => 'معلم اللغة العربية', 'stage' => 'middle', 'bio' => 'أول متوسط',
     'rating' => 0, 'reviews' => 0, 'courses' => 0],
    ['photo' => null, 'first' => 'عبدالله صلاح', 'last' => 'البرلسي',
     'email' => 'abdullah.elborolosy@taqdaredu.com', 'subject' => 'اللغة العربية',
     'title' => 'معلم اللغة العربية', 'stage' => 'middle', 'bio' => 'ثاني متوسط',
     'rating' => 0, 'reviews' => 0, 'courses' => 0],
    ['photo' => 'محمد حجازي.png', 'first' => 'محمد', 'last' => 'حجازي',
     'email' => 'mohamed.hegazy@taqdaredu.com', 'subject' => 'اللغة العربية',
     'title' => 'معلم اللغة العربية', 'stage' => 'middle', 'bio' => 'ثالث متوسط',
     'rating' => 0, 'reviews' => 0, 'courses' => 0],
    ['photo' => null, 'first' => 'أحمد محمد', 'last' => 'رجب القفاص',
     'email' => 'ahmed.elkaffas@taqdaredu.com', 'subject' => 'اللغة العربية',
     'title' => 'معلم اللغة العربية', 'stage' => 'primary', 'bio' => 'الرابع والخامس والسادس الابتدائي',
     'rating' => 0, 'reviews' => 0, 'courses' => 0],

    ['photo' => 'محمد البوهي.png', 'first' => 'محمد رضا', 'last' => 'صبحي البوهي',
     'email' => 'mohamed.elbouhy@taqdaredu.com', 'subject' => 'لغة إنجليزية',
     'title' => 'معلم اللغة الإنجليزية', 'stage' => 'middle', 'bio' => 'أول متوسط',
     'rating' => 0, 'reviews' => 0, 'courses' => 0],
    ['photo' => 'ياسين طارق.png', 'first' => 'ياسين طارق', 'last' => 'حسنين أحمد',
     'email' => 'yassin.tarek@taqdaredu.com', 'subject' => 'لغة إنجليزية',
     'title' => 'معلم اللغة الإنجليزية', 'stage' => 'middle', 'bio' => 'ثاني متوسط والسادس الابتدائي',
     'rating' => 0, 'reviews' => 0, 'courses' => 0],
    ['photo' => 'محمد نصر.png', 'first' => 'محمد نصر', 'last' => 'معوض خلف الله',
     'email' => 'mohamed.nasr@taqdaredu.com', 'subject' => 'لغة إنجليزية',
     'title' => 'معلم اللغة الإنجليزية', 'stage' => 'middle', 'bio' => 'ثالث متوسط',
     'rating' => 0, 'reviews' => 0, 'courses' => 0],
    ['photo' => 'شيماء عادل.png', 'first' => 'شيماء', 'last' => 'عادل محمد',
     'email' => 'shaimaa.adel@taqdaredu.com', 'subject' => 'لغة إنجليزية',
     'title' => 'معلمة اللغة الإنجليزية', 'stage' => 'primary', 'bio' => 'الرابع والخامس الابتدائي',
     'rating' => 0, 'reviews' => 0, 'courses' => 0],
];

/* ── نقل ما تحت حساب البذرة ──────────────────────────────────────────────
   كل عمود هنا يشير إلى `users.id` فعلا. وما بدا في مسح الأعمدة الرقمية من
   مطابقات أخرى (`question.id` · `currency.id` · `lesson.id` …) مصادفة مفاتيح
   أولية تقع في المدى نفسه، لا إشارة إلى مستخدم. */
$owned_scalar = [
    ['paths',              'teacher_id'],
    ['course',             'creator'],
    ['availability_slots', 'teacher_id'],
    ['tutoring_sessions',  'teacher_id'],
    ['quiz_results',       'approved_by'],
    ['notifications',      'to_user'],
    ['message',            'sender'],
    ['message',            'receiver'],
];

$pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $conf['hostname'], $conf['database']),
    $conf['username'], $conf['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);

/* ---------- حسابات البذرة الحالية ---------- */
$seeds = $pdo->query("SELECT id, first_name, last_name, email FROM users
                       WHERE is_instructor = 1 AND tq_seed = 1 ORDER BY id")
             ->fetchAll(PDO::FETCH_ASSOC);
$seed_ids = array_map('intval', array_column($seeds, 'id'));

echo $apply ? "== تنفيذ ==\n\n" : "== عرض فقط (بلا كتابة) — أضف ‎--apply‎ للتنفيذ ==\n\n";
echo "حسابات البذرة التي ستحذف: " . (count($seeds) ?: 'لا شيء') . "\n";
foreach ($seeds as $s) echo "  #{$s['id']}  {$s['first_name']} {$s['last_name']}  <{$s['email']}>\n";

/* ---------- الصور ---------- */
$updir  = $root . '/uploads/user_image/';
$optdir = $updir . 'optimized/';

/**
 * PNG عمودي ⟵ JPG مربع.
 *
 * القص من الأعلى لا من الوسط: اللقطة 1024x1280 والوجه في ثلثها الأعلى،
 * فمربع مركزي يقطع الرأس ويترك الصدر. و`$top` نسبة من الارتفاع تترك
 * هامشا فوق الرأس ولا أكثر.
 */
function tq_square_jpeg($src, $dst, $side, $top = 0.09, $quality = 86)
{
    $im = @imagecreatefrompng($src);
    if (!$im) return false;
    $w = imagesx($im);
    $h = imagesy($im);
    $cut = min($w, $h);
    $sy  = (int) round($top * $h);
    if ($sy + $cut > $h) $sy = max(0, $h - $cut);
    $sx  = (int) round(($w - $cut) / 2);

    $out = imagecreatetruecolor($side, $side);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
    imagecopyresampled($out, $im, 0, 0, $sx, $sy, $side, $side, $cut, $cut);
    $ok = imagejpeg($out, $dst, $quality);
    imagedestroy($im);
    imagedestroy($out);
    return $ok;
}

/**
 * مفتاح مطابقة اسم ملف عربي.
 *
 * «أحمد» تكتب حرفا واحدا (U+0623) في ويندوز وحرفين (ا + U+0654) بعد نسخها
 * إلى لينكس — بايتات مختلفة ونص واحد على الشاشة. فمقارنة الاسم كما هو تقول
 * «الصورة ناقصة» عن ملف موجود أمامها. والمفتاح يطرح علامات الهمزة المركبة
 * ويرد صورها المفردة إلى أصلها، فيلتقي الطرفان أيا كان الترميز.
 */
function tq_photo_key($s)
{
    $s = str_replace(["\u{0653}", "\u{0654}", "\u{0655}"], '', (string) $s);
    $s = strtr($s, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ؤ' => 'و', 'ئ' => 'ي', 'ى' => 'ي']);
    return preg_replace('~\s+~u', ' ', trim($s));
}

/* ---------- خطة الطاقم ---------- */
$dir_map = [];
foreach ((array) glob(rtrim($photos, '/') . '/*.png') as $f) {
    $dir_map[tq_photo_key(basename($f))] = $f;
}

$plan    = [];
$missing = [];
foreach ($roster as $t) {
    /* `photo => null` معلّم بلا صورة: يمرّ بلا مصدر، و`image` تبقى فارغة
       فيرسم له العرضُ أفاتار الحرف. ولا يُعدّ نقصًا ولا يوقف المرور. */
    if (empty($t['photo'])) {
        $t['photo'] = null;
        $src = null;
    } else {
        $key = tq_photo_key($t['photo']);
        $src = isset($dir_map[$key]) ? $dir_map[$key] : rtrim($photos, '/') . '/' . $t['photo'];
        if (!is_file($src)) $missing[] = $t['photo'];
    }
    $q = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $q->execute([$t['email']]);
    $t['existing'] = $q->fetch(PDO::FETCH_ASSOC) ?: null;
    $t['src']      = $src;
    $t['hash']     = $src ? md5('tq-teacher:' . $t['email']) : '';
    $plan[] = $t;
}

echo "\nمجلد الصور: $photos\n";
if ($missing) {
    echo "!! صور ناقصة (" . count($missing) . "):\n";
    foreach ($missing as $m) echo "   - $m\n";
    exit("\nتوقف: لا ينشأ معلم بلا صورته.\n");
}

echo "\nالطاقم (" . count($plan) . "):\n";
foreach ($plan as $t) {
    $what = $t['existing'] ? 'تحديث #' . $t['existing']['id']
          : (isset($t['inherits']) ? 'إنشاء + يرث #' . $t['inherits'] : 'إنشاء');
    echo '  ' . str_pad($t['first'] . ' ' . $t['last'], 20) . ' | '
       . str_pad($t['subject'], 22) . ' | ' . str_pad($t['stage'], 9) . ' | '
       . str_pad($t['email'], 34) . ' | ' . $what . "\n";
}

if (!$apply) { echo "\nلم يكتب شيء.\n"; exit(0); }

/* ---------- التنفيذ ---------- */
if (!is_dir($optdir)) mkdir($optdir, 0755, true);

$pdo->beginTransaction();
try {
    $creds = [];
    $id_of = [];   // رقم حساب البذرة ⟵ رقم المعلم الجديد الذي يرثه

    foreach ($plan as $t) {
        $now  = time();
        $data = [
            'first_name'      => $t['first'],
            'last_name'       => $t['last'],
            'email'           => $t['email'],
            'skills'          => $t['subject'],
            'biography'       => $t['bio'],
            /* العنوان من الطاقم: «معلّمة» لا تُشتقّ من اسم المادة. */
            'title'           => isset($t['title']) ? $t['title'] : ('معلم ' . $t['subject']),
            'role_id'         => 2,
            'is_instructor'   => 1,
            'is_public'       => 1,
            'status'          => 1,
            'tq_gate'         => 'teacher',
            'tq_seed'         => 0,
            'teacher_stage'   => $t['stage'],
            'teacher_rating'  => $t['rating'],
            'teacher_reviews' => $t['reviews'],
            'teacher_courses' => $t['courses'],
            'image'           => $t['hash'],
            'last_modified'   => $now,
        ];

        if ($t['existing']) {
            $id = (int) $t['existing']['id'];
            if ($repass) {
                $pass = bin2hex(random_bytes(5));
                $data['password'] = password_hash($pass, PASSWORD_DEFAULT);
                $creds[] = [$t['first'] . ' ' . $t['last'], $t['email'], $pass];
            }
            $set = implode(', ', array_map(function ($k) { return "`$k` = ?"; }, array_keys($data)));
            $args = array_values($data);
            $args[] = $id;
            $pdo->prepare("UPDATE users SET $set WHERE id = ?")->execute($args);
        } else {
            $pass = bin2hex(random_bytes(5));
            $data['password']       = password_hash($pass, PASSWORD_DEFAULT);
            $data['date_added']     = $now;
            $data['tq_verified_at'] = date('Y-m-d H:i:s');
            /* أعمدة NOT NULL بلا افتراضي: تركها يرمي الإدراج كله. */
            $data['payment_keys']      = '';
            $data['sessions']          = '';
            $data['verification_code'] = '';
            $cols = implode(', ', array_map(function ($k) { return "`$k`"; }, array_keys($data)));
            $qs   = implode(', ', array_fill(0, count($data), '?'));
            $pdo->prepare("INSERT INTO users ($cols) VALUES ($qs)")->execute(array_values($data));
            $id = (int) $pdo->lastInsertId();
            $creds[] = [$t['first'] . ' ' . $t['last'], $t['email'], $pass];
        }

        /* الرابط المختصر يحمل الرقم، فلا يعرف إلا بعد الإدراج. */
        $slug = strtolower(explode('@', $t['email'])[0]);
        $slug = preg_replace('~[^a-z0-9]+~', '-', $slug) . '-' . $id;
        $pdo->prepare("UPDATE users SET teacher_slug = ? WHERE id = ?")->execute([$slug, $id]);

        if (isset($t['inherits'])) $id_of[(int) $t['inherits']] = $id;

        /* الأصل 720px والمصغرة 220px — و`tqs_person_img()` تفضل الثانية. */
        if ($t['src']) {
            tq_square_jpeg($t['src'], $updir  . $t['hash'] . '.jpg', 720);
            tq_square_jpeg($t['src'], $optdir . $t['hash'] . '.jpg', 220);
        }

        echo "  [ok] #$id  {$t['first']} {$t['last']}\n";
    }

    /* ---------- نقل ما تحت البذرة ثم حذفها ---------- */
    if ($seed_ids) {
        echo "\nنقل المحتوى:\n";
        foreach ($owned_scalar as $pair) {
            list($tbl, $col) = $pair;
            $moved = 0;
            foreach ($seed_ids as $old) {
                if (!isset($id_of[$old])) continue;
                $st = $pdo->prepare("UPDATE `$tbl` SET `$col` = ? WHERE `$col` = ?");
                $st->execute([$id_of[$old], $old]);
                $moved += $st->rowCount();
            }
            if ($moved) echo "  $tbl.$col: $moved\n";
        }

        /* `course.user_id` قائمة بفواصل لا رقما: الكورس يدرسه أكثر من واحد.
           فالاستبدال داخل القائمة لا على الحقل كله، وإلا سقط شريك المعلم. */
        $rows = $pdo->query("SELECT id, user_id FROM course")->fetchAll(PDO::FETCH_ASSOC);
        $n = 0;
        foreach ($rows as $r) {
            $ids = array_filter(array_map('trim', explode(',', (string) $r['user_id'])), 'strlen');
            $new = [];
            $hit = false;
            foreach ($ids as $u) {
                $u = (int) $u;
                if (isset($id_of[$u]))                  { $new[] = $id_of[$u]; $hit = true; }
                elseif (in_array($u, $seed_ids, true))  { $hit = true; }   // بذرة بلا وارث: تسقط
                else                                    { $new[] = $u; }
            }
            if (!$hit) continue;
            $pdo->prepare("UPDATE course SET user_id = ? WHERE id = ?")
                ->execute([implode(',', array_unique($new)), $r['id']]);
            $n++;
        }
        if ($n) echo "  course.user_id: $n\n";

        $in = implode(',', $seed_ids);
        $pdo->exec("DELETE FROM users WHERE id IN ($in)");
        echo "  حذف حسابات البذرة: " . count($seed_ids) . "\n";
    }

    $pdo->commit();

    if ($creds) {
        echo "\n== بيانات الدخول (تظهر مرة واحدة) ==\n";
        foreach ($creds as $c) {
            echo '  ' . str_pad($c[0], 20) . ' | ' . str_pad($c[1], 34) . ' | ' . $c[2] . "\n";
        }
    }
    echo "\nتم.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    exit("\nفشل، ولم يكتب شيء: " . $e->getMessage() . "\n");
}
