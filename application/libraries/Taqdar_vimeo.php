<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * عميل فيميو — قراءةٌ فقط.
 *
 * ولا حزمة composer له: المستودع بلا `vendor/` ولا خطوة بناء، وأربعة نداءات
 * `GET` لا تستحق شجرة اعتماديات تغيّر قصة النشر كلها.
 *
 * والرمز لا يُطبع ولا يُسجَّل ولا يظهر في رسالة خطأ — `probe()` تردّ طوله
 * وصلاحياته لا قيمته. والمستودع **عام**، فموضع الرمز
 * `application/config/taqdar_vimeo.php` وهو مستبعَد من git.
 *
 * وفيه حارسٌ واحد صريح: لا فعل غير `GET`. فحتى لو أُعطي رمزًا بصلاحية
 * تعديل، لا سبيل في هذا الصف إلى الكتابة على فيميو.
 */
class Taqdar_vimeo
{
    const BASE    = 'https://api.vimeo.com';
    const ACCEPT  = 'application/vnd.vimeo.*+json;version=3.4';
    const PER     = 100;
    const MAXPAGE = 200;   /* حارس جموح: صفحةٌ تعيد نفسها لا تدور إلى الأبد */

    private $token = '';
    private $err   = '';

    public function __construct($cfg = array())
    {
        if (isset($cfg['token'])) $this->token = trim((string) $cfg['token']);
    }

    public function has_token() { return $this->token !== ''; }
    public function error()     { return $this->err; }

    /**
     * فحص الرمز — الخطوة التي تحوّل الفخ الصامت إلى رسالة.
     *
     * منحة `client_credentials` تعطي صلاحية `public` وحدها، وهي لا تسرد
     * مجلدات المستخدم ولا فيديوهاته غير المدرجة. فبها تنجح كل النداءات
     * وتعود بقوائم **فارغة** — وهذا أسوأ من خطأ: يبدو أن لا جديد.
     */
    public function probe()
    {
        $v = $this->get('/oauth/verify');
        if ($v === null) return null;

        $scope = isset($v['scope']) ? (string) $v['scope'] : '';
        $me    = $this->get('/me', array('fields' => 'uri,name'));

        return array(
            'scopes'  => array_values(array_filter(explode(' ', $scope))),
            'private' => (strpos($scope, 'private') !== false),
            'name'    => $me && isset($me['name']) ? (string) $me['name'] : '',
            'uri'     => $me && isset($me['uri'])  ? (string) $me['uri']  : '',
            'len'     => strlen($this->token),
        );
    }

    /** المجلّدات المسطَّحة — تُستعمل تحقّقًا من المجموع لا شجرةً. */
    public function projects()
    {
        return $this->page('/me/projects', array(
            'fields' => 'uri,name,created_time,metadata.connections.videos.total'));
    }

    /**
     * محتوى مجلّد: فيديوهاته ومجلّداته الفرعية.
     *
     * و`/items` هو ما يرى المتداخل. فإن ردّ الخادم ٤٠٤ سقطنا إلى
     * `/videos` — **ونقولها**: بلا قول تصير المجلّدات الفرعية غيابًا صامتًا
     * يظهر على أنه «لا جديد».
     */
    public function items($project_id, &$nested = true)
    {
        $rows = $this->page('/me/projects/' . (int) $project_id . '/items', array(
            /* `player_embed_url` ليس ترفًا: هو الحقل الوحيد الذي يحمل
               البصمة لفيديو خصوصيته `disable` — و`link` عنده مجرّد منها. */
            'fields' => 'type,folder.uri,folder.name,'
                      . 'video.uri,video.link,video.player_embed_url,video.name,'
                      . 'video.duration,video.privacy.view,video.created_time,'
                      . 'video.modified_time'));
        if ($rows !== null) return $rows;

        $nested = false;
        $vids = $this->page('/me/projects/' . (int) $project_id . '/videos', array(
            'fields' => 'uri,link,player_embed_url,name,duration,privacy.view,'
                      . 'created_time,modified_time'));
        if ($vids === null) return null;

        $out = array();
        foreach ($vids as $v) $out[] = array('type' => 'video', 'video' => $v);
        return $out;
    }

    /** كل فيديو في الحساب — لكشف ما هو خارج كل مجلّد. */
    public function all_videos()
    {
        return $this->page('/me/videos', array(
            'fields' => 'uri,link,player_embed_url,name,duration,privacy.view,'
                      . 'created_time,modified_time'));
    }

    /* =====================================================================
       الداخل
       ===================================================================== */

    /** يتبع `paging.next` لا أرقام صفحات محسوبة: المجموع يتحرّك تحت القدم. */
    private function page($path, $query = array())
    {
        $out  = array();
        $q    = array_merge($query, array('per_page' => self::PER, 'page' => 1));
        $next = $path . '?' . http_build_query($q);

        for ($i = 0; $i < self::MAXPAGE && $next !== ''; $i++) {
            $r = $this->get($next);
            if ($r === null) return null;
            if (isset($r['data']) && is_array($r['data'])) {
                foreach ($r['data'] as $row) $out[] = $row;
            }
            $next = (isset($r['paging']['next']) && $r['paging']['next']) ? (string) $r['paging']['next'] : '';
        }
        return $out;
    }

    private function get($path, $query = array())
    {
        if ($this->token === '') { $this->err = 'لا رمز.'; return null; }

        $url = (strpos($path, 'http') === 0) ? $path : self::BASE . $path;
        if ($query) $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query);

        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'GET',      /* الحارس: لا فعل سواه */
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => array(
                'Authorization: Bearer ' . $this->token,
                'Accept: ' . self::ACCEPT,
            ),
        ));
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);

        if ($body === false) { $this->err = 'تعذّر الاتصال: ' . $cerr; return null; }

        if ($code === 401) { $this->err = 'الرمز مرفوض (401) — منتهٍ أو خاطئ.'; return null; }
        if ($code === 403) { $this->err = 'ممنوع (403) — الرمز بلا الصلاحية اللازمة.'; return null; }
        if ($code === 429) { $this->err = 'تجاوزنا حدّ الطلبات (429) — أعد بعد قليل.'; return null; }
        if ($code < 200 || $code >= 300) { $this->err = 'ردّ فيميو ' . $code . '.'; return null; }

        $j = json_decode((string) $body, true);
        if (!is_array($j)) { $this->err = 'ردٌّ غير مفهوم من فيميو.'; return null; }

        $this->err = '';
        return $j;
    }
}
