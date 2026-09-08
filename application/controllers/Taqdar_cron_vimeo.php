<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * مزامنة فيديوهات فيميو — أداة سطر أوامر.
 *
 * تمرّ على مجلّدات الحساب كلها، وتقول أيُّ فيديو موجود على المنصّة سلفًا وأيُّه
 * جديد، وتُنشئ الجديد **مسودّةً** في موضعه.
 *
 * وثلاث قواعد تحكمها:
 *
 * ١ — **البصمة حاملة.** الرابط يُخزَّن `https://vimeo.com/{id}/{hash}`، وبدون
 *     البصمة يرفض `X-Frame-Options` التضمين فيفتح الطالب درسًا فارغًا
 *     (`TQ-VIMEO-HASH` في `assets/taqdar/js/tq-player.js:174`). فمقطعٌ غير
 *     مدرج بلا بصمة في رابطه **لا يُستورد**، ولا تُلفَّق له بصمة.
 *
 * ٢ — **لا تخمين في الموضع.** درسٌ في غير مقرّره يظهر للطلّاب في الحال،
 *     ويُنقص نسبة إتمام كلّ مسجَّل (`Taqdar_repo_model:616` تحسبها حيّة).
 *     فالربط من `scripts/vimeo/folders.json` وحده، والأداة **ترفض** مجلّدًا
 *     لا سطر له فيه.
 *
 * ٣ — **الإنشاء من الباب الواحد**: `Taqdar_curriculum_model::save_lesson()`
 *     — تتحقّق من الملكيّة، وتُلزم القسم بمقرّره، وتسند الترتيب، وتكتب في
 *     سجلّ التدقيق. ولا `INSERT` خامًا في هذا الملفّ.
 *
 * الأفعال:
 *   doctor                 فحص الإعداد والرمز وصلاحياته
 *   folders                سرد المجلّدات كما يراها الرمز
 *   scan                   الفارق: على المنصّة سلفًا · جديد · شواذّ
 *   plan [folder_id]       الموضع المقترح وعواقبه — معاينة
 *   import [folder] [apply] الإنشاء — معاينة ما لم تكتب apply
 *   verify                 فحصٌ بعديّ
 */
class Taqdar_cron_vimeo extends CI_Controller
{
    const MAP      = 'scripts/vimeo/folders.json';
    const MAX_SEC  = 28800;   /* سقف `record_duration()` — ثماني ساعات */
    const MIN_TITLE = 3;

    private $api = null;

    public function __construct()
    {
        parent::__construct();
        if (!$this->input->is_cli_request()) show_404();
        /* القاعدة ليست في الأوتولود لسطر الأوامر. */
        $this->load->database();
        $this->load->model('taqdar_curriculum_model', 'curr');
    }

    public function index() { $this->doctor(); }

    /* =====================================================================
       الإعداد
       ===================================================================== */

    public function doctor()
    {
        echo "مزامن فيميو — فحص الإعداد\n" . str_repeat('─', 58) . "\n";

        $cfg = $this->cfg();
        if ($cfg === null) {
            echo "✗ لا ملفّ إعداد: application/config/taqdar_vimeo.php\n"
               . "  انسخ القالب واملأه برمز وصول شخصيّ:\n"
               . "      cp application/config/taqdar_vimeo.php.example \\\n"
               . "         application/config/taqdar_vimeo.php\n"
               . "  والرمز يُولَّد من developer.vimeo.com/apps ← Personal Access Token،\n"
               . "  وتُؤشَّر فيه صلاحيّة private.\n";
            return false;
        }
        echo "  ✓ ملفّ الإعداد   application/config/taqdar_vimeo.php\n";

        $api = $this->api();
        if (!$api->has_token()) { echo "  ✗ الرمز فارغ في الملفّ.\n"; return false; }

        $p = $api->probe();
        if ($p === null) { echo '  ✗ ' . $api->error() . "\n"; return false; }

        echo '  ✓ الرمز          ' . $p['len'] . " محرفًا\n";
        echo '  ✓ الحساب         ' . ($p['name'] !== '' ? $p['name'] : '—')
           . '  (' . $p['uri'] . ")\n";
        echo '  ' . ($p['private'] ? '✓' : '✗') . ' الصلاحيات      '
           . implode('، ', $p['scopes']) . "\n";

        if (!$p['private']) {
            echo "\n✗ الرمز بلا صلاحيّة private.\n"
               . "  منحة client_credentials لا تُعطي إلّا public، وهي لا تسرد\n"
               . "  مجلّدات الحساب ولا مقاطعه غير المدرجة — فتنجح النداءات\n"
               . "  وتعود بقوائم فارغة، وهو أسوأ من خطأ ظاهر.\n";
            return false;
        }

        $pr = $api->projects();
        if ($pr === null) { echo '  ✗ ' . $api->error() . "\n"; return false; }
        echo '  ✓ المجلّدات       ' . count($pr) . " مجلّدًا\n";

        $map = $this->map();
        echo '  ' . ($map === null ? '✗' : '✓') . ' ملفّ الربط      '
           . ($map === null ? 'مفقود — ' . self::MAP : count($map) . ' سطرًا') . "\n";
        return true;
    }

