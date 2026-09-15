<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * شاشات الطالب — طبقة البيانات.
 *
 * كل ما يمكن وصله بقاعدة taqd_lms موصول هنا، ومصدره واحد لكل الشاشات
 * حتى لا يختلف رقم بين شاشة وأخرى. وما لا مصدر له بعد لا يخترع:
 * الدالة ترجع مصفوفة فارغة، والشاشة تعرض حالتها الفارغة.
 *
 * وكان هذا الملف يعيش في `views/frontend/taqdar/tq_student_data.php` —
 * أي أن **طبقة البيانات كانت في مجلد العرض**، وقالبا يضمها بـ`include`
 * نسبي. وذلك يعمل ما دام القارئ قالبا؛ فلما جاءت `Api_v1` تسأل الأسئلة
 * نفسها لم تجد ما تناديه إلا ملف عرض — و`tq_student_styles.php` الذي
 * يحمل نصف دواله يطبع كتلة `<style>` عند ضمه، فأول نداء منها يكتب CSS
 * فوق JSON ويرمي عميل Dart `FormatException`.
 *
 * فصار مساعدا محملا تلقائيا (`autoload.php`): الويب والتطبيق يقرآن
 * الدالة نفسها، ولا نسخة ثانية تفترق عن أختها عند أول تعديل. والقوالب
 * تبقى على `include` الذي تكتبه اليوم — الملف المضموم صار تعليقا،
 * وحراس `function_exists` تحته تمنع أي إعادة تعريف.
 *
 * جداول موصولة: enrol · course · lesson · section · watch_histories ·
 * watched_duration · quiz_results · question · category · resource_files ·
 * notifications · message.
 */

if (!function_exists('tq_s_secs')) {
    /** «45:30» أو «01:12:40» إلى ثوان. مدد Academy تخزن كنص ساعة. */
    function tq_s_secs($hms)
    {
        $parts = array_map('intval', explode(':', trim((string) $hms)));
        $n = count($parts);
        if ($n === 3) return $parts[0] * 3600 + $parts[1] * 60 + $parts[2];
        if ($n === 2) return $parts[0] * 60 + $parts[1];
        return $n === 1 ? $parts[0] : 0;
    }
}

if (!function_exists('tq_file_kind')) {
    /**
     * نوع الملف: أيقونة ولون ثابتان لكل نوع في كل شاشة من المنصة.
     * تغييرهما في شاشة واحدة يجعل الطالب يعيد التعرف على النوع في كل صفحة.
     */
    function tq_file_kind($file_name, $hint = '')
    {
        static $map = [
            'pdf'   => ['key' => 'pdf',   'label' => 'PDF',        'icon' => 'file',     'pastel' => 'rose'],
            'video' => ['key' => 'video', 'label' => 'فيديو',      'icon' => 'video',    'pastel' => 'sky'],
            'slide' => ['key' => 'slide', 'label' => 'عرض تقديمي', 'icon' => 'chart',    'pastel' => 'peach'],
            'audio' => ['key' => 'audio', 'label' => 'ملف صوتي',  'icon' => 'play',     'pastel' => 'lilac'],
            'image' => ['key' => 'image', 'label' => 'صورة',       'icon' => 'folder',   'pastel' => 'mint'],
            'link'  => ['key' => 'link',  'label' => 'رابط',       'icon' => 'clipboard','pastel' => 'sand'],
            'doc'   => ['key' => 'doc',   'label' => 'مستند',      'icon' => 'file',     'pastel' => 'sand'],
        ];
        static $ext = [
            'pdf' => 'pdf',
            'mp4' => 'video', 'mkv' => 'video', 'webm' => 'video', 'mov' => 'video', 'avi' => 'video',
            'ppt' => 'slide', 'pptx' => 'slide', 'key' => 'slide', 'odp' => 'slide',
            'mp3' => 'audio', 'wav' => 'audio', 'm4a' => 'audio', 'ogg' => 'audio', 'aac' => 'audio',
            'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'gif' => 'image', 'webp' => 'image', 'svg' => 'image',
            'doc' => 'doc', 'docx' => 'doc', 'txt' => 'doc', 'rtf' => 'doc', 'xls' => 'doc', 'xlsx' => 'doc',
            'zip' => 'doc', 'rar' => 'doc',
        ];

        /* TQ-I18N — التسمية وحدها تترجم؛ و`key` و`icon` و`pastel` رموز. */
        $out = function ($row) { $row['label'] = t($row['label']); return $row; };

        $name = trim((string) $file_name);
        if ($name !== '' && preg_match('~^https?://~i', $name)) return $out($map['link']);

        $e = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($e !== '' && isset($ext[$e])) return $out($map[$ext[$e]]);

        $h = strtolower(trim((string) $hint));
        if (isset($map[$h])) return $out($map[$h]);
        if ($h !== '' && isset($ext[$h])) return $out($map[$ext[$h]]);

        return $out($map['doc']);
    }
}

if (!function_exists('tq_s_ts')) {
    /** Academy يخزن الأوقات نصا: طابعا زمنيا أحيانا وتاريخا أحيانا. */
    function tq_s_ts($value)
    {
        $v = trim((string) $value);
        if ($v === '' || $v === '0') return 0;
        if (ctype_digit($v)) return (int) $v;
        $t = strtotime($v);
        return $t ?: 0;
    }
}

if (!function_exists('tq_s_uid')) {
    /** الطالب الحالي — صفر يعني زائرا، فتعرض حالة «سجل دخولك». */
    function tq_s_uid()
    {
        $CI = get_instance();
        return isset($CI->session) ? (int) $CI->session->userdata('user_id') : 0;
    }
}

if (!function_exists('tq_s_counts')) {
    /** عدادات القائمة والترويسة — من الجداول لا من ثوابت مكتوبة. */
    function tq_s_counts($uid)
    {
        $CI = get_instance();
        if ($uid <= 0) return [];
        $msg = (int) $CI->db->where('receiver', $uid)->where('read_status', 0)->count_all_results('message');
        $ntf = (int) $CI->db->where('to_user', $uid)->where('status', 0)->count_all_results('notifications');
        $out = [];
        if ($msg) $out['messages'] = $msg;
        if ($ntf) $out['notifications'] = $ntf;
        return $out;
    }
}

