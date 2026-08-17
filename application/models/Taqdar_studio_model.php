<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * استوديو المحتوى — حالة الأصل، وحمايته، ومخرجاته المعتمدة.
 *
 * ثلاثة أشياء يجمعها أنها كلها عن **الدرس قبل أن يراه طالب**:
 *
 * ١ — **آلة حالة الأصل** (`B3.2`): `uploading → processed → in_review →
 *     published`، ومعها `rejected` بسبب مكتوب. وكانت الدروس تنشر بلا
 *     حالة أصلا: يرفع المعلم فيظهر، ولا موضع للمراجعة الفنية بينهما.
 *
 * ٢ — **حماية التشغيل** (`B3.3`). معيار قبول الوثيقة نصه: «لا رابط
 *     تشغيل دائم أبدا». والرابط هنا لا يخرج عاريا: يخرج **رمزا موقعا
 *     بـ HMAC** صالحا خمس دقائق، مقيدا بالدرس وبالطالب معا. ومن نسخ
 *     الرابط ووزعه وزع رمزا ينتهي قبل أن يفتح، ولا يعمل من حساب آخر
 *     أصلا لأن معرف الطالب داخل التوقيع.
 *
 *     **وحدوده تقال صراحة**: هذا يمنع النسخ العرضي والتوزيع بالرابط، ولا
 *     يمنع تسجيل الشاشة — ولا شيء يمنعه. والحماية التامة لملف يعرض وهم،
 *     والوعد بها أسوأ من عدمها. أما فيديو يوتيوب وفيميو فخارج هذا كله:
 *     مستضاف عند غيرنا برابط عام دائم بحكم تعريفه، ولذلك تعلمه هذه
 *     الطبقة `unprotected` صراحة بدل أن تدعي حمايته.
 *
 * ٣ — **المخرجات المولدة واعتمادها** (`F3.2`): ملخص، وخريطة مفاهيم،
 *     وبطاقات مراجعة، وأسئلة مقترحة. وشرط الوثيقة عليها واحد لا يخرق:
 *     **«لا نشر تلقائي — كل مخرج يمر باعتماد صريح»**. فكل مخرج يولد
 *     `draft`، ولا يصل طالبا حتى يضغط المعلم «اعتمد» عليه بعينه.
 *
 *     والمولد **قابل للاستبدال**: `generate()` تنادي مزودا خارجيا إن
 *     ضبط مفتاحه في `settings`، وإلا اشتقت مسودة من مادة الدرس نفسه
 *     (أهدافه ونصه). ومسودة مشتقة يعدلها المعلم في دقيقة خير من شاشة
 *     تقول «اربط مزودا ثم عد» — والاعتماد شرط في الحالين.
 */
class Taqdar_studio_model extends CI_Model
{
    /** حالات الأصل بالترتيب. والانتقال لا يقفز: لا نشر بلا مراجعة. */
    private static $FLOW = array(
        'uploading'  => array('label' => 'قيد الرفع',      'next' => array('processed', 'rejected')),
        'processed'  => array('label' => 'جاهز للمراجعة',  'next' => array('in_review', 'rejected')),
        'in_review'  => array('label' => 'قيد المراجعة',   'next' => array('published', 'rejected')),
        'published'  => array('label' => 'منشور',          'next' => array('rejected')),
        'rejected'   => array('label' => 'مرفوض',          'next' => array('processed')),
    );

    /** أنواع المخرجات المولدة. */
    private static $KINDS = array(
        'summary'     => 'ملخص الدرس',
        'concept_map' => 'خريطة المفاهيم',
        'flashcards'  => 'بطاقات المراجعة',
        'questions'   => 'أسئلة مقترحة',
    );

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    public static function flow()  { return self::$FLOW; }
    public static function kinds() { return self::$KINDS; }

    public static function state_label($k)
    {
        return isset(self::$FLOW[$k]) ? self::$FLOW[$k]['label'] : $k;
    }

    /* =====================================================================
       المخطط
       ===================================================================== */