    /* =====================================================================
       السرد
       ===================================================================== */

    public function folders()
    {
        if (!$this->doctor()) return;
        $api = $this->api();

        echo "\nمجلّدات فيميو\n" . str_repeat('─', 58) . "\n";
        $tree = $this->walk($api, $nested);
        if ($tree === null) { echo '✗ ' . $api->error() . "\n"; return; }

        $tot = 0;
        foreach ($tree as $f) {
            $tot += count($f['videos']);
            echo '  #' . str_pad($f['id'], 11) . ' ' . $this->pad($f['name'], 36)
               . str_pad((string) count($f['videos']), 4, ' ', STR_PAD_LEFT) . " فيديو\n";
        }

        $all = $api->all_videos();
        if (is_array($all)) {
            $in = array();
            foreach ($tree as $f) foreach ($f['videos'] as $v) $in[$v['id']] = true;
            $loose = 0;
            foreach ($all as $v) { $r = $this->vref_api($v); if ($r && !isset($in[$r['id']])) $loose++; }
            echo '  ' . $this->pad('خارج كلّ مجلّد', 49) . str_pad((string) $loose, 4, ' ', STR_PAD_LEFT) . " فيديو\n";
        }

        echo str_repeat('─', 58) . "\n";
        echo '  المجموع في المجلّدات: ' . $tot . "\n";
        if (!$nested) {
            echo "  ⚠ التصفّح المتداخل غير متاح — المجلّدات الفرعية لم تُقرأ.\n";
        }
    }

    /* =====================================================================
       الفارق
       ===================================================================== */

    public function scan()
    {
        if (!$this->doctor()) return;
        $api  = $this->api();
        $tree = $this->walk($api, $nested);
        if ($tree === null) { echo '✗ ' . $api->error() . "\n"; return; }

        $have = $this->platform_index();
        $map  = $this->map() ?: array();

        $n = array('have' => 0, 'new' => 0, 'placed' => 0, 'nohash' => 0, 'unmapped' => 0);

        echo "\nمسح فيميو ↔ المنصّة\n" . str_repeat('─', 58) . "\n";
        foreach ($tree as $f) {
            $dest = isset($map[$f['id']]) ? $map[$f['id']] : null;
            echo "\nمجلّد: " . $f['name'] . '  (#' . $f['id'] . ') → '
               . ($dest ? 'المقرَّر ' . $dest['course_id'] : '✗ لا موضع له في ' . self::MAP) . "\n";

            foreach ($f['videos'] as $v) {
                if (isset($have[$v['id']])) {
                    $n['have']++;
                    $l = $have[$v['id']][0];
                    echo '  ✓ ' . $this->pad($v['name'], 40) . '← الدرس ' . $l['id'] . "\n";
                } elseif ($v['hash'] === '' && $v['privacy'] === 'unlisted') {
                    $n['nohash']++;
                    echo '  ✗ ' . $this->pad($v['name'], 40) . "بلا بصمة — لا يُستورد\n";
                } elseif (!$dest) {
                    $n['unmapped']++; $n['new']++;
                    echo '  ? ' . $this->pad($v['name'], 40) . "جديد، بلا موضع\n";
                } else {
                    $n['new']++; $n['placed']++;
                    echo '  + ' . $this->pad($v['name'], 40) . "جديد\n";
                }
            }
        }

        $odd = $this->odd_rows();
        echo "\n" . str_repeat('─', 58) . "\n";
        echo '  على المنصّة سلفًا   ' . $n['have'] . "\n";
        echo '  جديد وله موضع      ' . $n['placed'] . "\n";
        echo '  جديد بلا موضع      ' . $n['unmapped'] . "\n";
        echo '  بلا بصمة           ' . $n['nohash'] . "\n";
        echo '  شواذّ على المنصّة   ' . count($odd) . "\n";
        foreach ($odd as $o) echo '     الدرس ' . $o['id'] . ' — ' . $o['video_url'] . "\n";
        echo "\nمعاينة — لا شيء كُتب. التالي:  plan\n";
    }