if (!function_exists('tq_s_progress')) {
    /**
     * تقدّم الطالب بالثواني — استعلام واحد لكلّ الطالب، مكاش ثابت.
     *
     * ═══ لماذا مصدر ثانٍ ═══
     *
     * `watch_histories` مرآة موروثة **بلا ثانية واحدة**: نسبة مئوية
     * وقائمة معرّفات ومعرّف آخر درس لُمس. فبطاقة تقرأ منه وحده تكتب
     * «لم يبدأ» لطالبٍ شاهد خمس دقائق — وهو ما يقع اليوم في أربعة
     * تسجيلات. و`lesson_progress.position_sec` يعرف **أين** توقّف
     * بالضبط، وعليه يُرسم خطّ الزمن.
     *
     * ═══ ولماذا لا يُطرح القديم ═══
     *
     * ثلاثة طلاب يحملون إتمامات في المرآة بلا صفٍّ في `lesson_progress`.
     * فالإتمام **اتّحاد المصدرين** لا استبدال أحدهما — وإسقاط الموروث
     * يمحو تقدّمًا حقيقيًّا من شاشة صاحبه.
     *
     * @return array by_lesson[معرّف الدرس] · by_course[معرّف الكورس][]
     */
    function tq_s_progress($uid)
    {
        static $cache = [];
        $uid = (int) $uid;
        if (isset($cache[$uid])) return $cache[$uid];
        if ($uid <= 0) return $cache[$uid] = ['by_lesson' => [], 'by_course' => []];

        $CI = get_instance();
        /* `e.user_id` يُدرج مصبوبًا `int`: باني الاستعلام في CI3 لا يربط
           داخل شرط `ON`، فالصبّ هو الحارس. */
        $rows = $CI->db
            ->select('lp.lesson_id, lp.position_sec, lp.watch_seconds, lp.completed_at,'
                   . ' lp.mastered_at, lp.last_ping_at,'
                   . ' l.course_id, l.duration_sec, l.duration, l.title')
            ->from('lesson_progress lp')
            ->join('lesson l', 'l.id = lp.lesson_id', 'inner')
            ->join('enrol e', 'e.course_id = l.course_id AND e.user_id = ' . $uid, 'inner')
            ->where('lp.student_id', $uid)
            ->where('l.lesson_type !=', 'quiz')
            ->get()->result_array();

        $by_lesson = [];
        $by_course = [];
        foreach ($rows as $r) {
            $lid = (int) $r['lesson_id'];
            $cid = (int) $r['course_id'];
            /* مرآة `lesson_duration()`: العمود الصحيح أوّلًا، والنصّ
               احتياطًا — ولا تُقاس المدّة بما أعلنه مشغّل الطالب
               (`media_sec`)، فصفٌّ واحد يقول ١١٩ ومدّته ٦٣٥. */
            $dur = (int) $r['duration_sec'] > 0
                 ? (int) $r['duration_sec']
                 : tq_s_secs($r['duration']);
            $p = [
                'lesson_id' => $lid,
                'course_id' => $cid,
                'pos'       => (int) $r['position_sec'],
                'watched'   => (int) $r['watch_seconds'],
                'complete'  => $r['completed_at'] !== null,
                'ping'      => $r['last_ping_at'],
                'duration'  => $dur,
                'title'     => (string) $r['title'],
            ];
            $by_lesson[$lid] = $p;
            $by_course[$cid][] = $p;
        }
        return $cache[$uid] = ['by_lesson' => $by_lesson, 'by_course' => $by_course];
    }
}

if (!function_exists('tq_s_enrolled')) {
    /**
     * الكورسات المسجلة مع تقدمها الحقيقي.
     *
     * التقدم يقرأ من **مصدرين موحّدين** لا من المرآة وحدها
     * (`tq_s_progress()` — وهناك شرحه)، وعدد الدروس ومددها من `lesson`،
     * وموضع التوقف بالثواني لا بمعرّف درس وحده — وهو ما يعيد زر
     * «استكمل التعلم» الطالب إليه بالضبط لا إلى أول الكورس.
     */
    function tq_s_enrolled($uid)
    {
        static $cache = [];
        if (isset($cache[$uid])) return $cache[$uid];

        $CI = get_instance();
        if ($uid <= 0) return $cache[$uid] = [];

        $courses = $CI->db
            ->select('c.id, c.title, c.thumbnail, c.level, c.category_id, c.sub_category_id, c.status AS course_status,'
                   . ' c.user_id AS instructor_id, e.date_added AS enrolled_at')
            ->from('enrol e')
            ->join('course c', 'c.id = e.course_id', 'inner')
            ->where('e.user_id', $uid)
            ->order_by('e.date_added', 'DESC')
            ->get()->result_array();

        if (empty($courses)) return $cache[$uid] = [];

        $ids = array_map('intval', array_column($courses, 'id'));

        /* مدة الدرس نص «hh:mm:ss» فلا تجمع في SQL — تجمع بعد التحويل.
           TQ-PUBLISHED-COUNT — والمسند القانوني للنشر هنا كما هو في
           `Taqdar.php:216` و`Taqdar_catalog_model:228`: المسودة لا تفتح
           (يرد المتحكم 404) فلا تعد ولا تدخل مقام النسبة. */
        $lessons = $CI->db->select('id, course_id, duration, lesson_type')
            ->from('lesson')->where_in('course_id', $ids)
            ->where('COALESCE(`tq_status`, "published") =', 'published')->get()->result_array();

        $count = [];
        $secs  = [];
        $counted_ids = [];   // ما عُدّ في المقام، ليقاس عليه البسط
        foreach ($lessons as $l) {
            $cid = (int) $l['course_id'];
            if (($l['lesson_type'] ?? '') === 'quiz') continue;
            $counted_ids[$cid][(int) $l['id']] = true;
            $count[$cid] = ($count[$cid] ?? 0) + 1;
            $secs[$cid]  = ($secs[$cid] ?? 0) + tq_s_secs($l['duration']);
        }

        $watch = $CI->db->select('course_id, course_progress, completed_lesson, watching_lesson_id, date_updated')
            ->from('watch_histories')->where('student_id', $uid)->where_in('course_id', $ids)
            ->get()->result_array();

        $hist = [];
        foreach ($watch as $w) $hist[(int) $w['course_id']] = $w;

        $prog = tq_s_progress($uid);
        $CI->load->model('taqdar_repo_model');

        $out = [];
        foreach ($courses as $i => $c) {
            $cid   = (int) $c['id'];
            $h     = $hist[$cid] ?? null;
            $rows  = $prog['by_course'][$cid] ?? [];

            /* ── TQ-PROGRESS-ONE — المنجز والنسبة والوجهة من `course_state()` ──
               كان «المكتمل» اتحاد `watch_histories.completed_lesson` و
               `completed_at`، والقفل يسأل «أتقن السابق إن كان له اختبار». فتقول
               البطاقة ١٢ درسا مكتملا ويرد الخادم فتح عشرة منها، ويأخذ زر
               «تابع» الطالب إلى درس مقفل. والآن رقم واحد بقاعدة القفل نفسها. */
            $st    = $CI->taqdar_repo_model->course_state($uid, $cid);
            $total = (int) $st['total'];
            $done  = (int) $st['done'];
            $pct   = max(0, min(100, (int) $st['percent']));

            $cur_id = 0; $cur_title = ''; $cur_pos = 0; $cur_dur = 0;
            if ((int) $st['resume_pos'] > 0) {
                $cur_id    = (int) $st['resume_lesson_id'];
                $cur_title = (string) $st['lessons'][$cur_id]['title'];
                $cur_pos   = (int) $st['resume_pos'];
                foreach ($rows as $p) if ($p['lesson_id'] === $cur_id) $cur_dur = $p['duration'];
            }

            $pos_seen = 0; $watched = 0;
            foreach ($rows as $p) { $pos_seen = max($pos_seen, $p['pos']); $watched += $p['watched']; }

            /* ── الحالة دالة في الأرقام التي يرسمها الشريط ─────────── */
            $status = 'idle';
            if ($done > 0 || $pos_seen > 15 || $watched > 15) $status = 'progress';
            if ($total > 0 && $done >= $total) $status = 'done';

            /* ── قطع الشريط: قطعة لكل درس بترتيب القفل نفسه ─────────── */
            $segments = [];
            foreach ($st['lessons'] as $lid => $ls) {
                if ($ls['is_quiz']) continue;
                if ($ls['done']) {
                    $segments[] = ['id' => $lid, 'title' => $ls['title'], 'state' => 'done', 'fill' => 100, 'locked' => false];
                } elseif ($lid === $cur_id) {
                    $f = $cur_dur > 0 ? (int) round($cur_pos * 100 / $cur_dur) : 0;
                    $f = max(2, min(98, $f));
                    $segments[] = ['id' => $lid, 'title' => $ls['title'], 'state' => 'current', 'fill' => $f, 'locked' => false];
                } else {
                    $segments[] = ['id' => $lid, 'title' => $ls['title'], 'state' => 'todo', 'fill' => 0, 'locked' => $ls['locked']];
                }
            }

            /* الوجهة درس **يفتح**: ما وقف فيه، وإلا أول مفتوح لم ينجز. */
            $resume = (int) $st['resume_lesson_id'];

            $out[] = [
                'id'         => $cid,
                /* حال الكورس في المنصة لا حال الطالب فيه: كورس مسودة أو قيد
                   المراجعة يبقى عند من سجل فيه، ويقال ذلك على بطاقته. */
                'course_status' => (string) ($c['course_status'] ?? ''),
                'title'      => $c['title'],
                'thumbnail'  => $c['thumbnail'],
                'level'      => $c['level'],
                'category_id' => (int) $c['category_id'],
                'index'      => $i,
                'lessons'    => $total,
                'done'       => $done,
                'seconds'    => $secs[$cid] ?? 0,
                'progress'   => $pct,
                'resume_id'  => $resume,
                'touched_at' => $h ? tq_s_ts($h['date_updated']) : tq_s_ts($c['enrolled_at']),
                /* TQ-RESUME-TRUTH — `touched_at` يرتد إلى تاريخ التسجيل حين لا سجل
                   مشاهدة، وهو ارتداد صالح للترتيب وكاذب للاستئناف: مقرر سجل أمس
                   ولم يفتح يفوز بسطر «واصل … حيث توقفت». فيميز الحالان هنا، وتقرأ
                   `tq_s_resume` التمييز بدل أن تخمنه من الوقت. */
                'has_history' => ($h !== null),
                'status'     => $status,

                /* الغلاف التفاعليّ */
                'segments'      => $segments,
                'current_id'    => $cur_id,
                'current_title' => $cur_title,
                'position_sec'  => $cur_pos,
                'watched_sec'   => $watched,
            ];
        }

        return $cache[$uid] = $out;
    }
}

