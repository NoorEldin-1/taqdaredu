<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * تفريغ كلام فيميو إلى المنصّة — أداة سطر أوامر.
 *
 * كلّ فيديو في الحساب له ترجمة عربيّة تلقائيّة (`ar-x-autogen`). تُنزَّل،
 * وتُحوَّل إلى صيغة المنصّة، وتُكتب في `tq_transcript` فيقرأ منها المشغّل
 * (`taqdar-lesson.js:718`) بحثًا وقفزًا إلى اللحظة.
 *
 * وأربع قواعد تحكمها:
 *
 * ١ — **التحويل قبل الكتابة إلزاميّ.** `Taqdar_learn_model::save_transcript`
 *     لا يرفض سطرًا قطّ؛ ما لا يطابق ختمًا زمنيًّا يُلحَق بنصّ المقطع السابق.
 *     فالـVTT الخام لا يُعطى لها أبدًا: السطر `00:01:23.456 --> 00:01:25.000`
 *     يُقرأ زمنه صحيحًا ثمّ يُلتقط `.456 --> …` **نصًّا للمقطع**. ينظَّف هنا.
 *
 * ٢ — **لا يُدهَس تفريغ قائم.** حقل الاستوديو موجود ليكتب المعلّم تفريغه
 *     بيده. فدرسٌ له مقاطع يُتخطّى، و`force` تُكتب صراحةً وتطبع العدد القديم.
 *
 * ٣ — **فحص صلاحيّة صريح** رغم أنّ `save_transcript` لا تطلب فاعلًا ولا
 *     تفحص شيئًا ولا تكتب سجلّ تدقيق — وهي الاستثناء المعماريّ الوحيد في
 *     طبقة المنهج. فيُفحص هنا بـ`may_edit_lesson()` قبل كلّ كتابة.
 *
 * ٤ — **المكتبة لا تُمسّ.** `libraries/Taqdar_vimeo.php` محظورة وليس فيها
 *     `texttracks()` أصلًا، ودالّتها `get()` خاصّة. فالنداء من هنا مباشرةً،
 *     والرمز يُقرأ من الإعداد ولا يُطبع ولا يغادر الخادم.
 *
 * الأفعال:
 *   doctor                 الرمز · الفيديوهات · الدروس · ما في الجدول
 *   fetch                  تنزيل كلّ الترجمات إلى الأرشيف — لا يمسّ القاعدة
 *   plan                   معاينة: أيّ درس يُملأ وبكم مقطعًا
 *   apply [force]          الكتابة
 *   verify                 فحصٌ بعديّ
 */
class Taqdar_cron_transcripts extends CI_Controller
{
    /** خارج جذر الويب وخارج git — المستودع عامّ والتفريغ منتج المعلّمين. */
    const ARCHIVE  = '/home/taqdaredu.com/vimeo_vtt';

    /** فوقها يصير الختم `H:MM:SS`؛ حقل الدقائق في نمط الحفظ سقفه ٩٩:٥٩. */
    const LONG_SEC = 5999;

    /** حدّ الدمج: لا يُصنع مقطع أطول من هذا فيضيع القفز الدقيق. */
    const MERGE_WORDS = 25;

    /** أقصى فجوة صامتة يُدمَج عبرها مقطعٌ لم ينتهِ عند علامة وقف. */
    const MERGE_GAP   = 1.0;

    private $tk = null;

    public function __construct()
    {
        parent::__construct();
        if (!$this->input->is_cli_request()) show_404();
        /* القاعدة ليست في الأوتولود لسطر الأوامر. */
        $this->load->database();
        $this->load->model('taqdar_curriculum_model', 'curr');
        $this->load->model('taqdar_learn_model', 'learn');
    }

    public function index() { $this->doctor(); }

    /* =====================================================================
       فيميو — نداء مباشر، بلا المكتبة
       ===================================================================== */