    /* =====================================================================
       الخطّة والاستيراد
       ===================================================================== */

    public function plan($folder = '') { $this->run($folder, false); }

    public function import($folder = '', $apply = '')
    {
        /* `import apply` بلا مجلّد: الوسيط الأول يحمل الكلمة. */
        if ($folder === 'apply') { $folder = ''; $apply = 'apply'; }
        $this->run($folder, $apply === 'apply');
    }

    private function run($folder, $write)
    {
        if (!$this->doctor()) return;
        $api  = $this->api();
        $tree = $this->walk($api, $nested);
        if ($tree === null) { echo '✗ ' . $api->error() . "\n"; return; }

        $map = $this->map();
        if ($map === null) { echo "\n✗ لا ملفّ ربط: " . self::MAP . "\n"; return; }

        /* الفهرس يُعاد قراءته هنا لا يُؤخذ من مسح سابق: كاشٌ بائت يعيد
           إنشاء درس حُذف قبل دقائق. */
        $have = $this->platform_index();
        $made = 0; $skipped = 0; $refused = array();

        echo "\n" . ($write ? 'الاستيراد' : 'خطّة الاستيراد') . "\n" . str_repeat('─', 58) . "\n";

        foreach ($tree as $f) {
            if ($folder !== '' && (string) $f['id'] !== (string) $folder) continue;

            $dest = isset($map[$f['id']]) ? $map[$f['id']] : null;
            if (!$dest) { $refused[] = 'مجلّد «' . $f['name'] . '» — لا سطر له في الربط'; continue; }

            $place = $this->resolve($dest);
            if (isset($place['error'])) { $refused[] = 'مجلّد «' . $f['name'] . '» — ' . $place['error']; continue; }

            /* بترتيب الرفع لا بترتيب فيميو: فيميو يردّ الأحدث أولا،
               والترتيب هنا يصير ترتيب الدروس — وبوابة الإتقان تقفل الدرس
               التالي على سابقه، فمعكوسٌ يعني درسا يسبق تمهيده. */
            $vs = $f['videos'];
            usort($vs, function ($a, $b) {
                $c = strcmp((string) $a['created'], (string) $b['created']);
                return $c !== 0 ? $c : strcmp((string) $a['id'], (string) $b['id']);
            });

            $todo = array();
            foreach ($vs as $v) {
                if (isset($have[$v['id']]))                       { $skipped++; continue; }
                if ($v['hash'] === '' && $v['privacy'] === 'unlisted') continue;
                if ($v['sec'] <= 0 || $v['sec'] > self::MAX_SEC)  { continue; }
                if (mb_strlen(trim($v['name'])) < self::MIN_TITLE) { continue; }
                $todo[] = $v;
            }
            if (!$todo) continue;

            echo "\nالمقرَّر " . $place['course_id'] . '  ' . $place['course']
               . '   القسم ' . $place['section_id'] . "\n";

            $done = $this->finishers($place['course_id']);
            if ($done > 0) {
                $tot = $this->lesson_count($place['course_id']);
                $pct = (int) floor($tot * 100 / max(1, $tot + count($todo)));
                echo '  ⚠ فيه ' . $done . ' طالبًا أتمّوه — إضافة ' . count($todo)
                   . ' درسًا تُنزل نسبتهم إلى ' . $pct . "%\n";
            }

            foreach ($todo as $v) {
                $title = $this->title_for($dest, $v);
                if ($title === null) {
                    echo '  ✗ ' . $this->pad($v['name'], 40) . "لا عنوان له في الربط\n";
                    continue;
                }
                if (!$write) {
                    echo '  + ' . $this->pad($title, 40) . $this->hms($v['sec']) . "   مسودّة\n";
                    continue;
                }
                $id = $this->create($place, $v, $title);
                if (is_array($id)) { echo '  ✗ ' . $this->pad($title, 40) . $id['error'] . "\n"; }
                else { $made++; echo '  ✓ #' . str_pad((string) $id, 5) . $this->pad($title, 38)
                                    . $this->hms($v['sec']) . "   مسودّة\n"; }
            }
        }

        if ($refused) {
            echo "\n✗ مرفوض:\n";
            foreach ($refused as $r) echo '  ' . $r . "\n";
        }

        echo "\n";
        if ($write) {
            echo '✓ أُنشئ ' . $made . " درسًا · تُخطّي " . $skipped . " موجودًا سلفًا\n";
            echo "  وكلّها **مسودّات**: تُنشر من لوحة الإدارة بعد المعاينة.\n";
        } else {
            echo "معاينة — أضف apply للتنفيذ.\n";
        }
    }