if (!function_exists('tq_s_lessons')) {
    /**
     * دروس الطالب — **الدرس** وحدة الصف لا الكورس.
     *
     * كانت شاشة «دروسي» تعرض بطاقات كورسات: عنوان الشاشة يقول درسا،
     * وما تحته صف من الكورسات نفسه المعروض في «كورساتي». فمن أراد درسا
     * بعينه — «أين درس الكسور؟» — لم يجد له مدخلا في البوابة كلها إلا
     * أن يفتح الكورس ثم يمسح منهجه بعينه.
     *
     * فهذه الدالة تقرأ الدروس أنفسها: كل درس في كورس مسجل، ومعه وحدته
     * وكورسه ومدته وحالته. والحالة من `watch_histories` نفسه الذي تقرأ
     * منه بطاقات الكورسات — مصدر واحد فلا يختلف رقم بين شاشتين:
     *   • `done`    — معرفه في `completed_lesson`
     *   • `current` — هو `watching_lesson_id` ولم يكتمل
     *   • `todo`    — ما عدا ذلك
     *
     * والاختبارات (`lesson_type = quiz`) تستثنى: لها شاشتها `اختباراتي`،
     * وخلطها بالدروس يجعل عداد «٣٥ من ١١٢ درسا» يخالف عداد الكورسات.
     */
    function tq_s_lessons($uid)
    {
        static $cache = [];
        $uid = (int) $uid;
        if (isset($cache[$uid])) return $cache[$uid];
        if ($uid <= 0) return $cache[$uid] = [];

        $CI = get_instance();

        $rows = $CI->db
            ->select('l.id, l.title, l.duration, l.duration_sec, l.lesson_type, l.is_free, l.section_id,'
                   . ' l.order AS lesson_order, l.date_added,'
                   . ' c.id AS course_id, c.title AS course_title, c.level, c.category_id,'
                   . ' c.thumbnail')
            ->from('lesson l')
            ->join('enrol e', 'e.course_id = l.course_id', 'inner')
            ->join('course c', 'c.id = l.course_id', 'inner')
            ->where('e.user_id', $uid)
            ->where('l.lesson_type !=', 'quiz')
            ->where('COALESCE(l.`tq_status`, "published") =', 'published')
            ->order_by('l.course_id', 'ASC')
            ->order_by('l.section_id', 'ASC')
            ->order_by('l.order', 'ASC')
            ->order_by('l.id', 'ASC')
            ->get()->result_array();

        if (empty($rows)) return $cache[$uid] = [];

        /* عناوين الوحدات — استعلام واحد لكل الكورسات لا واحد لكل درس. */
        $section_ids = array_values(array_unique(array_filter(
            array_map('intval', array_column($rows, 'section_id'))
        )));
        $units = [];
        if ($section_ids) {
            foreach ($CI->db->select('id, title')->from('section')
                        ->where_in('id', $section_ids)->get()->result_array() as $s) {
                $units[(int) $s['id']] = (string) $s['title'];
            }
        }

        /* ── TQ-PROGRESS-ONE — الحالة والقفل من `course_state()` ───────
           كانت الحالة من `watch_histories` مع `completed_at`، والقفل من قاعدة
           أخرى: فيقرأ الطالب «أتممته» على درس يرده الخادم، و٤٥ درسا مقفلا
           من ٥٩ بلا إشارة واحدة. والترتيب ترتيب القفل (الوحدة ثم الدرس). */
        $CI->load->model('taqdar_repo_model');
        $states = [];
        foreach (array_values(array_unique(array_map('intval', array_column($rows, 'course_id')))) as $cid) {
            $states[$cid] = $CI->taqdar_repo_model->course_state($uid, $cid);
        }

        $out = [];
        foreach ($rows as $r) {
            $lid = (int) $r['id'];
            $cid = (int) $r['course_id'];
            $st  = $states[$cid];
            $ls  = $st['lessons'][$lid] ?? null;
            if ($ls === null) continue;   // خارج ترتيب القفل = غير منشور

            $state = 'todo';
            if ($ls['done'])                                                     $state = 'done';
            elseif ((int) $st['resume_pos'] > 0 && (int) $st['resume_lesson_id'] === $lid) $state = 'current';

            $out[] = [
                'locked'     => $ls['locked'],
                'lock_hint'  => (string) $ls['blocking_title'],
                /* أكمل الفيديو ولم يجتز اختباره: «أتممته» كذب، و«لم يبدأ» كذب. */
                'needs_quiz' => ($ls['completed'] && !$ls['done']),
                'order'      => (int) $ls['order'],
                'id'       => $lid,
                'title'    => (string) $r['title'],
                'unit'     => $units[(int) $r['section_id']] ?? '',
                'course_id' => $cid,
                'course'   => (string) $r['course_title'],
                'subject'  => tq_s_subject($r['category_id'], (string) $r['course_title'], $cid),
                'level'    => (string) $r['level'],
                'thumbnail' => (string) $r['thumbnail'],
                'type'     => (string) $r['lesson_type'],
                'duration_sec' => (int) $r['duration_sec'],
                'free'     => (int) $r['is_free'] === 1,
                'seconds'  => tq_s_secs($r['duration']),
                'state'    => $state,
                'at'       => $ls['ping'] !== '' ? tq_s_ts($ls['ping']) : tq_s_ts($r['date_added']),
                'url'      => tq_s_lesson_url($cid, $lid),
            ];
        }

        usort($out, static function ($a, $b) {
            return [$a['course_id'], $a['order']] <=> [$b['course_id'], $b['order']];
        });
        foreach ($out as $i => &$o) $o['index'] = $i;
        unset($o);

        return $cache[$uid] = $out;
    }
}