    private function token()
    {
        if ($this->tk === null) {
            $p = APPPATH . 'config/taqdar_vimeo.php';
            $c = is_file($p) ? include $p : null;
            $this->tk = (is_array($c) && !empty($c['token'])) ? (string) $c['token'] : '';
        }
        return $this->tk;
    }

    private function get($path, $retry = 2)
    {
        $ch = curl_init('https://api.vimeo.com' . $path);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => array(
                'Authorization: Bearer ' . $this->token(),
                'Accept: application/vnd.vimeo.*+json;version=3.4',
            ),
        ));
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (($code === 0 || $code === 429 || $code >= 500) && $retry > 0) {
            sleep(3);
            return $this->get($path, $retry - 1);
        }
        if ($code !== 200) return array('_err' => $code);

        $j = json_decode((string) $body, true);
        return is_array($j) ? $j : array('_err' => 'json');
    }

    /** تنزيل الترجمة نفسها — مضيفها `captions.cloud.vimeo.com` لا الواجهة. */
    private function raw($url)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0',
        ));
        $b    = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($code === 200 && $b !== false && $b !== '') ? $b : null;
    }

    private function all_videos()
    {
        $out = array();
        for ($page = 1; $page <= 20; $page++) {
            $d = $this->get('/me/videos?per_page=100&page=' . $page
                          . '&fields=uri,name,duration,privacy.view');
            if (isset($d['_err']) || empty($d['data'])) break;
            foreach ($d['data'] as $v) {
                $id = basename((string) $v['uri']);
                $out[$id] = array(
                    'id'   => $id,
                    'name' => isset($v['name']) ? (string) $v['name'] : '',
                    'sec'  => isset($v['duration']) ? (int) $v['duration'] : 0,
                );
            }
            if (empty($d['paging']['next'])) break;
        }
        return $out;
    }

    /* =====================================================================
       VTT ← صيغة المنصّة
       ===================================================================== */

    private function vtt_sec($s)
    {
        $s = trim($s);
        if (preg_match('/^(\d+):(\d{2}):(\d{2})(?:[.,](\d{1,3}))?$/', $s, $m)) {
            return (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3];
        }
        if (preg_match('/^(\d+):(\d{2})(?:[.,](\d{1,3}))?$/', $s, $m)) {
            return (int) $m[1] * 60 + (int) $m[2];
        }
        return null;
    }

    /** كتل VTT ← `array(بداية, نهاية, نصّ)`، بلا ترويسة ولا أرقام ولا وسوم. */
    private function cues($vtt)
    {
        $vtt = (string) $vtt;
        if (substr($vtt, 0, 3) === "\xEF\xBB\xBF") $vtt = substr($vtt, 3);
        $vtt = str_replace(array("\r\n", "\r"), "\n", $vtt);

        $out = array();
        foreach (preg_split('/\n{2,}/', trim($vtt)) as $block) {
            $lines = explode("\n", trim($block));
            if (!$lines) continue;

            /* ترويسة الملفّ وكتل التعليق والتنسيق — تُطرح كاملةً. */
            if (preg_match('/^(WEBVTT|NOTE|STYLE|REGION)\b/i', trim($lines[0]))) continue;

            /* معرّف المقطع سطرًا مستقلًّا قبل التوقيت. */
            if (preg_match('/^\d+$/', trim($lines[0]))) array_shift($lines);
            if (!$lines) continue;

            $ts = array_shift($lines);
            if (strpos($ts, '-->') === false) continue;
            $parts = explode('-->', $ts);
            $from  = $this->vtt_sec($parts[0]);
            if ($from === null) continue;
            /* إعدادات المقطع تلي وقت النهاية على السطر نفسه. */
            $to = $this->vtt_sec(preg_split('/\s+/', trim($parts[1]))[0]);

            $text = implode(' ', $lines);
            $text = preg_replace('/<[^>]*>/u', '', $text);      /* وسوم VTT الداخليّة */
            $text = trim(preg_replace('/\s+/u', ' ', $text));
            if ($text === '') continue;

            $out[] = array($from, $to === null ? $from : $to, $text);
        }
        return $out;
    }

    /**
     * دمج محافظ — مقطعٌ لم ينتهِ عند علامة وقف وتلاه كلامٌ بلا صمت يُضمّ إليه.
     *
     * وهو لاغٍ عمليًّا على العربيّة: ترجمة فيميو تخرج جملًا مرقَّمة تامّة.
     * وإنّما يُصلح دروس الإنجليزيّة حيث يتشظّى الكلام المخلوط إلى كلمتين.
     */
    private function merge($cues)
    {
        $out = array();
        foreach ($cues as $c) {
            $n = count($out);
            if ($n) {
                $prev = $out[$n - 1];
                $tail = preg_match('/[.!?؟۔:]\s*$/u', $prev[2]);
                $gap  = $c[0] - $prev[1];
                if (!$tail && $gap <= self::MERGE_GAP
                    && count(preg_split('/\s+/u', $prev[2])) < self::MERGE_WORDS) {
                    $out[$n - 1][1] = $c[1];
                    $out[$n - 1][2] = $prev[2] . ' ' . $c[2];
                    continue;
                }
            }
            $out[] = $c;
        }
        return $out;
    }

    /** المقاطع ← النصّ الذي يفهمه `save_transcript`: `M:SS نصّ` سطرًا سطرًا. */
    private function raw_text($cues)
    {
        $long = false;
        foreach ($cues as $c) if ($c[0] > self::LONG_SEC) { $long = true; break; }

        $lines = array();
        foreach ($cues as $c) {
            $s = (int) $c[0];
            $lines[] = ($long
                ? sprintf('%d:%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60)
                : sprintf('%d:%02d', intdiv($s, 60), $s % 60)) . ' ' . $c[2];
        }
        return implode("\n", $lines);
    }

    /* =====================================================================
       الأفعال
       ===================================================================== */

    public function doctor()
    {
        echo "— الرمز: " . ($this->token() !== '' ? 'موجود' : '✗ غائب') . "\n";
        if ($this->token() === '') return;

        $v = $this->all_videos();
        echo '— فيديوهات الحساب: ' . count($v) . "\n";

        $les = $this->lessons();
        echo '— دروس بروابط فيميو: ' . count($les) . "\n";

        $row = $this->db->query('SELECT COUNT(*) c, COUNT(DISTINCT lesson_id) l FROM tq_transcript')->row_array();
        echo '— في الجدول الآن: ' . (int) $row['c'] . ' مقطعًا في ' . (int) $row['l'] . " درسًا\n";

        $n = is_dir(self::ARCHIVE) ? count(glob(self::ARCHIVE . '/*.vtt')) : -1;
        echo '— الأرشيف: ' . ($n < 0 ? 'غير موجود بعد' : $n . ' ملفًّا') . "\n";
    }

    public function fetch()
    {
        if (!is_dir(self::ARCHIVE) && !@mkdir(self::ARCHIVE, 0755, true)) {
            echo "✗ تعذّر إنشاء الأرشيف\n"; return;
        }
        $vids = $this->all_videos();
        if (!$vids) { echo "✗ لا فيديوهات — افحص الرمز\n"; return; }

        $ok = $skip = $none = $fail = 0;
        foreach ($vids as $id => $v) {
            $dest = self::ARCHIVE . '/' . $id . '.vtt';
            if (is_file($dest) && filesize($dest) > 64) { $skip++; continue; }

            $d = $this->get('/videos/' . $id . '/texttracks?fields=link,language,type');
            if (isset($d['_err']) || empty($d['data'])) {
                echo '  ✗ ' . $id . ' — لا مسار ترجمة (' . (isset($d['_err']) ? $d['_err'] : 'فارغ') . ")\n";
                $none++; continue;
            }
            /* العربيّة أوّلًا، وإلّا فأوّل مسار موجود. */
            $pick = null;
            foreach ($d['data'] as $t) {
                if (isset($t['language']) && strpos((string) $t['language'], 'ar') === 0) { $pick = $t; break; }
            }
            if ($pick === null) $pick = $d['data'][0];

            $body = empty($pick['link']) ? null : $this->raw($pick['link']);
            if ($body === null) { echo '  ✗ ' . $id . " — فشل التنزيل\n"; $fail++; continue; }

            file_put_contents($dest, $body);
            $ok++;
        }
        echo "\nنُزّل: $ok · موجود سلفًا: $skip · بلا ترجمة: $none · فشل: $fail\n";
        echo 'الأرشيف: ' . count(glob(self::ARCHIVE . '/*.vtt')) . " ملفًّا\n";
    }

    public function plan()       { $this->run(false, false); }
    public function apply($f = '') { $this->run(true, $f === 'force'); }

    private function run($write, $force)
    {
        $actor = $this->actor();
        $les   = $this->lessons();
        if (!$les) { echo "لا دروس بروابط فيميو.\n"; return; }

        $done = $skipped = $missing = $denied = $empty = 0;
        $segs = 0;

        foreach ($les as $L) {
            $lid  = (int) $L['id'];
            $vid  = $L['vid'];
            $file = self::ARCHIVE . '/' . $vid . '.vtt';

            if (!is_file($file)) {
                echo '  ✗ ' . $lid . ' — لا ملفّ ترجمة للفيديو ' . $vid . "\n";
                $missing++; continue;
            }

            $have = (int) $this->db->where('lesson_id', $lid)->count_all_results('tq_transcript');
            if ($have > 0 && !$force) { $skipped++; continue; }

            $cues = $this->merge($this->cues(file_get_contents($file)));
            if (!$cues) { echo '  ✗ ' . $lid . " — الملفّ بلا مقاطع صالحة\n"; $empty++; continue; }

            if (!$this->curr->may_edit_lesson($actor, $lid)) {
                echo '  ✗ ' . $lid . " — الصلاحيّة مرفوضة\n"; $denied++; continue;
            }

            if (!$write) {
                echo sprintf("  + %-4d ق%-3d %-4d مقطعًا  |  %s\n", $lid, (int) $L['course_id'],
                             count($cues), mb_substr($cues[0][2], 0, 46));
                $done++; $segs += count($cues); continue;
            }

            if ($have > 0) echo '  ⟳ ' . $lid . ' — استبدال ' . $have . " مقطعًا\n";

            $r = $this->learn->save_transcript($lid, $this->raw_text($cues));
            if (!is_array($r) || empty($r['ok'])) { echo '  ✗ ' . $lid . " — رفض الحفظ\n"; continue; }

            $n = (int) $this->db->where('lesson_id', $lid)->count_all_results('tq_transcript');
            if ($n !== count($cues)) echo '  ⚠ ' . $lid . ' — كُتب ' . $n . ' والمتوقَّع ' . count($cues) . "\n";
            $done++; $segs += $n;
        }

        echo "\n" . ($write ? 'كُتب' : 'سيُكتب') . ": $done درسًا · $segs مقطعًا\n";
        echo "متخطّى (له تفريغ): $skipped · بلا ملفّ: $missing · بلا مقاطع: $empty · مرفوض: $denied\n";
    }

    public function verify()
    {
        $row = $this->db->query('SELECT COUNT(*) c, COUNT(DISTINCT lesson_id) l FROM tq_transcript')->row_array();
        echo 'الجدول: ' . (int) $row['c'] . ' مقطعًا في ' . (int) $row['l'] . " درسًا\n";

        $bad = $this->db->query(
            'SELECT COUNT(*) n FROM tq_transcript WHERE text LIKE "%-->%" OR text LIKE "WEBVTT%"')->row_array();
        echo 'مقاطع فيها أثر VTT خام: ' . (int) $bad['n'] . ' ' . ((int) $bad['n'] === 0 ? '✓' : '✗') . "\n";

        $neg = $this->db->query('SELECT COUNT(*) n FROM tq_transcript WHERE at_second < 0')->row_array();
        echo 'أزمنة سالبة: ' . (int) $neg['n'] . ' ' . ((int) $neg['n'] === 0 ? '✓' : '✗') . "\n";

        $over = $this->db->query(
            'SELECT COUNT(*) n FROM tq_transcript t JOIN lesson l ON l.id=t.lesson_id
             WHERE l.duration_sec > 0 AND t.at_second > l.duration_sec + 60')->row_array();
        echo 'مقاطع بعد نهاية الفيديو: ' . (int) $over['n'] . ' ' . ((int) $over['n'] === 0 ? '✓' : '✗') . "\n";

        $orph = $this->db->query(
            'SELECT COUNT(DISTINCT t.lesson_id) n FROM tq_transcript t
             LEFT JOIN lesson l ON l.id=t.lesson_id WHERE l.id IS NULL')->row_array();
        echo 'مقاطع بلا درس: ' . (int) $orph['n'] . ' ' . ((int) $orph['n'] === 0 ? '✓' : '✗') . "\n";

        echo "\nأقلّ وأكثر الدروس مقاطعَ:\n";
        foreach ($this->db->query(
            'SELECT t.lesson_id, COUNT(*) n, l.duration_sec, LEFT(l.title,34) ti
             FROM tq_transcript t JOIN lesson l ON l.id=t.lesson_id
             GROUP BY t.lesson_id ORDER BY n ASC LIMIT 3')->result_array() as $r) {
            echo sprintf("  %-4d %-4d مقطعًا  %-5dث  %s\n", $r['lesson_id'], $r['n'], $r['duration_sec'], $r['ti']);
        }
    }

    /** عيّنة مقروءة: أوّل ما يقوله الدرس. */
    public function sample($lid = 0, $n = 6)
    {
        foreach ($this->db->where('lesson_id', (int) $lid)->order_by('at_second', 'ASC')
                          ->limit((int) $n)->get('tq_transcript')->result_array() as $r) {
            echo sprintf("  %5ds  %s\n", $r['at_second'], $r['text']);
        }
    }

    /** مقارنة ثلاثيّة: مدّة الدرس · مدّة الفيديو عند فيميو · آخر ختم في الترجمة. */
    public function probe($ids = '')
    {
        $want = array_filter(array_map('intval', explode('-', (string) $ids)));
        foreach ($this->lessons() as $L) {
            $lid = (int) $L['id'];
            if ($want && !in_array($lid, $want, true)) continue;
            $d   = $this->get('/videos/' . $L['vid'] . '?fields=duration,name');
            $vim = isset($d['duration']) ? (int) $d['duration'] : -1;
            $f   = self::ARCHIVE . '/' . $L['vid'] . '.vtt';
            $mx  = 0;
            if (is_file($f)) foreach ($this->cues(file_get_contents($f)) as $c) $mx = max($mx, (int) $c[1]);
            echo sprintf("  %-4d ق%-3d فيمو=%-5s درس=%-5d ترجمة=%-5d  %s  |  %s\n",
                $lid, (int) $L['course_id'], $vim, (int) $L['duration_sec'], $mx,
                ($vim === (int) $L['duration_sec'] ? '✓مدّة' : '✗مدّة'),
                mb_substr((string) $L['title'], 0, 30));
        }
    }

    /**
     * ترميم الدروس التي ترجمتها ليست ترجمتها.
     *
     * فيميو يسلّم أحيانًا ترجمة **التسجيل الأصليّ كاملًا** مع مقطع مقصوص منه.
     * أُثبت بثلاثة أدلّة مجتمعة:
     *   · مدّة الفيديو عند فيميو تطابق `duration_sec` تمامًا، والترجمة تبلغ ضِعفها.
     *   · ملفّا الترجمة في الزوجين ٢٣٦/٢٣٧ و٢٢٦/٢٢٧ **متطابقان بايتًا ببايت**
     *     (تفريغ آليّ حتميّ على الصوت نفسه) وهما الزوجان الوحيدان في الأرشيف.
     *   · الأجزاء التالية تبدأ ترجمتها بالسلام والتحيّة، وهي لا تفتتح بسلام
     *     أصلًا — عُرف ثابت في هذه المنصّة: التابع يبدأ من حيث وقف سابقه.
     *
     * فالعلاج مختلف بحسب الموضع:
     *   · **رأس التسجيل** — الثانية صفر واحدة في الأصل وفي المقطع، فالمحاذاة
     *     سليمة ويكفي قصّ ما بعد نهاية الفيديو.
     *   · **جزء تالٍ** — زمن الترجمة يعود لبداية الأصل لا لبداية المقطع، فكلّ
     *     ختم مزاح بمقدار مجهول. تُفرَّغ، لأنّ غياب الترجمة صادق وواجهة المشغّل
     *     تخفي اللوحة عند صفر مقطع (`taqdar-lesson.js:721`)، أمّا ترجمة مزاحة
     *     فتقفز بالطالب إلى لحظة خاطئة وهو يظنّها صحيحة.
     */
    public function mend($apply = '')
    {
        $heads = array(226, 236);        /* رأس التسجيل — تُقصّ */
        $parts = array(225, 227, 237);   /* جزء تالٍ — تُفرَّغ */
        $actor = $this->actor();

        foreach ($this->lessons() as $L) {
            $lid = (int) $L['id'];
            $head = in_array($lid, $heads, true);
            if (!$head && !in_array($lid, $parts, true)) continue;

            $dur  = (int) $L['duration_sec'];
            $have = (int) $this->db->where('lesson_id', $lid)->count_all_results('tq_transcript');

            if ($head) {
                $keep = array();
                foreach ($this->merge($this->cues(file_get_contents(self::ARCHIVE . '/' . $L['vid'] . '.vtt'))) as $c) {
                    if ((int) $c[0] <= $dur) $keep[] = $c;
                }
                $raw = $this->raw_text($keep);
                $what = 'قصّ إلى ' . $dur . 'ث: ' . $have . ' ← ' . count($keep) . ' مقطعًا';
            } else {
                $raw  = '';
                $what = 'تفريغ: ' . $have . ' ← 0 مقطعًا';
            }

            if ($apply !== 'apply') { echo '  + ' . $lid . '  ' . $what . "\n"; continue; }
            if (!$this->curr->may_edit_lesson($actor, $lid)) { echo '  ✗ ' . $lid . " — الصلاحيّة مرفوضة\n"; continue; }

            $this->learn->save_transcript($lid, $raw);
            $now = (int) $this->db->where('lesson_id', $lid)->count_all_results('tq_transcript');
            $mx  = (int) ($this->db->select_max('at_second', 'm')->where('lesson_id', $lid)
                               ->get('tq_transcript')->row('m'));
            echo sprintf("  \xe2\x9c\x93 %-4d %-34s  آخر ختم=%-5dث  مدّة=%dث\n", $lid, $what, $mx, $dur);
        }
    }

    /* =====================================================================
       مساعدات
       ===================================================================== */

    /** الدروس ذات روابط فيميو، ومعها معرّف الفيديو مستخرَجًا من الرابط. */
    private function lessons()
    {
        $out = array();
        foreach ($this->db->select('id, course_id, section_id, title, video_url, duration_sec')
                          ->like('video_url', 'vimeo.com/')
                          ->order_by('course_id', 'ASC')->order_by('id', 'ASC')
                          ->get('lesson')->result_array() as $r) {
            if (!preg_match('~vimeo\.com/(?:[\w-]+/)*?(\d{6,})~', (string) $r['video_url'], $m)) continue;
            $r['vid'] = $m[1];
            $out[] = $r;
        }
        return $out;
    }

    private function actor()
    {
        $u = $this->db->select('id')->where('role_id', 1)->where('status', 1)
                      ->order_by('id', 'ASC')->limit(1)->get('users')->row_array();
        return $this->curr->actor_as('admin', $u ? (int) $u['id'] : 0);
    }
}