    /** الإنشاء من الباب الواحد. */
    private function create($place, $v, $title)
    {
        $post = array(
            'course_id'  => (int) $place['course_id'],
            'section_id' => (int) $place['section_id'],
            'title'      => mb_substr(trim($title), 0, 190),
            'tq_kind'    => 'vimeo',
            'video_url'  => $v['link'],
            'duration'   => (int) $v['sec'],   /* عددٌ مجرّد يُقرأ ثوانيَ */
            'action'     => 'draft',
            'summary'    => '',
            'is_free'    => 0,
        );
        /* عقد `save_lesson()`: `['ok'=>true,'id'=>N]` أو `['ok'=>false,'message'=>…]`
           — و`fail()` تجمع الأخطاء في `errors` وتضمّها في `message`. */
        $r = $this->curr->save_lesson($this->actor(), 0, $post);
        if (!is_array($r) || empty($r['ok'])) {
            $m = (is_array($r) && isset($r['message'])) ? (string) $r['message'] : 'رفضٌ بلا سبب';
            return array('error' => $m);
        }
        return (int) $r['id'];
    }

    /* =====================================================================
       الفحص البعديّ
       ===================================================================== */

    public function verify()
    {
        echo "فحص بعد الاستيراد\n" . str_repeat('─', 58) . "\n";

        $rows = $this->db->query(
            "SELECT id, course_id, video_url, duration, duration_sec, tq_status,
                    video_type, attachment_type
               FROM `lesson` WHERE video_url LIKE '%vimeo.com/%'")->result_array();

        $nohash = 0; $nodur = 0; $draft = 0; $ids = array(); $dup = 0;
        foreach ($rows as $r) {
            $v = $this->vref($r['video_url']);
            if (!$v || $v['hash'] === '') $nohash++;
            if ((int) $r['duration_sec'] <= 0) $nodur++;
            if ($r['tq_status'] === 'draft') $draft++;
            if ($v) { if (isset($ids[$v['id']])) $dup++; $ids[$v['id']] = true; }
        }

        $this->ck('دروس فيميو', count($rows), true);
        $this->ck('لها بصمة في رابطها', (count($rows) - $nohash) . ' من ' . count($rows), $nohash === 0);
        $this->ck('مدّتها > صفر', (count($rows) - $nodur) . ' من ' . count($rows), $nodur === 0);
        $this->ck('معرّف مكرَّر بين درسين', $dup, $dup === 0);
        $this->ck('مسودّات تنتظر النشر', $draft, true);

        $odd = $this->odd_rows();
        $this->ck('شواذّ (نوعه vimeo ورابطه سواه)', count($odd), count($odd) === 0);
        foreach ($odd as $o) echo '      الدرس ' . $o['id'] . ' — ' . $o['video_url'] . "\n";
    }

    /* =====================================================================
       الداخل
       ===================================================================== */

    private function cfg()
    {
        $p = APPPATH . 'config/taqdar_vimeo.php';
        if (!is_file($p)) return null;
        $c = include $p;
        return is_array($c) ? $c : null;
    }

    private function api()
    {
        if ($this->api === null) {
            require_once APPPATH . 'libraries/Taqdar_vimeo.php';
            $this->api = new Taqdar_vimeo($this->cfg() ?: array());
        }
        return $this->api;
    }

    /** الربط: مفتاحه معرّف المجلّد — الأسماء تتغيّر والمعرّفات لا. */
    private function map()
    {
        $p = FCPATH . self::MAP;
        if (!is_file($p)) return null;
        $j = json_decode((string) file_get_contents($p), true);
        if (!is_array($j) || !isset($j['folders'])) return null;

        $out = array();
        foreach ($j['folders'] as $f) {
            if (!isset($f['folder_id'])) continue;
            $out[(string) $f['folder_id']] = $f;
        }
        return $out;
    }

    /**
     * عنوان الدرس — من `titles` في ملفّ الربط، مفتاحه معرّف الفيديو.
     *
     * ويردّ `null` إن لم يوجد، فيُرفض الفيديو. والرفض مقصود: اسم فيميو
     * تسميةُ ملفّ لا عنوانَ درس، واستيراده يضع في وجه الطالب
     * `مستر عبدالله الباز_رياضيات_أول متوسط_حل المسائل`.
     */
    private function title_for($dest, $v)
    {
        if (!isset($dest['titles']) || !is_array($dest['titles'])) return null;
        $t = isset($dest['titles'][$v['id']]) ? trim((string) $dest['titles'][$v['id']]) : '';
        return ($t === '') ? null : $t;
    }

    /** يحسم المقرّر والقسم — ويرفض بدل أن يخمّن. */
    private function resolve($dest)
    {
        $cid = isset($dest['course_id']) ? (int) $dest['course_id'] : 0;
        if ($cid <= 0) return array('error' => 'بلا course_id');

        $c = $this->db->select('id, title')->where('id', $cid)->get('course')->row_array();
        if (!$c) return array('error' => 'المقرَّر ' . $cid . ' غير موجود');

        /* الحاجز الذي يُبقي المقرّرات المهجورة بمنأى — قاعدةً حيّة لا
           قائمةَ معرّفات تصير خطأً يوم يُنشر أحدها.

           و`allow_draft` إذنٌ صريح لكلّ مجلّد على حدة: مقرَّرٌ أُنشئ اليوم
           مسودّةً **عن قصد** هو وجهةٌ مقصودة لا مهجور. والإذن في ملفّ
           الربط المراجَع بالعين، فهو نفسه التوثيق. */
        $pub = (int) $this->db->where('course_id', $cid)->where('status', 'published')
                              ->count_all_results('paths');
        if ($pub === 0 && empty($dest['allow_draft'])) {
            return array('error' => 'المقرَّر ' . $cid . ' على مسار غير منشور'
                                  . ' — أضف "allow_draft": true إن كان مقصودًا');
        }

        $secs = $this->db->select('id')->where('course_id', $cid)
                         ->order_by('`order`', 'ASC', false)->get('section')->result_array();
        if (!$secs) return array('error' => 'المقرَّر ' . $cid . ' بلا قسم');

        if (isset($dest['section_id'])) {
            $sid = (int) $dest['section_id'];
            $ok  = (int) $this->db->where('id', $sid)->where('course_id', $cid)
                                  ->count_all_results('section');
            if (!$ok) return array('error' => 'القسم ' . $sid . ' ليس من المقرَّر ' . $cid);
        } elseif (count($secs) === 1) {
            $sid = (int) $secs[0]['id'];
        } else {
            return array('error' => 'المقرَّر ' . $cid . ' له ' . count($secs) . ' أقسام، فاكتب section_id');
        }

        return array('course_id' => $cid, 'course' => (string) $c['title'], 'section_id' => $sid);
    }

    /**
     * شجرة المجلّدات، كلّ مجلّد مرّة واحدة.
     *
     * و`/me/projects` يردّ المجلّدات **مسطّحةً** — الجذور والمتداخلة معًا.
     * فمعاملة كلّ صفّ منها على أنّه جذر ثمّ النزول إلى أبنائه تزور المجلّد
     * المتداخل مرّتين: مرّةً كجذر ومرّةً كابن. وقياسًا على الحساب الحيّ:
     * ثمانية وسبعون فيديو تُعدّ مئتين وأربعة وثلاثين.
     *
     * فالمرور مرّتان: الأولى تجلب محتوى كلّ مجلّد **مرّةً واحدة** وتبني
     * الأبوّة، والثانية تُخرج الجذور وأبناءها في ترتيب الشجرة. وعدد
     * الطلبات هو عدد المجلّدات لا أكثر.
     */
    private function walk($api, &$nested)
    {
        $nested = true;
        $roots  = $api->projects();
        if ($roots === null) return null;

        $names = array();
        foreach ($roots as $p) {
            $id = $this->tail($p['uri']);
            if ($id !== '') $names[$id] = (string) $p['name'];
        }

        $kids = array(); $vids = array(); $parent = array();
        foreach (array_keys($names) as $id) {
            $rows = $api->items($id, $nested);
            if ($rows === null) $rows = array();
            $kids[$id] = array(); $vids[$id] = array();
            foreach ($rows as $r) {
                $t = isset($r['type']) ? (string) $r['type'] : '';
                if ($t === 'folder' && isset($r['folder']['uri'])) {
                    $c = $this->tail($r['folder']['uri']);
                    if ($c === '') continue;
                    $kids[$id][] = $c;
                    $parent[$c]  = $id;
                    if (!isset($names[$c])) $names[$c] = (string) $r['folder']['name'];
                } elseif ($t === 'video' && isset($r['video'])) {
                    $v = $this->vref_api($r['video']);
                    if ($v) $vids[$id][] = $v;
                }
            }
        }

        $out = array();
        foreach (array_keys($names) as $id) {
            if (isset($parent[$id])) continue;          /* ليس جذرًا */
            $this->emit($id, $names, $kids, $vids, $parent, $out, 0);
        }
        return $out;
    }

    /** يُخرج مجلّدًا وأبناءه، واسم الابن مسبوقٌ بأبيه ليدلّ على مادّته. */
    private function emit($id, $names, $kids, $vids, $parent, &$out, $depth, $prefix = '')
    {
        if ($depth > 6) return;
        $name = isset($names[$id]) ? $names[$id] : $id;
        $out[] = array(
            'id'      => $id,
            'name'    => $name,
            'teacher' => $prefix,
            'videos'  => isset($vids[$id]) ? $vids[$id] : array(),
        );
        foreach ((isset($kids[$id]) ? $kids[$id] : array()) as $c) {
            $this->emit($c, $names, $kids, $vids, $parent, $out, $depth + 1, $name);
        }
    }

    /**
     * يقرأ مرجعًا من صفّ فيديو في ردّ فيميو.
     *
     * **البصمة من `player_embed_url` لا من `link`.** فيديو خصوصيته `disable`
     * — وهي حال ستة وستين من ثمانية وسبعين في هذا الحساب — يردّ `link`
     * مجرّدا: `https://vimeo.com/1223738503`، بينما `player_embed_url` يحمل
     * `?h=6ea6fae696`. والدرس القائم #٤٤ يخزّن تلك البصمة بعينها، فهو
     * الدليل على أنّ هذا هو مصدر ما تخزّنه المنصّة.
     *
     * والمعرّف من `uri` لا من الرابط: `uri` يردّ أحيانا `/videos/{id}:{hash}`
     * فيُقطع عند النقطتين.
     */
    private function vref_api($v)
    {
        $id = $this->tail(isset($v['uri']) ? (string) $v['uri'] : '');
        $id = explode(':', $id);
        $id = preg_replace('/\D/', '', $id[0]);
        if ($id === '') {
            $r = $this->vref(isset($v['link']) ? (string) $v['link'] : '');
            if (!$r) return null;
            $id = $r['id'];
        }

        $hash = '';
        $emb  = isset($v['player_embed_url']) ? (string) $v['player_embed_url'] : '';
        if ($emb !== '' && preg_match('/[?&]h=([0-9A-Za-z]+)/', $emb, $m)) $hash = $m[1];
        if ($hash === '') {
            $r = $this->vref(isset($v['link']) ? (string) $v['link'] : '');
            if ($r) $hash = $r['hash'];
        }

        return array(
            'id'      => $id,
            'hash'    => $hash,
            'link'    => 'https://vimeo.com/' . $id . ($hash !== '' ? '/' . $hash : ''),
            'name'    => isset($v['name']) ? (string) $v['name'] : '',
            'sec'     => isset($v['duration']) ? (int) $v['duration'] : 0,
            'privacy' => isset($v['privacy']['view']) ? (string) $v['privacy']['view'] : '',
            'created' => isset($v['created_time']) ? (string) $v['created_time'] : '',
        );
    }

    /**
     * يقرأ مرجع فيميو من رابط — أو null.
     *
     * المضيف يُفحص قبل النمط: على المنصّة صفٌّ نوعه `vimeo` ورابطه يوتيوب،
     * والنمط وحده يردّه أيضًا، لكنّ فحص المضيف يجعل الرفض **مقصودًا** فيُعدّ
     * في دلو الشواذّ بدل أن يسقط صامتًا.
     *
     * ويطابق `vimeoRef()` في `assets/taqdar/js/tq-player.js:174` عمدًا: لو
     * اختلف المطابِق عن المشغّل، عُدَّ موجودًا درسٌ لا يستطيع المشغّل فتحه.
     */
    private function vref($url)
    {
        $s = trim((string) $url);
        if ($s === '') return null;

        $host = strtolower((string) parse_url($s, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host);
        if ($host !== 'vimeo.com' && $host !== 'player.vimeo.com') return null;

        if (!preg_match('~vimeo\.com/(?:[\w-]+/)*?(\d{6,})(?:/([0-9A-Za-z]+))?~', $s, $m)) return null;

        $hash = isset($m[2]) ? $m[2] : '';
        if ($hash === '' && preg_match('/[?&]h(?:ash)?=([0-9A-Za-z]+)/', $s, $q)) $hash = $q[1];

        return array('id' => $m[1], 'hash' => $hash,
                     'canon' => 'https://vimeo.com/' . $m[1] . ($hash !== '' ? '/' . $hash : ''));
    }

    /**
     * فهرس المنصّة: كلّ درس، بلا ترشيح على `video_type`.
     *
     * والنوع مخرَجٌ لا مدخل — `Vimeo` بحرف كبير و`vimeo` وحتّى `''` تُفهرَس
     * سواءً. والسؤال «هل هذا الفيديو هنا؟» لا «هل وُسم صحيحًا؟»، ومَوسِمٌ
     * خاطئ يُبلَّغ ولا يُرخِّص نسخةً ثانية.
     */
    private function platform_index()
    {
        $out = array();
        $rows = $this->db->query(
            'SELECT id, course_id, section_id, title, video_url, tq_status FROM `lesson`')->result_array();
        foreach ($rows as $r) {
            $v = $this->vref($r['video_url']);
            if (!$v) continue;
            if (!isset($out[$v['id']])) $out[$v['id']] = array();
            $out[$v['id']][] = $r;
        }
        return $out;
    }

    /** صفوف موسومة فيميو ورابطها ليس فيميو. */
    private function odd_rows()
    {
        $out = array();
        $rows = $this->db->query(
            "SELECT id, video_url FROM `lesson` WHERE LOWER(video_type) = 'vimeo'")->result_array();
        foreach ($rows as $r) if (!$this->vref($r['video_url'])) $out[] = $r;
        return $out;
    }

    private function finishers($course_id)
    {
        $t = $this->lesson_count($course_id);
        if ($t <= 0) return 0;
        return (int) $this->db->query(
            'SELECT COUNT(*) AS n FROM (
               SELECT p.student_id FROM `lesson_progress` p JOIN `lesson` l ON l.id = p.lesson_id
                WHERE l.course_id = ? AND p.mastered_at IS NOT NULL
                GROUP BY p.student_id HAVING COUNT(*) >= ?) z',
            array((int) $course_id, $t))->row('n');
    }

    private function lesson_count($course_id)
    {
        return (int) $this->db->where('course_id', (int) $course_id)->count_all_results('lesson');
    }

    private function actor()
    {
        $u = $this->db->select('id')->where('role_id', 1)->where('status', 1)
                      ->order_by('id', 'ASC')->limit(1)->get('users')->row_array();
        return $this->curr->actor_as('admin', $u ? (int) $u['id'] : 0);
    }

    private function tail($uri)
    {
        $p = explode('/', trim((string) $uri, '/'));
        return $p ? (string) end($p) : '';
    }

    private function hms($s)
    {
        $s = max(0, (int) $s);
        return sprintf('%02d:%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60);
    }

    /** حشوٌ يحسب المحارف لا البايتات — العربية تكسر `str_pad`. */
    private function pad($s, $w)
    {
        $s = (string) $s;
        if (mb_strlen($s) > $w) $s = mb_substr($s, 0, $w - 1) . '…';
        return $s . str_repeat(' ', max(0, $w - mb_strlen($s)));
    }

    private function ck($label, $val, $ok)
    {
        echo '  ' . ($ok ? '✓' : '✗') . ' ' . $this->pad($label, 34) . $val . "\n";
    }
}