if (!function_exists('tq_s_categories')) {
    /** المواد — من جدول category، وتستعمل تسمية في التصفية والتصنيف. */
    function tq_s_categories()
    {
        static $cache = null;
        if ($cache !== null) return $cache;
        $CI = get_instance();
        $rows = $CI->db->select('id, name, parent')->from('category')->order_by('name', 'ASC')->get()->result_array();
        $out = [];
        foreach ($rows as $r) $out[(int) $r['id']] = $r['name'];
        return $cache = $out;
    }
}

if (!function_exists('tq_s_course_subjects')) {
    /**
     * [معرف الكورس => اسم مادته] من `paths.subject_id`.
     *
     * «المادة» في هذه القاعدة جدول `subjects` (رياضيات · لغة عربية · علوم…)،
     * و`category` جدول **مراحل** (ابتدائية · متوسطة · ثانوية). وكانت الشاشات
     * تقرأ المادة من `course.category_id` — وهو صفر في كل كورس منشور، فتسقط
     * كل تسمية مادة إلى عنوان الكورس، ويبقى منتقي المادة في «حصص بالطلب»
     * فارغا أبدا. والربط الصحيح موجود في `paths` منذ البداية: لكل كورس مسار،
     * وللمسار مادته.
     */
    function tq_s_course_subjects()
    {
        static $cache = null;
        if ($cache !== null) return $cache;

        $CI  = get_instance();
        $out = [];
        foreach ($CI->db->select('p.course_id, s.name_ar')
                        ->from('paths p')
                        ->join('subjects s', 's.id = p.subject_id', 'inner')
                        ->where('p.course_id >', 0)
                        ->get()->result_array() as $r) {
            $out[(int) $r['course_id']] = (string) $r['name_ar'];
        }
        return $cache = $out;
    }
}

if (!function_exists('tq_s_subject')) {
    /**
     * اسم المادة لكورس: مادته من مساره أولا، ثم تصنيفه، ثم البديل.
     * و`$course_id` اختياري لئلا تنكسر مناداة قديمة لا تعرفه.
     */
    function tq_s_subject($category_id, $fallback = '', $course_id = 0)
    {
        $course_id = (int) $course_id;
        if ($course_id > 0) {
            $map = tq_s_course_subjects();
            if (isset($map[$course_id]) && $map[$course_id] !== '') return $map[$course_id];
        }
        $cats = tq_s_categories();
        return $cats[(int) $category_id] ?? $fallback;
    }
}

if (!function_exists('tq_s_subject_tutors')) {
    /**
     * المواد وعدد معلميها — وهو عدد معلمي المادة لا عدد المتاحين الآن.
     *
     * المصدر `paths.subject_id` لا `course.category_id`: الأخير صفر في كل
     * كورس منشور، فكان الشرط `category_id > 0` يرد كل الصفوف ويبقى «اختر
     * المادة» فارغا مهما نشرت المنصة من مواد ومعلمين.
     */
    function tq_s_subject_tutors($limit = 5)
    {
        $CI = get_instance();
        $rows = $CI->db->select('p.subject_id, s.name_ar, COUNT(DISTINCT c.user_id) AS n', false)
            ->from('paths p')
            ->join('course c', 'c.id = p.course_id', 'inner')
            ->join('subjects s', 's.id = p.subject_id', 'inner')
            ->where('c.status', 'active')
            ->where('c.user_id !=', '')
            ->group_by('p.subject_id')
            ->order_by('n', 'DESC')
            ->limit((int) $limit)
            ->get()->result_array();

        $out = [];
        foreach ($rows as $r) {
            $name = trim((string) $r['name_ar']);
            if ($name === '') continue;
            $out[] = ['id' => (int) $r['subject_id'], 'name' => $name, 'tutors' => (int) $r['n']];
        }
        return $out;
    }
}