    public function ensure_schema()
    {
        static $done = false;
        if ($done) return;
        $done = true;

        $this->db->query(
            'CREATE TABLE IF NOT EXISTS `tq_lesson_asset` (
               `id`          INT(11)     NOT NULL AUTO_INCREMENT,
               `lesson_id`   INT(11)     NOT NULL,
               `state`       VARCHAR(16) NOT NULL DEFAULT "uploading",
               `protection`  VARCHAR(16) NOT NULL DEFAULT "signed",
               `source`      VARCHAR(16) NOT NULL DEFAULT "file",
               `reason`      VARCHAR(250) NULL,
               `reviewed_by` INT(11)     NULL,
               `reviewed_at` DATETIME    NULL,
               `created_at`  DATETIME    NULL,
               `updated_at`  DATETIME    NULL,
               PRIMARY KEY (`id`),
               UNIQUE KEY `uq_lesson` (`lesson_id`),
               KEY `ix_state` (`state`)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $this->db->query(
            'CREATE TABLE IF NOT EXISTS `tq_lesson_output` (
               `id`          INT(11)     NOT NULL AUTO_INCREMENT,
               `lesson_id`   INT(11)     NOT NULL,
               `kind`        VARCHAR(24) NOT NULL,
               `body`        MEDIUMTEXT  NULL,
               `state`       VARCHAR(16) NOT NULL DEFAULT "draft",
               `engine`      VARCHAR(32) NOT NULL DEFAULT "derived",
               `reason`      VARCHAR(250) NULL,
               `approved_by` INT(11)     NULL,
               `approved_at` DATETIME    NULL,
               `created_at`  DATETIME    NULL,
               `updated_at`  DATETIME    NULL,
               PRIMARY KEY (`id`),
               UNIQUE KEY `uq_lesson_kind` (`lesson_id`,`kind`),
               KEY `ix_state` (`state`)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    private function now() { return date('Y-m-d H:i:s'); }

    private function safe($sql, $bind = array())
    {
        try { return $this->db->query($sql, $bind)->result_array(); }
        catch (Throwable $e) { log_message('error', 'TQ-STUDIO: ' . $e->getMessage()); return array(); }
    }

    /* =====================================================================
       آلة حالة الأصل
       ===================================================================== */

    /**
     * حالة أصل درس. ولا ترجع `null`: درس رفع قبل أن توجد هذه الطبقة
     * يعامل `published` — فالتاريخ لا يعاد كتابته، وفرض المراجعة بأثر
     * رجعي يطفئ محتوى المنصة كله في لحظة واحدة.
     */
    public function asset($lesson_id)
    {
        $this->ensure_schema();
        $lesson_id = (int) $lesson_id;

        $row = $this->db->where('lesson_id', $lesson_id)->get('tq_lesson_asset')->row_array();
        if ($row) {
            $row['label'] = self::state_label($row['state']);
            return $row;
        }

        $lesson = $this->db->select('video_type')->where('id', $lesson_id)->get('lesson')->row_array();

        return array(
            'lesson_id'  => $lesson_id,
            'state'      => 'published',
            'label'      => 'منشور',
            'protection' => $this->protection_for($lesson ? $lesson['video_type'] : ''),
            'source'     => $lesson ? (string) $lesson['video_type'] : 'file',
            'legacy'     => true,
        );
    }

    /**
     * نوع الحماية الممكن لمصدر بعينه.
     *
     * يوتيوب وفيميو `unprotected` — ولا يقال غير ذلك: نطاق آخر يستضيف
     * الملف ويعطيه رابطا عاما دائما، وادعاء حمايته يجعل المعلم يرفع عليه
     * ما لا يرفعه لو علم.
     */
    public function protection_for($video_type)
    {
        $t = strtolower((string) $video_type);
        if ($t === 'youtube' || $t === 'vimeo' || $t === 'html5_youtube') return 'unprotected';
        return 'signed';
    }

    /** يسجل أصلا جديدا عند الرفع. */
    public function register($lesson_id, $video_type = 'file')
    {
        $this->ensure_schema();
        $now = $this->now();

        $this->db->query(
            'INSERT INTO `tq_lesson_asset`
                (`lesson_id`,`state`,`protection`,`source`,`created_at`,`updated_at`)
             VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE `source` = VALUES(`source`),
                                     `protection` = VALUES(`protection`),
                                     `updated_at` = VALUES(`updated_at`)',
            array((int) $lesson_id, 'uploading', $this->protection_for($video_type),
                  (string) $video_type, $now, $now));

        return $this->asset($lesson_id);
    }

    /**
     * ينقل الأصل حالة. والانتقال يفحص: القفز من `uploading` إلى
     * `published` مباشرة هو بعينه ما تمنعه آلة الحالة — ولو سمح به
     * لصارت الآلة عمودا في جدول لا قاعدة.
     */
    public function transition($lesson_id, $to, $actor_id = null, $reason = '')
    {
        $this->ensure_schema();
        $lesson_id = (int) $lesson_id;

        if (!isset(self::$FLOW[$to])) {
            return array('ok' => false, 'message' => 'حالة غير معروفة.');
        }

        $cur = $this->asset($lesson_id);
        $from = $cur['state'];

        if ($from === $to) {
            return array('ok' => true, 'message' => 'الحالة كما هي.', 'state' => $to);
        }
        if (!in_array($to, self::$FLOW[$from]['next'], true)) {
            return array('ok' => false,
                'message' => 'لا ينتقل الدرس من «' . self::state_label($from) . '» إلى «'
                           . self::state_label($to) . '» مباشرة.');
        }
        if ($to === 'rejected' && trim((string) $reason) === '') {
            /* الرفض بسبب مكتوب — نص الوثيقة: «اعتماد أو رفض بسبب مكتوب».
               ورفض بلا سبب لا يعلم صاحبه ماذا يصلح فيعيد الرفع كما هو. */
            return array('ok' => false, 'message' => 'اكتب سبب الرفض — بلاه لا يعرف المعلم ماذا يصلح.');
        }

        $now = $this->now();
        $this->db->query(
            'INSERT INTO `tq_lesson_asset`
                (`lesson_id`,`state`,`protection`,`source`,`reason`,`reviewed_by`,`reviewed_at`,`created_at`,`updated_at`)
             VALUES (?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE `state` = VALUES(`state`), `reason` = VALUES(`reason`),
                                     `reviewed_by` = VALUES(`reviewed_by`),
                                     `reviewed_at` = VALUES(`reviewed_at`),
                                     `updated_at`  = VALUES(`updated_at`)',
            array($lesson_id, $to, $cur['protection'], $cur['source'],
                  trim((string) $reason) ?: null,
                  $actor_id ? (int) $actor_id : null, $now, $now, $now));

        try {
            $this->load->model('taqdar_repo_model', 'tq_repo');
            $this->tq_repo->audit($actor_id, 'asset.' . $to, 'lesson:' . $lesson_id,
                                  array('state' => $from), array('state' => $to, 'reason' => $reason));
        } catch (Throwable $e) {}

        return array('ok' => true, 'state' => $to,
                     'message' => 'صار الدرس «' . self::state_label($to) . '».');
    }

    /** هل يعرض هذا الدرس لطالب؟ */
    public function is_playable($lesson_id)
    {
        $a = $this->asset($lesson_id);
        return $a['state'] === 'published';
    }

    /* =====================================================================
       الرابط الموقع — B3.3
       ===================================================================== */

    /** سر التوقيع. من `settings` لا من الشيفرة، ويولد مرة عند أول طلب. */
    private function secret()
    {
        static $s = null;
        if ($s !== null) return $s;

        $row = $this->db->select('value')->where('key', 'tq_media_secret')
                        ->get('settings')->row_array();
        $s = $row ? (string) $row['value'] : '';

        if ($s === '') {
            $s = bin2hex(random_bytes(32));
            /* المفتاح يزرع في `settings` كما تزرع مفاتيح تاب — المستودع
               عام والنشر `git reset --hard`، فسر في ملف يضيع أو يتسرب. */
            $this->db->replace('settings', array('key' => 'tq_media_secret', 'value' => $s));
        }
        return $s;
    }

    public function ttl_seconds()
    {
        $row = $this->db->select('value')->where('key', 'tq_media_ttl_sec')
                        ->get('settings')->row_array();
        $v = $row ? (int) $row['value'] : 0;
        return ($v > 0 && $v <= 3600) ? $v : 300;   // خمس دقائق كما تنص الوثيقة
    }

    /**
     * رمز تشغيل موقع.
     *
     * التوقيع يشمل الدرس **والطالب** والانتهاء: فرمز نسخ من حساب لا يعمل
     * في حساب آخر، ورمز حفظ لا يعمل بعد خمس دقائق. و`hash_equals` عند
     * الفحص لا `===` — المقارنة النصية العادية تسرب طول التطابق بزمنها.
     */
    public function sign($lesson_id, $student_id)
    {
        $exp  = time() + $this->ttl_seconds();
        $body = (int) $lesson_id . ':' . (int) $student_id . ':' . $exp;
        $sig  = hash_hmac('sha256', $body, $this->secret());

        return array(
            'token'      => rtrim(strtr(base64_encode($body . ':' . $sig), '+/', '-_'), '='),
            'expires_at' => $exp,
            'ttl'        => $this->ttl_seconds(),
        );
    }

    /** يفحص رمزا. يرجع معرف الدرس عند الصحة، وصفرا عند أي خلل. */
    public function verify($token, $student_id)
    {
        $raw = base64_decode(strtr((string) $token, '-_', '+/'), true);
        if ($raw === false) return 0;

        $p = explode(':', $raw);
        if (count($p) !== 4) return 0;

        list($lesson_id, $uid, $exp, $sig) = $p;

        if ((int) $uid !== (int) $student_id) return 0;
        if ((int) $exp < time())              return 0;

        $expect = hash_hmac('sha256', $lesson_id . ':' . $uid . ':' . $exp, $this->secret());
        if (!hash_equals($expect, (string) $sig)) return 0;

        return (int) $lesson_id;
    }

    /* =====================================================================
       المخرجات المولدة واعتمادها — F3.2
       ===================================================================== */

    public function outputs($lesson_id)
    {
        $this->ensure_schema();
        $rows = $this->safe(
            'SELECT * FROM `tq_lesson_output` WHERE `lesson_id` = ? ORDER BY `kind` ASC',
            array((int) $lesson_id));

        $by = array();
        foreach ($rows as $r) {
            $r['label'] = isset(self::$KINDS[$r['kind']]) ? self::$KINDS[$r['kind']] : $r['kind'];
            $r['data']  = $r['body'] ? json_decode($r['body'], true) : null;
            $by[$r['kind']] = $r;
        }
        return $by;
    }

    /** المخرج المعتمد وحده — وهذا ما يقرؤه الطالب. */
    public function approved($lesson_id, $kind)
    {
        $this->ensure_schema();
        $row = $this->db->where('lesson_id', (int) $lesson_id)->where('kind', (string) $kind)
                        ->where('state', 'approved')->get('tq_lesson_output')->row_array();
        return $row ? json_decode($row['body'], true) : null;
    }

    /**
     * يولد المخرجات الأربعة **مسودات**.
     *
     * ولا ينشر شيئا: كل مخرج يكتب `draft`، ولا يصل طالبا حتى يعتمده
     * المعلم بعينه. هذا شرط `F3.2` نصا، وهو أيضا الصواب — النص المولد
     * يخطئ، وخطأ في ملخص درس يقرؤه مئة طالب أثقل من خطأ في مسودة.
     *
     * والتوليد لا يمس ما اعتمد: من اعتمد ملخصا ثم أعاد التوليد لا يفقد
     * اعتماده. وإعادة توليد المعتمد صراحة `regenerate_approved`.
     */
    public function generate($lesson_id, $actor_id = null, $only = null)
    {
        $this->ensure_schema();
        $lesson_id = (int) $lesson_id;

        $lesson = $this->db->where('id', $lesson_id)->get('lesson')->row_array();
        if (!$lesson) return array('ok' => false, 'message' => 'لا درس بهذا الرقم.');

        $objectives = $this->safe(
            'SELECT `id`,`text`,`at_second` FROM `objectives`
              WHERE `lesson_id` = ? ORDER BY `at_second` ASC, `id` ASC', array($lesson_id));

        $cues = $this->safe(
            'SELECT `at_second`,`text` FROM `tq_transcript`
              WHERE `lesson_id` = ? ORDER BY `at_second` ASC', array($lesson_id));

        if (!$objectives && !$cues) {
            return array('ok' => false,
                'message' => 'لا مادة يولد منها بعد: أضف أهداف الدرس أو ارفع نصه أولا.');
        }

        $kinds = $only ? array_intersect(array_keys(self::$KINDS), (array) $only)
                       : array_keys(self::$KINDS);

        $made = 0; $kept = 0;
        foreach ($kinds as $kind) {
            $cur = $this->db->where('lesson_id', $lesson_id)->where('kind', $kind)
                            ->get('tq_lesson_output')->row_array();

            if ($cur && $cur['state'] === 'approved') { $kept++; continue; }

            $out = $this->produce($kind, $lesson, $objectives, $cues);
            if ($out === null) continue;

            $now = $this->now();
            $this->db->query(
                'INSERT INTO `tq_lesson_output`
                    (`lesson_id`,`kind`,`body`,`state`,`engine`,`created_at`,`updated_at`)
                 VALUES (?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE `body` = VALUES(`body`), `state` = "draft",
                                         `engine` = VALUES(`engine`), `updated_at` = VALUES(`updated_at`)',
                array($lesson_id, $kind, json_encode($out['data'], JSON_UNESCAPED_UNICODE),
                      'draft', $out['engine'], $now, $now));
            $made++;
        }

        try {
            $this->load->model('taqdar_repo_model', 'tq_repo');
            $this->tq_repo->audit($actor_id, 'studio.generate', 'lesson:' . $lesson_id, null,
                                  array('made' => $made, 'kept_approved' => $kept));
        } catch (Throwable $e) {}

        $msg = 'ولد ' . $made . ' مخرجا كمسودة.';
        if ($kept) $msg .= ' و' . $kept . ' معتمدا لم يمس.';
        $msg .= ' راجع كل واحد واعتمده — لا ينشر شيء قبل اعتمادك.';

        return array('ok' => $made > 0 || $kept > 0, 'made' => $made, 'kept' => $kept, 'message' => $msg);
    }

    /**
     * يصنع مخرجا واحدا.
     *
     * المزود الخارجي أولا إن ضبط، وإلا الاشتقاق من مادة الدرس. والحد
     * بينهما هنا وحده — فمن ربط مزودا غدا لا يعيد كتابة شيء مما فوق.
     */
    private function produce($kind, $lesson, $objectives, $cues)
    {
        $ai = $this->ai_draft($kind, $lesson, $objectives, $cues);
        if ($ai !== null) return array('data' => $ai, 'engine' => 'provider');

        $d = $this->derive($kind, $lesson, $objectives, $cues);
        return $d === null ? null : array('data' => $d, 'engine' => 'derived');
    }

    /**
     * نقطة وصل المزود الخارجي.
     *
     * ترجع `null` ما لم يضبط `tq_ai_provider` **و** مفتاحه في `settings`
     * — كما يفعل `Taqdar_tap_model::ready()` تماما: بلا مفاتيح لا شيء
     * يتغير، ولا شاشة تعد بما لا يقع.
     *
     * ومن يصل مزودا يكتب النداء هنا وحده: التوقيع ثابت، والمناداة من
     * `produce()` لا تتغير.
     */
    private function ai_draft($kind, $lesson, $objectives, $cues)
    {
        $row = $this->db->select('value')->where('key', 'tq_ai_provider')->get('settings')->row_array();
        $provider = $row ? trim((string) $row['value']) : '';
        if ($provider === '') return null;

        $row = $this->db->select('value')->where('key', 'tq_ai_key')->get('settings')->row_array();
        $key = $row ? trim((string) $row['value']) : '';
        if ($key === '') return null;

        // لا مزود موصول بعد. والاشتقاق يتكفل حتى يوصل.
        log_message('debug', 'TQ-STUDIO: مزود مضبوط ولا نداء مركب بعد — يشتق.');
        return null;
    }

    /**
     * مسودة مشتقة من مادة الدرس نفسه.
     *
     * ليست ذكاء وليست تدعيه: تعيد ترتيب ما كتبه المعلم (أهدافه ونصه)
     * في الشكل الذي يحتاجه كل مخرج، فيصير أمامه شيء يعدله بدل صفحة
     * بيضاء. وهذا هو الفرق العملي كله في استوديو محتوى.
     */
    private function derive($kind, $lesson, $objectives, $cues)
    {
        if ($kind === 'summary') {
            $lines = array();
            foreach ($objectives as $o) $lines[] = $o['text'];

            if (!$lines && $cues) {
                /* بلا أهداف: أول جملة من كل خمس مقاطع — عينة تمثل الدرس
                   طولا، لا أول خمسة أسطر وحدها. */
                for ($i = 0; $i < count($cues); $i += 5) {
                    $t = trim($cues[$i]['text']);
                    if ($t !== '') $lines[] = mb_substr($t, 0, 160);
                    if (count($lines) >= 8) break;
                }
            }
            if (!$lines) return null;

            return array(
                'title' => 'ملخص ' . $lesson['title'],
                'points' => array_slice($lines, 0, 10),
                'note' => 'مسودة مشتقة من أهداف الدرس ونصه. عدلها ثم اعتمدها.',
            );
        }

        if ($kind === 'concept_map') {
            if (!$objectives) return null;
            $nodes = array();
            foreach ($objectives as $i => $o) {
                $nodes[] = array(
                    'id'    => (int) $o['id'],
                    'text'  => $o['text'],
                    'at'    => (int) $o['at_second'],
                    'order' => $i + 1,
                );
            }
            /* السلسلة الزمنية هي العلاقة الوحيدة التي نعرفها يقينا:
               الهدف الذي يشرح قبل غيره يمهد له. وعلاقة أعمق من ذلك
               تخمين، والتخمين في خريطة مفاهيم يعلم خطأ. */
            return array(
                'nodes' => $nodes,
                'edges' => array_map(function ($i) use ($nodes) {
                    return array('from' => $nodes[$i]['id'], 'to' => $nodes[$i + 1]['id'], 'kind' => 'يمهد لـ');
                }, range(0, max(0, count($nodes) - 2))),
                'note' => 'الروابط مشتقة من ترتيب الشرح. أعد ترتيبها بما يناسب المادة ثم اعتمدها.',
            );
        }

        if ($kind === 'flashcards') {
            if (!$objectives) return null;
            $cards = array();
            foreach ($objectives as $o) {
                $cards[] = array(
                    'front' => 'ما المقصود بـ: ' . $o['text'] . '؟',
                    'back'  => '',       // يملؤه المعلم — ولا يخترع له جواب
                    'at'    => (int) $o['at_second'],
                    'objective_id' => (int) $o['id'],
                );
            }
            return array('cards' => $cards,
                'note' => 'وجه البطاقة مشتق من الهدف، وظهرها لك: اكتب الجواب المختصر ثم اعتمدها.');
        }

        if ($kind === 'questions') {
            if (!$objectives) return null;
            $qs = array();
            foreach ($objectives as $o) {
                /* كل سؤال مربوط بهدفه — `B2.2` يشترطه إلزاميا، و`F3.3`
                   يعيده: «لا سؤال يحفظ بدون objective_id». فالمقترح يولد
                   موصولا من أول لحظة، لا يوصل بعد الحفظ. */
                $qs[] = array(
                    'objective_id' => (int) $o['id'],
                    'objective'    => $o['text'],
                    'at'           => (int) $o['at_second'],
                    'title'        => '',
                    'options'      => array('', '', '', ''),
                    'correct'      => null,
                );
            }
            return array('questions' => $qs,
                'note' => 'هيكل سؤال لكل هدف، مربوط به. اكتب نصه وخياراته ثم اعتمده — ولا يحفظ سؤال بلا هدف.');
        }

        return null;
    }

    /** يحفظ تعديل المعلم على مخرج. والتعديل يرجعه مسودة إن كان معتمدا. */
    public function save_output($lesson_id, $kind, $data, $actor_id = null)
    {
        $this->ensure_schema();
        if (!isset(self::$KINDS[$kind])) return array('ok' => false, 'message' => 'نوع مخرج غير معروف.');

        $now = $this->now();
        $this->db->query(
            'INSERT INTO `tq_lesson_output`
                (`lesson_id`,`kind`,`body`,`state`,`engine`,`created_at`,`updated_at`)
             VALUES (?,?,?,"draft","teacher",?,?)
             ON DUPLICATE KEY UPDATE `body` = VALUES(`body`), `state` = "draft",
                                     `engine` = "teacher",
                                     `approved_by` = NULL, `approved_at` = NULL,
                                     `updated_at` = VALUES(`updated_at`)',
            array((int) $lesson_id, $kind, json_encode($data, JSON_UNESCAPED_UNICODE), $now, $now));

        return array('ok' => true, 'message' => 'حفظ التعديل مسودة. اعتمده ليصل الطالب.');
    }

    /**
     * الاعتماد الصريح — وهو الباب الوحيد الذي يصل مخرج منه إلى طالب.
     */
    public function approve($lesson_id, $kind, $actor_id)
    {
        $this->ensure_schema();
        $row = $this->db->where('lesson_id', (int) $lesson_id)->where('kind', (string) $kind)
                        ->get('tq_lesson_output')->row_array();

        if (!$row) return array('ok' => false, 'message' => 'لا مخرج بهذا النوع لهذا الدرس.');
        if ($row['state'] === 'approved') return array('ok' => true, 'message' => 'معتمد أصلا.');

        $data = $row['body'] ? json_decode($row['body'], true) : null;
        $bad  = $this->incomplete($kind, $data);
        if ($bad !== '') {
            /* الاعتماد يفحص التمام: مخرج نصفه فارغ يعتمد فيصل الطالب
               بطاقة بلا جواب أو سؤال بلا نص. والرفض هنا يقول ما ينقص
               بالضبط لا «غير مكتمل». */
            return array('ok' => false, 'message' => $bad);
        }

        $this->db->where('id', (int) $row['id'])->update('tq_lesson_output', array(
            'state' => 'approved', 'approved_by' => (int) $actor_id,
            'approved_at' => $this->now(), 'updated_at' => $this->now(), 'reason' => null));

        try {
            $this->load->model('taqdar_repo_model', 'tq_repo');
            $this->tq_repo->audit($actor_id, 'studio.approve', 'lesson:' . (int) $lesson_id, null,
                                  array('kind' => $kind));
        } catch (Throwable $e) {}

        return array('ok' => true,
                     'message' => 'اعتمد «' . self::$KINDS[$kind] . '»، وصار يعرض للطالب.');
    }

    public function reject_output($lesson_id, $kind, $actor_id, $reason = '')
    {
        $this->ensure_schema();
        $this->db->where('lesson_id', (int) $lesson_id)->where('kind', (string) $kind)
                 ->update('tq_lesson_output', array(
                     'state' => 'draft', 'approved_by' => null, 'approved_at' => null,
                     'reason' => mb_substr(trim((string) $reason), 0, 250) ?: null,
                     'updated_at' => $this->now()));

        return array('ok' => true, 'message' => 'أعيد المخرج مسودة، ولم يعد يعرض للطالب.');
    }

    /** ما ينقص مخرجا قبل اعتماده — نصا يقرؤه صاحبه لا رمزا. */
    private function incomplete($kind, $data)
    {
        if (!is_array($data)) return 'المخرج فارغ. ولده أو اكتبه قبل اعتماده.';

        if ($kind === 'summary') {
            $pts = isset($data['points']) ? array_filter((array) $data['points'], 'trim') : array();
            return $pts ? '' : 'الملخص بلا نقاط. اكتب نقطة واحدة على الأقل.';
        }
        if ($kind === 'flashcards') {
            foreach ((array) (isset($data['cards']) ? $data['cards'] : array()) as $i => $c) {
                if (trim((string) (isset($c['back']) ? $c['back'] : '')) === '') {
                    return 'البطاقة رقم ' . ($i + 1) . ' بلا جواب. اكتب ظهرها قبل الاعتماد.';
                }
            }
            return '';
        }
        if ($kind === 'questions') {
            foreach ((array) (isset($data['questions']) ? $data['questions'] : array()) as $i => $q) {
                if (trim((string) (isset($q['title']) ? $q['title'] : '')) === '') {
                    return 'السؤال رقم ' . ($i + 1) . ' بلا نص.';
                }
                if (empty($q['objective_id'])) {
                    return 'السؤال رقم ' . ($i + 1) . ' بلا هدف مرتبط — ولا يحفظ سؤال بلا هدف.';
                }
            }
            return '';
        }
        return '';
    }

    /* =====================================================================
       طابور المراجعة — ما ينتظر الإدارة
       ===================================================================== */

    public function review_queue($limit = 60)
    {
        $this->ensure_schema();
        return $this->safe(
            'SELECT a.`lesson_id`, a.`state`, a.`updated_at`,
                    l.`title` AS lesson_title, c.`id` AS course_id, c.`title` AS course_title,
                    TRIM(CONCAT(COALESCE(u.`first_name`,""), " ", COALESCE(u.`last_name`,""))) AS teacher
               FROM `tq_lesson_asset` a
               JOIN `lesson` l ON l.`id` = a.`lesson_id`
               JOIN `course` c ON c.`id` = l.`course_id`
          LEFT JOIN `users`  u ON u.`id` = c.`creator`
              WHERE a.`state` = "in_review"
              ORDER BY a.`updated_at` ASC
              LIMIT ' . max(1, (int) $limit));
    }

    public function counts()
    {
        $this->ensure_schema();
        $out = array('uploading' => 0, 'processed' => 0, 'in_review' => 0, 'published' => 0, 'rejected' => 0);
        foreach ($this->safe('SELECT `state`, COUNT(*) n FROM `tq_lesson_asset` GROUP BY `state`') as $r) {
            $out[$r['state']] = (int) $r['n'];
        }
        return $out;
    }
}