if (!function_exists('tq_s_quizzes')) {
    /**
     * الاختبارات: درس من نوع quiz في كورس مسجل.
     * حالته من quiz_results — بلا نتيجة «قادم»، وبنتيجة غير مسلمة «جار»،
     * وبنتيجة مسلمة «منته». وهذا كل ما تعرفه القاعدة اليوم:
     * لا موعد بدء ولا مدة مجدولة، فلا تعرض مواعيد مخترعة.
     */
    function tq_s_quizzes($uid)
    {
        static $cache = [];
        if (isset($cache[$uid])) return $cache[$uid];

        $CI = get_instance();
        if ($uid <= 0) return $cache[$uid] = [];

        $rows = $CI->db
            ->select('l.id, l.title, l.course_id, l.section_id, l.date_added,'
                   . ' c.title AS course_title, c.level, c.category_id')
            ->from('lesson l')
            ->join('enrol e', 'e.course_id = l.course_id', 'inner')
            ->join('course c', 'c.id = l.course_id', 'inner')
            ->where('e.user_id', $uid)
            ->where('l.lesson_type', 'quiz')
            /* اختبار درس مسودة لم يعتمد لا يعرض: المشغل يرده 404. */
            ->where("COALESCE(l.tq_status, 'published') = 'published'", null, false)
            ->get()->result_array();

        /* لا خروج مبكر هنا: طبقة الإتقان تضاف في آخر الدالة، وقد يكون
           الموروث فارغا وهي عامرة — وهو الحال الغالب اليوم. */
        if (empty($rows)) {
            return $cache[$uid] = tq_s_exam_finalize($uid, tq_s_assessment_quizzes($uid));
        }

        $qids = array_map('intval', array_column($rows, 'id'));

        $counts = [];
        foreach ($CI->db->select('quiz_id, COUNT(*) AS n')->from('question')
                    ->where_in('quiz_id', $qids)->group_by('quiz_id')->get()->result_array() as $q) {
            $counts[(int) $q['quiz_id']] = (int) $q['n'];
        }

        $results = [];
        foreach ($CI->db->select('quiz_result_id, quiz_id, total_obtained_marks, is_submitted,'
                               . ' date_added, date_updated, teacher_score, teacher_note, approved_at')
                    ->from('quiz_results')->where('user_id', $uid)->where_in('quiz_id', $qids)
                    ->order_by('quiz_result_id', 'ASC')->get()->result_array() as $r) {
            $results[(int) $r['quiz_id']] = $r;   // الأحدث يغلب
        }

        $out = [];
        foreach ($rows as $i => $r) {
            $qid   = (int) $r['id'];
            $marks = $counts[$qid] ?? 0;
            /* TQ-EMPTY-QUIZ — القاعدة نفسها التي يطبقها فرع التقييمات (`HAVING marks > 0`):
               اختبار بلا سؤال واحد صف أنشئ ولم يؤلف، وعرضه «متاح الآن — ٠ سؤال» يعد
               الطالب باختبار لا يجده. */
            if ($marks < 1) continue;
            $res   = $results[$qid] ?? null;

            $state = 'upcoming';
            if ($res !== null) $state = ((int) $res['is_submitted'] === 1) ? 'done' : 'live';

            /* الحجب عند المصدر: من هنا تغذى كل شاشة، فلا تنسى واحدة. */
            $CI->load->model('taqdar_marking_model');
            $sv = $res !== null
                ? $CI->taqdar_marking_model->student_view($res)
                : array('visible' => false, 'score' => null, 'state' => 'unsubmitted', 'note' => '');

            $visible = !empty($sv['visible']);
            $got = $visible ? (float) $sv['score'] : null;
            $pct = ($visible && $state === 'done' && $marks > 0)
                 ? (int) round($got * 100 / $marks) : null;

            $out[] = [
                'id'       => $qid,
                'title'    => $r['title'],
                'course_id' => (int) $r['course_id'],
                'course'   => $r['course_title'],
                'subject'  => tq_s_subject($r['category_id'], $r['course_title'], $r['course_id']),
                'level'    => $r['level'],
                'marks'    => $marks,
                'obtained' => $got,
                'percent'  => $pct,
                'visible'  => $visible,
                'grade_state' => $sv['state'],
                'teacher_note' => (string) $sv['note'],
                'state'    => $state,
                'index'    => $i,
                'started_at' => $res !== null ? tq_s_ts($res['date_added']) : 0,
                'ended_at'   => $res !== null ? tq_s_ts($res['date_updated']) : 0,
            ];
        }

        /* ── ٢ · اختبارات الدرس — بوابة الإتقان — TQ-EXAM-SOURCE ────────
           الكتلة أعلاه تقرأ **النظام الموروث**: درس `lesson_type='quiz'`
           ونتيجته في `quiz_results`. وقد توقف تأليف الاختبارات به منذ
           صار اختبار الدرس تقييم `assessments(type='review')` بأسئلة في
           `question.assessment_id` (انظر رأس [Taqdar_quiz_model.php]).
           فكانت هذه الشاشة تقول «لا اختبارات بعد» لطالب سلم أربع محاولات
           في الأسبوع الماضي — لأنها تسأل الجدول الذي هجر.
           والقراءة الآن من الاثنين: الموروث لما بقي منه، والحي لما يؤلف
           اليوم. */
        foreach (tq_s_assessment_quizzes($uid) as $q) $out[] = $q;

        return $cache[$uid] = tq_s_exam_finalize($uid, $out);
    }
}

if (!function_exists('tq_s_exam_finalize')) {
    /**
     * آخر ما يمر به كل مصدر للاختبارات: دمج المكرر، وامتحانات المحطات، والقفل.
     *
     * TQ-EXAM-DEDUPE — **الاختبار الواحد مرة واحدة.** دمج النظامين (الموروث
     * و`assessments`) كان يضيف الثاني إلى الأول بلا فحص: درس اختبار له صف في
     * الاثنين يعرض مرتين — «اختبار الوحدة الأولى ٨٠٪» في سطرين، ومرة قادما
     * ومرة منتهيا. والمفتاح معرف الدرس، ويبقى الأوفر حالا (منته ثم جار ثم
     * قادم)، ثم ما جاء من الطبقة الحية.
     *
     * TQ-EXAM-STATION — **وامتحانات المحطات تعرض.** هي التي تصدر بها الشهادات
     * (`assessments.type = 'exam'` على محطة مسار)، ولم تكن هذه الشاشة تسأل عنها
     * أصلا: من اجتاز امتحان محطته لا يجد له أثرا في «اختباراتي». وتعرض ما أداه
     * منها وحده — ولا بطاقة «ابدأ» لما لم يؤده، فبدؤه من محطته لا من هنا.
     */
    function tq_s_exam_finalize($uid, array $out)
    {
        $rank = ['done' => 3, 'live' => 2, 'upcoming' => 1];
        $by = [];
        foreach ($out as $q) {
            $k = (int) $q['id'];
            if (!isset($by[$k])) { $by[$k] = $q; continue; }
            $a = $rank[$by[$k]['state']] ?? 0;
            $b = $rank[$q['state']] ?? 0;
            if ($b > $a || ($b === $a && ($q['grade_state'] ?? '') === 'auto')) $by[$k] = $q;
        }
        $out = array_values($by);

        foreach (tq_s_station_exams($uid) as $q) $out[] = $q;

        usort($out, function ($a, $b) { return $b['ended_at'] <=> $a['ended_at']; });
        foreach ($out as $i => &$q) $q['index'] = $i;
        unset($q);
        return tq_s_exam_lock($uid, $out);
    }
}

if (!function_exists('tq_s_station_exams')) {
    /** امتحانات المحطات التي أداها الطالب — آخر محاولة لكل امتحان. */
    function tq_s_station_exams($uid)
    {
        $CI  = get_instance();
        $uid = (int) $uid;
        if ($uid <= 0) return [];

        try {
            $rows = $CI->db->query(
                'SELECT t.`assessment_id`, t.`score`, t.`started_at`, t.`submitted_at`,
                        COALESCE(m.`title`, p.`title`) AS title, p.`course_id`,
                        c.`title` AS course_title, c.`level`, c.`category_id`,
                        (SELECT COUNT(*) FROM `question` q WHERE q.`assessment_id` = a.`id`) AS marks
                   FROM `attempts` t
                   JOIN `assessments` a ON a.`id` = t.`assessment_id` AND a.`type` = "exam"
                   LEFT JOIN `milestones` m ON m.`id` = a.`milestone_id`
                   LEFT JOIN `paths` p ON p.`id` = COALESCE(a.`path_id`, m.`path_id`)
                   LEFT JOIN `course` c ON c.`id` = p.`course_id`
                  WHERE t.`student_id` = ?
                  ORDER BY t.`id` ASC', array($uid))->result_array();
        } catch (Throwable $e) {
            log_message('error', 'TQ-EXAM-STATION: ' . $e->getMessage());
            return [];
        }

        $last = [];
        foreach ($rows as $r) $last[(int) $r['assessment_id']] = $r;   // الأحدث يغلب

        $out = [];
        foreach ($last as $aid => $r) {
            $marks = (int) $r['marks'];
            $done  = !empty($r['submitted_at']);
            $got   = $done ? (float) $r['score'] : null;
            $out[] = [
                /* معرف سالب: لا درس لامتحان المحطة، ومعرف موجب يصطدم بدرس
                   حقيقي في دمج المكرر وفي عداد المدة. */
                'id'           => -$aid,
                'title'        => (string) ($r['title'] ?: t('امتحان محطة')),
                'course_id'    => (int) $r['course_id'],
                'course'       => (string) $r['course_title'],
                'subject'      => tq_s_subject($r['category_id'], (string) $r['course_title'], (int) $r['course_id']),
                'level'        => (string) $r['level'],
                'marks'        => $marks,
                'obtained'     => $got,
                'percent'      => ($done && $marks > 0) ? (int) round($got * 100 / $marks) : null,
                'visible'      => $done,
                'grade_state'  => $done ? 'auto' : 'unsubmitted',
                'teacher_note' => '',
                'state'        => $done ? 'done' : 'live',
                'station'      => true,
                'started_at'   => tq_s_ts($r['started_at']),
                'ended_at'     => tq_s_ts($r['submitted_at']),
            ];
        }
        return $out;
    }
}

if (!function_exists('tq_s_exam_lock')) {
    /**
     * TQ-EXAM-LOCK — بطاقة الاختبار تقرأ القرار الذي يقرؤه زر البدء.
     *
     * كانت كل بطاقة «قادمة» تقول «متاح الآن — ابدأ الاختبار» وإن كان درسها
     * مقفلا، فيصل الطالب إلى MASTERY_LOCKED بعد النقرة لا قبلها. القرار
     * نفسه الذي تفحصه `start_attempt()` — الاستحقاق ثم `lesson_lock_state()`
     * — يقرأ هنا مرة واحدة لكل اختبار قادم، ويبقى المصدر واحدا.
     */
    function tq_s_exam_lock($uid, array $items)
    {
        $CI = get_instance();
        $CI->load->model('taqdar_repo_model');
        foreach ($items as &$q) {
            if (($q['state'] ?? '') !== 'upcoming') continue;
            $q['available']      = true;
            $q['lock_lesson_id'] = 0;
            $q['lock_title']     = '';
            try {
                if (!$CI->taqdar_repo_model->is_entitled($uid, (int) $q['course_id'])) {
                    $q['available'] = false;
                    continue;
                }
                $st = $CI->taqdar_repo_model->lesson_lock_state($uid, (int) $q['id']);
                if (!empty($st['found']) && empty($st['unlocked'])) {
                    $q['available']      = false;
                    $q['lock_lesson_id'] = (int) ($st['blocking_lesson_id'] ?? 0);
                    $q['lock_title']     = (string) ($st['blocking_lesson_title'] ?? '');
                }
            } catch (Throwable $e) {
                log_message('error', 'TQ-EXAM-LOCK: ' . $e->getMessage());
            }
        }
        unset($q);
        return $items;
    }
}

if (!function_exists('tq_s_assessment_quizzes')) {
    /**
     * اختبارات الدرس من طبقة الإتقان — `assessments(type='review')`.
     *
     * والحالة تشتق من `attempts`:
     *   لا محاولة              → قادم
     *   محاولة بلا `submitted_at` → جار
     *   آخر محاولة مسلمة        → منته بدرجته
     *
     * و**آخر محاولة لكل تقييم** لا كلها — السؤال «أين هو الآن؟»، وهي
     * القاعدة نفسها التي يعمل بها لوح النتائج المشترك.
     *
     * والاختبار بلا سؤال واحد لا يعرض: هو صف تقييم أنشئ عند فتح المحرر
     * ولم يؤلف بعد، وعرضه يعد الطالب باختبار لا يجده.
     */
    function tq_s_assessment_quizzes($uid)
    {
        $CI = get_instance();
        $uid = (int) $uid;
        if ($uid <= 0) return [];

        try {
            $rows = $CI->db->query(
                'SELECT a.`id` AS assessment_id, a.`pass_mark`,
                        l.`id` AS lesson_id, l.`title`, l.`course_id`,
                        c.`title` AS course_title, c.`level`, c.`category_id`,
                        (SELECT COUNT(*) FROM `question` q
                          WHERE q.`assessment_id` = a.`id`) AS marks
                   FROM `assessments` a
                   JOIN `lesson` l ON l.`id` = a.`lesson_id`
                   JOIN `course` c ON c.`id` = l.`course_id`
                   JOIN `enrol`  e ON e.`course_id` = l.`course_id` AND e.`user_id` = ?
                  WHERE a.`type` = "review" AND a.`lesson_id` > 0
                    AND COALESCE(l.`tq_status`, "published") = "published"
                 HAVING marks > 0', array($uid))->result_array();
        } catch (Throwable $e) {
            log_message('error', 'TQ-EXAM-SOURCE: ' . $e->getMessage());
            return [];
        }
        if (!$rows) return [];

        $aids = array_map('intval', array_column($rows, 'assessment_id'));

        /* آخر محاولة لكل تقييم — بالمعرف الأكبر، وهو ترتيب الإنشاء. */
        $last = [];
        try {
            foreach ($CI->db->query(
                'SELECT `id`, `assessment_id`, `score`, `passed`, `attempt_no`,
                        `started_at`, `submitted_at`
                   FROM `attempts`
                  WHERE `student_id` = ? AND `assessment_id` IN ('
                        . implode(',', $aids) . ')
                  ORDER BY `id` ASC', array($uid))->result_array() as $a) {
                $last[(int) $a['assessment_id']] = $a;   // الأحدث يغلب
            }
        } catch (Throwable $e) { $last = []; }

        $out = [];
        foreach ($rows as $i => $r) {
            $aid   = (int) $r['assessment_id'];
            $marks = (int) $r['marks'];
            $a     = isset($last[$aid]) ? $last[$aid] : null;

            $state = 'upcoming';
            if ($a !== null) $state = empty($a['submitted_at']) ? 'live' : 'done';

            $got = ($state === 'done') ? (float) $a['score'] : null;
            $pct = ($state === 'done' && $marks > 0)
                 ? (int) round($got * 100 / $marks) : null;

            $out[] = [
                'id'        => (int) $r['lesson_id'],
                'title'     => $r['title'],
                'course_id' => (int) $r['course_id'],
                'course'    => $r['course_title'],
                'subject'   => tq_s_subject($r['category_id'], $r['course_title'], (int) $r['course_id']),
                'level'     => $r['level'],
                'marks'     => $marks,
                'obtained'  => $got,
                'percent'   => $pct,
                /* اختيار من متعدد يصحح آليا، فالنتيجة تظهر فور التسليم —
                   ولا تنتظر اعتماد معلم كما ينتظر المقالي. */
                'visible'   => $state === 'done',
                'grade_state'  => $state === 'done' ? 'auto' : 'unsubmitted',
                'teacher_note' => '',
                'state'     => $state,
                'index'     => $i,
                'started_at' => $a !== null ? tq_s_ts($a['started_at'])   : 0,
                'ended_at'   => $a !== null ? tq_s_ts($a['submitted_at']) : 0,
            ];
        }
        return $out;
    }
}

if (!function_exists('tq_s_materials')) {
    /**
     * المواد التعليمية: ملفات الدروس (resource_files) ومرفقات الدروس،
     * في الكورسات المسجلة وحدها. الحجم من القرص إن كان الملف موجودا.
     */
    function tq_s_materials($uid)
    {
        static $cache = [];
        if (isset($cache[$uid])) return $cache[$uid];

        $CI = get_instance();
        if ($uid <= 0) return $cache[$uid] = [];

        $out = [];

        $CI->load->model('taqdar_student_model', 'tq_stu');
        $CI->load->model('taqdar_repo_model');

        /* TQ-MATERIAL-GATE — ثلاثة شروط كانت غائبة:
             • **درس منشور وحده**: ملف درس مسودة كان يعرض ويحمل قبل أن يعتمد.
             • **الرابط رابط الحارس** (`material_url()`) لا مسار `uploads/`.
             • **القفل يقال في الصف**: ملف درس لم يصل إليه الطالب يعرض مقفلا
               باسم الدرس الذي يفتحه — والحارس يرده إن طلب على أي حال. */
        $published = "COALESCE(l.tq_status, 'published') = 'published'";

        $locks   = [];
        $lock_of = static function ($lesson_id) use ($CI, $uid, &$locks) {
            $lesson_id = (int) $lesson_id;
            if (!isset($locks[$lesson_id])) {
                $s = $CI->taqdar_repo_model->lesson_lock_state($uid, $lesson_id);
                $locks[$lesson_id] = [
                    'locked' => empty($s['found']) || empty($s['unlocked']),
                    'hint'   => trim((string) ($s['blocking_lesson_title'] ?? '')),
                ];
            }
            return $locks[$lesson_id];
        };

        $files = $CI->db
            ->select('rf.id, rf.title, rf.file_name, rf.created_at, l.id AS lesson_id, l.title AS lesson_title,'
                   . ' c.id AS course_id, c.title AS course_title, c.category_id')
            ->from('resource_files rf')
            ->join('lesson l', 'l.id = rf.lesson_id', 'inner')
            ->join('course c', 'c.id = l.course_id', 'inner')
            ->join('enrol e', 'e.course_id = c.id', 'inner')
            ->where('e.user_id', $uid)
            ->where($published, null, false)
            ->get()->result_array();

        foreach ($files as $f) {
            $rel  = 'uploads/resource_files/' . $f['file_name'];
            $lock = $lock_of($f['lesson_id']);
            $out[] = [
                /* `fav_id` معرف الملف في `resource_files`، وهو ما يفضل.
                   ومرفق الدرس أدناه يتركه صفرا: لا صف له في جدول، فلا معرف
                   ثابت يفضل به — وقلب لا يعرف ما يحفظ لا يعرض. */
                'fav_id'    => (int) $f['id'],
                'src'       => 'file',
                'id'        => (int) $f['id'],
                'lesson_id' => (int) $f['lesson_id'],
                'course_id' => (int) $f['course_id'],
                'title'     => $f['title'] !== '' ? $f['title'] : $f['file_name'],
                'file'      => $f['file_name'],
                'lesson'    => $f['lesson_title'],
                'course'    => $f['course_title'],
                'subject'   => tq_s_subject($f['category_id'], $f['course_title'], $f['course_id']),
                'url'       => $CI->tq_stu->material_url('file', (int) $f['id']),
                'bytes'     => is_file(FCPATH . $rel) ? (int) filesize(FCPATH . $rel) : 0,
                'at'        => tq_s_ts($f['created_at']),
                'kind'      => tq_file_kind($f['file_name']),
                'locked'    => $lock['locked'],
                'lock_hint' => $lock['hint'],
            ];
        }

        $attached = $CI->db
            ->select('l.id, l.title, l.lesson_type, l.attachment, l.attachment_type, l.date_added,'
                   . ' c.id AS course_id, c.title AS course_title, c.category_id')
            ->from('lesson l')
            ->join('course c', 'c.id = l.course_id', 'inner')
            ->join('enrol e', 'e.course_id = c.id', 'inner')
            ->where('e.user_id', $uid)
            ->where('l.attachment !=', '')
            ->where($published, null, false)
            ->get()->result_array();

        foreach ($attached as $a) {
            /* إعدادات الاختبار ووسم الإطار ونص الدرس تسكن العمود نفسه —
               وليست ملفات. انظر `lesson_attachment_is_file()`. */
            if (!$CI->tq_stu->lesson_attachment_is_file($a)) continue;

            $is_link = (bool) preg_match('~^https?://~i', trim((string) $a['attachment']));
            $rel     = 'uploads/lesson_files/' . $a['attachment'];
            if (!$is_link && !is_file(FCPATH . $rel)) continue;   // اسم بلا ملف لا يعرض زر تحميل يرد 404

            $lock  = $lock_of($a['id']);
            $out[] = [
                'src'       => $is_link ? 'link' : 'lesson',
                'id'        => (int) $a['id'],
                'lesson_id' => (int) $a['id'],
                'course_id' => (int) $a['course_id'],
                'title'     => $a['title'],
                'file'      => $a['attachment'],
                'lesson'    => $a['title'],
                'course'    => $a['course_title'],
                'subject'   => tq_s_subject($a['category_id'], $a['course_title'], $a['course_id']),
                'url'       => $is_link ? $a['attachment'] : $CI->tq_stu->material_url('lesson', (int) $a['id']),
                'bytes'     => $is_link ? 0 : (int) filesize(FCPATH . $rel),
                'at'        => tq_s_ts($a['date_added']),
                'kind'      => tq_file_kind($a['attachment'], $a['attachment_type']),
                'locked'    => $lock['locked'],
                'lock_hint' => $lock['hint'],
            ];
        }

        usort($out, function ($x, $y) { return $y['at'] <=> $x['at']; });
        return $cache[$uid] = $out;
    }
}

if (!function_exists('tq_s_deadlines')) {
    /**
     * المواعيد القادمة: نهايات الوحدات المقيدة بتاريخ في الكورسات المسجلة.
     * وهو الموعد الوحيد الذي تعرفه القاعدة اليوم — ولا موعد سواه يعرض.
     */
    function tq_s_deadlines($uid, $limit = 5)
    {
        $CI = get_instance();
        if ($uid <= 0) return [];

        $rows = $CI->db
            ->select('s.id, s.title, s.end_date, s.course_id, c.title AS course_title, c.category_id')
            ->from('section s')
            ->join('enrol e', 'e.course_id = s.course_id', 'inner')
            ->join('course c', 'c.id = s.course_id', 'inner')
            ->where('e.user_id', $uid)
            ->where('s.end_date !=', '')
            ->get()->result_array();

        $out = [];
        foreach ($rows as $r) {
            $ts = tq_s_ts($r['end_date']);
            if ($ts <= 0 || $ts < strtotime('today')) continue;
            $out[] = [
                'title'   => $r['title'],
                'course'  => $r['course_title'],
                'subject' => tq_s_subject($r['category_id'], $r['course_title'], $r['course_id']),
                'course_id' => (int) $r['course_id'],
                'at'      => $ts,
            ];
        }
        usort($out, function ($a, $b) { return $a['at'] <=> $b['at']; });
        return array_slice($out, 0, $limit);
    }
}

if (!function_exists('tq_s_activity')) {
    /**
     * أرقام التقدم.
     * ساعات الدراسة من watched_duration، والدروس المكتملة من watch_histories،
     * ومتوسط الدرجات من quiz_results. والمقارنة بالأسبوع الماضي تحسب فقط حيث
     * يوجد طابع زمني للحدث — ولا تخمن حيث لا يوجد ('has_delta' => false).
     */
    function tq_s_activity($uid)
    {
        $CI  = get_instance();
        /* TQ-UNKNOWN-NOT-ZERO — «٠٪» عن غياب قياس تقرأ أداء ضعيفا لا غياب
           محاولة. و`has_score_source` يميز الحالين كما يميزهما `has_streak_source`
           المجاور — والقاعدة واحدة: الجلب الفاشل أو المصدر الفارغ لا يصير صفرا. */
        $out = [
            'seconds' => 0, 'lessons' => 0, 'score' => 0, 'streak' => 0,
            'score_delta' => null, 'has_streak_source' => false,
            'has_score_source' => false,
        ];
        if ($uid <= 0) return $out;

        $row = $CI->db->select('SUM(current_duration) AS s', false)
            ->from('watched_duration')->where('watched_student_id', $uid)->get()->row_array();
        $out['seconds'] = (int) ($row['s'] ?? 0);

        /* TQ-PROGRESS-ONE — «الدروس المكتملة» عدد ما يفتح الدرس التالي، من
           `course_state()` كبطاقات الكورسات لا من `watch_histories` الموروث. */
        foreach (tq_s_enrolled($uid) as $c) $out['lessons'] += (int) $c['done'];

        $quizzes = tq_s_quizzes($uid);
        $now  = time();
        $wk   = 7 * 86400;
        $this_week = [];
        $last_week = [];
        $all = [];
        foreach ($quizzes as $q) {
            if ($q['state'] !== 'done' || $q['marks'] <= 0) continue;
            $all[] = $q['percent'];
            $age = $now - $q['ended_at'];
            if ($q['ended_at'] > 0 && $age <= $wk)                    $this_week[] = $q['percent'];
            elseif ($q['ended_at'] > 0 && $age > $wk && $age <= 2 * $wk) $last_week[] = $q['percent'];
        }
        if ($all) {
            $out['score'] = (int) round(array_sum($all) / count($all));
            $out['has_score_source'] = true;
        }
        if ($this_week && $last_week) {
            $out['score_delta'] = (int) round(
                array_sum($this_week) / count($this_week) - array_sum($last_week) / count($last_week)
            );
        }

        /* TQ-STREAK-SOURCE — «ولا جدول له في القاعدة بعد» انتفت حجتها:
           `tq_activity_day` قائم ومعمور، ينشئه `Taqdar_learn_model::ensure_schema()`
           وتقرأه `streak()` وترد `has_source => true`. واللافتة أعلى الشاشة نفسها
           تقرأ منه («١ يوما متتاليا») بينما البطاقة الجانبية تحته تقول «—» وتعد
           بأن الرقم «يظهر عند تسجيل نشاطك اليومي» — وهو مسجل منذ أسابيع.
           فالعلاج إزالة نفي لمصدر قائم، لا اختراع رقم: الكيان واحد والمصدر واحد. */
        $CI = get_instance();
        $CI->load->model('taqdar_learn_model', 'tq_learn');
        $tq_st = $CI->tq_learn->streak($uid);
        $out['streak'] = (int) $tq_st['days'];
        $out['has_streak_source'] = !empty($tq_st['has_source']);

        return $out;
    }
}

if (!function_exists('tq_s_resume')) {
    /** آخر موضع بالضبط: الكورس الأحدث نشاطا والدرس الذي توقف عنده. */
    function tq_s_resume($uid)
    {
        $courses = tq_s_enrolled($uid);
        $best = null;
        foreach ($courses as $c) {
            if ($c['progress'] >= 100) continue;
            /* TQ-RESUME-TRUTH — «حيث توقفت» تقال عمن توقف: مقرر بلا سجل مشاهدة
               ولا موضع محفوظ لم يفتح قط، فلا يرشح لسطر الاستئناف. ولا يخفى
               المقرر: بطاقته باقية في «كورساتي»، وزر «الخطوة التالية» يبقى. */
            /* وصف `watch_histories` قد يوجد بلا تقدم: يكتب عند فتح الكورس مرة،
               فيفوز بسطر «واصل حيث توقفت» كورس صفر دروسه وصفر موضعه. فالشرط
               تقدم مقيس لا وجود صف. */
            if ((int) $c['done'] <= 0 && (int) ($c['position_sec'] ?? 0) <= 15
                && (int) ($c['watched_sec'] ?? 0) <= 15) continue;
            if ($best === null || $c['touched_at'] > $best['touched_at']) $best = $c;
        }
        return $best;
    }
}

if (!function_exists('tq_s_lesson_url')) {
    /** رابط تشغيل درس داخل بوابة تقدر. */
    function tq_s_lesson_url($course_id, $lesson_id = 0)
    {
        $p = 'student/lesson/' . (int) $course_id;
        if ((int) $lesson_id > 0) $p .= '/' . (int) $lesson_id;
        return base_url($p);
    }
}

if (!function_exists('tq_s_month')) {
    /** أسماء الشهور الميلادية كما تكتب في السوق السعودي. */
    function tq_s_month($m)
    {
        static $names = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
                         'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
        $i = ((int) $m) - 1;
        /* TQ-I18N — الترجمة عند الخروج: `static` لا يقبل نداء في تهيئته. */
        return isset($names[$i]) ? t($names[$i]) : '';
    }
}

if (!function_exists('tq_s_date')) {
    /** «20 مايو 2026» — الرقمان معزولان داخل الجملة. */
    function tq_s_date($ts)
    {
        $ts = (int) $ts;
        if ($ts <= 0) return '';
        return tq_iso(date('j', $ts) . ' ' . tq_s_month(date('n', $ts)) . ' ' . date('Y', $ts));
    }
}

if (!function_exists('tq_s_time')) {
    /** «10:00 ص» — الساعة وحدة واحدة لا رقمان. */
    function tq_s_time($ts)
    {
        $ts = (int) $ts;
        if ($ts <= 0) return '';
        $mer = (int) date('G', $ts) < 12 ? t('ص') : t('م');
        return tq_num(date('g:i', $ts)) . ' ' . $mer;
    }
}

if (!function_exists('tq_stamp')) {
    /**
     * TQ-STAMP-SEC — طابع من القاعدة يقرأ كما يقرأ الناس الوقت.
     *
     * `parent_links.consent_at` و أخواتها أعمدة `DATETIME`، وكانت تطبع
     * **كما هي**: «2026-06-10 23:32:33». والثواني في شاشة يقرؤها أب عن
     * موافقة ابنه لا تجيب سؤالا سأله أحد، وتجعل السطر أطول من أن يمسح
     * بالعين — وهي في بطاقة الابن تحت الزر مباشرة، فتقرأ رمزا تقنيا
     * تسرب إلى واجهة.
     *
     * والصيغة صيغة `tq_s_date()` نفسها فلا تفترق شاشتان في شهر واحد،
     * والوقت يبقى بلا ثوان: «10 يونيو 2026 · 11:32 م».
     *
     * @param string $sql      طابع القاعدة، أو أي نص يفهمه strtotime
     * @param bool   $withTime أيلحق الوقت؟ التاريخ وحده يكفي في القوائم
     */
    function tq_stamp($sql, $withTime = true)
    {
        $sql = trim((string) $sql);
        if ($sql === '' || $sql === '0000-00-00 00:00:00') return '';
        $ts = strtotime($sql);
        if ($ts === false || $ts <= 0) return '';

        $out = tq_s_date($ts);
        if ($withTime) $out .= ' · ' . tq_s_time($ts);
        return $out;
    }
}
