<?php
/**
 * رسائلي — بوّابة الطالب.
 *
 * ── قاعدة مُلزِمة لهذه الشاشة ──────────────────────────────────────────────
 * المراسلة هنا بين الطالب ومعلّمه، أو الدعم الفنّي، أو مجموعة مادة يشرف
 * عليها معلّم — ولا رسائل خاصّة بين الطلاب بعضهم ببعض، لأن أغلب مستخدمي
 * المنصّة قاصرون، وقناة خاصّة بين قاصرَين بلا إشراف بابٌ لا يُفتح.
 * قائمة المستقبِلين أدناه مبنيّة على هذا الأساس: معلّمو موادّك المسجّلة
 * وحساب الدعم فقط. وهذا نصف القاعدة؛ نصفها الآخر أن يفرضها الخادم عند
 * الإرسال ولا يكتفي بإخفاء الخيار — إخفاء الزرّ ليس صلاحية.
 * ─────────────────────────────────────────────────────────────────────────
 */

$tq_nav   = 'messages';
$tq_role  = 'student';
$tq_title = 'رسائلي';
$tq_sub   = 'تواصل مع معلّميك والدعم الفنّي بسهولة';
$tq_icon  = 'chat';

$uid = (int) $this->session->userdata('user_id');

/* ---- حذف المحادثة: يُنفَّذ قبل أي إخراج، وبتحقّق ملكية على الخادم ------
   زرّ الخطر يجب أن يفعل ما يقوله. والتحقّق هنا لا يكفي وحده: الصلاحية
   تُفرض في الخادم لا في الواجهة، وهذا الشرط جزء من الخادم لا من العرض. */
if ($this->input->post('action') === 'delete_thread') {
    $tq_del = (string) $this->input->post('thread', true);
    $tq_own = $this->db->where('message_thread_code', $tq_del)
        ->group_start()->where('sender', (string) $uid)->or_where('receiver', (string) $uid)->group_end()
        ->count_all_results('message_thread');
    if ($tq_del !== '' && $tq_own > 0) {
        $this->db->where('message_thread_code', $tq_del)->delete('message');
        $this->db->where('message_thread_code', $tq_del)->delete('message_thread');
    }
    redirect(site_url('taqdar/messages'), 'refresh');
}

/* ---- المحادثات ------------------------------------------------------- */
$tq_threads_raw = $this->db
    ->group_start()->where('sender', (string) $uid)->or_where('receiver', (string) $uid)->group_end()
    ->order_by('last_message_timestamp', 'DESC')
    ->get('message_thread')->result_array();

$tq_threads = [];
foreach ($tq_threads_raw as $t) {
    $code  = $t['message_thread_code'];
    $other = ((int) $t['sender'] === $uid) ? (int) $t['receiver'] : (int) $t['sender'];

    $last = $this->db->where('message_thread_code', $code)
        ->order_by('timestamp', 'DESC')->limit(1)
        ->get('message')->row_array();

    $unread = (int) $this->db->where('message_thread_code', $code)
        ->where('receiver', $uid)->where('read_status', 0)
        ->count_all_results('message');

    $person = $this->db->select('id, first_name, last_name, image, is_instructor, role_id')
        ->where('id', $other)->get('users')->row_array();

    $tq_threads[] = [
        'code'    => $code,
        'other'   => $other,
        'person'  => $person,
        'last'    => $last,
        'unread'  => $unread,
        'ts'      => (int) ($last['timestamp'] ?? $t['last_message_timestamp']),
    ];
}

/* ---- تصفية القائمة: تبويبات وبحث يعملان على الخادم فعلًا -------------- */
$tq_filter = $this->input->get('filter', true);
$tq_filter = in_array($tq_filter, ['unread', 'teachers', 'support'], true) ? $tq_filter : 'all';
$tq_query  = trim((string) $this->input->get('q', true));

$tq_all_threads = $tq_threads;
$tq_threads = array_values(array_filter($tq_threads, static function ($t) use ($tq_filter, $tq_query) {
    if ($tq_filter === 'unread'   && $t['unread'] < 1) return false;
    if ($tq_filter === 'teachers' && empty($t['person']['is_instructor'])) return false;
    if ($tq_filter === 'support'  && (int) ($t['person']['role_id'] ?? 0) !== 1) return false;
    if ($tq_query !== '') {
        $hay = ($t['person']['first_name'] ?? '') . ' ' . ($t['person']['last_name'] ?? '') . ' ' . ($t['last']['message'] ?? '');
        if (mb_stripos($hay, $tq_query) === false) return false;
    }
    return true;
}));

$tq_filters = [
    'all'      => 'الكل',
    'unread'   => 'غير مقروءة',
    'teachers' => 'المعلّمون',
    'support'  => 'الدعم',
];
$tq_unread_total = 0;
foreach ($tq_all_threads as $t) {
    $tq_unread_total += $t['unread'];
}

/* المحادثة المفتوحة */
$tq_open_code = $this->input->get('t', true);
$tq_open      = null;
foreach ($tq_threads as $t) {
    if ($t['code'] === $tq_open_code) {
        $tq_open = $t;
        break;
    }
}
if ($tq_open === null && $tq_threads) {
    $tq_open = $tq_threads[0];
}

/* فتح المحادثة يجعلها مقروءة — كما يتوقّع من فتحها فعلًا. */
if ($tq_open && $tq_open_code === $tq_open['code'] && $tq_open['unread'] > 0) {
    $this->crud_model->mark_thread_messages_read($tq_open['code']);
    $tq_open['unread'] = 0;
}

$tq_messages = [];
if ($tq_open) {
    $tq_messages = $this->db->where('message_thread_code', $tq_open['code'])
        ->order_by('timestamp', 'ASC')
        ->get('message')->result_array();
}

/* ---- المستقبِلون المسموح بهم: معلّمو موادّك + الدعم ------------------- */
$tq_allowed = [];
$tq_teacher_ids = $this->db->select('c.user_id')
    ->from('enrol e')->join('course c', 'c.id = e.course_id', 'inner')
    ->where('e.user_id', $uid)
    ->get()->result_array();
$tq_teacher_ids = array_values(array_unique(array_filter(array_map(static function ($r) {
    return (int) $r['user_id'];
}, $tq_teacher_ids))));
if ($tq_teacher_ids) {
    foreach ($this->db->select('id, first_name, last_name')->where_in('id', $tq_teacher_ids)->get('users')->result_array() as $u) {
        $tq_allowed[] = ['id' => (int) $u['id'], 'name' => trim($u['first_name'] . ' ' . $u['last_name']), 'kind' => 'معلّم'];
    }
}
foreach ($this->db->select('id, first_name, last_name')->where('role_id', 1)->limit(1)->get('users')->result_array() as $u) {
    $tq_allowed[] = ['id' => (int) $u['id'], 'name' => 'الدعم الفنّي', 'kind' => 'دعم'];
}

/* ---- أدوات عرض ------------------------------------------------------- */
$tq_photo_of = static function ($person) {
    return (!empty($person['image']))
        ? base_url('uploads/user_image/' . $person['image'])
        : tq_asset('brand/icon.png');
};
$tq_name_of = static function ($person) {
    if (empty($person)) {
        return 'مستخدم محذوف';
    }
    return trim($person['first_name'] . ' ' . $person['last_name']) ?: 'مستخدم';
};
$tq_clock = static function ($ts) {
    return tq_num(date('g:i', (int) $ts), 'tq-num--sm') . ' ' . ((int) date('G', (int) $ts) < 12 ? 'ص' : 'م');
};
$tq_daylabel = static function ($ts) {
    $d = strtotime('today') - strtotime(date('Y-m-d', (int) $ts));
    if ($d === 0)     return 'اليوم';
    if ($d === 86400) return 'أمس';
    $days   = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
    $months = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
    return $days[(int) date('w', $ts)] . '، ' . TQ_LRI . date('j', $ts) . TQ_PDI . ' ' . $months[(int) date('n', $ts) - 1];
};

include 'portal_open.php';
?>

<style>
/* رسائلي — ثلاثة أعمدة: القائمة · الحوار · معلومات المحادثة. */
.tq-icon-box[class*='tq-pastel--'] { color: var(--tq-pastel-ink); }
/* تبويبات القائمة روابط حقيقية تعمل بلا جافاسكربت، فحالتها aria-current. */
.tq-tab[aria-current='page'] { color: var(--tq-navy); border-block-end-color: var(--tq-navy); font-weight: 700; }
.tq-tab { display: inline-flex; align-items: center; gap: var(--tq-space-xs); }
.tq-tab:hover { text-decoration: none; }

.tq-chatwrap { display: grid; grid-template-columns: 320px minmax(0, 1fr); gap: var(--tq-space-xl); align-items: start; }

.tq-convlist { background: var(--tq-surface); border-radius: var(--tq-radius-card); box-shadow: var(--tq-shadow-soft); padding: var(--tq-space-l); }
.tq-convsearch { position: relative; margin-block: var(--tq-space-m) var(--tq-space-l); }
.tq-conv { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: var(--tq-space-m);
  padding: var(--tq-space-m); border-radius: var(--tq-radius-medium); align-items: center; }
.tq-conv:hover { background: var(--tq-navyWash); text-decoration: none; }
.tq-conv[aria-current='page'] { background: var(--tq-sky-fill); }
.tq-conv__ava { position: relative; }
.tq-conv__on { position: absolute; inset-block-end: 0; inset-inline-end: 0; inline-size: 10px; block-size: 10px;
  border-radius: var(--tq-radius-pill); background: var(--tq-teal); border: 2px solid var(--tq-surface); }
.tq-conv__name { color: var(--tq-navy); font: var(--tq-type-bodyStrong); display: block;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tq-conv__last { color: var(--tq-text2); font: var(--tq-type-caption); display: block;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tq-conv__meta { display: grid; justify-items: end; gap: var(--tq-space-xs); }
.tq-conv__count { min-inline-size: 22px; padding: 0 var(--tq-space-xs); background: var(--tq-actionPrimary); color: var(--tq-onAction);
  border-radius: var(--tq-radius-pill); font: var(--tq-type-numeralSm); text-align: center; unicode-bidi: isolate; direction: ltr; }

.tq-thread { background: var(--tq-surface); border-radius: var(--tq-radius-card); box-shadow: var(--tq-shadow-soft);
  display: flex; flex-direction: column; min-block-size: 640px; }
.tq-thread__head { display: flex; align-items: center; gap: var(--tq-space-m);
  padding: var(--tq-space-l) var(--tq-space-xl); border-block-end: 1px solid var(--tq-line); }
.tq-thread__pin { display: flex; align-items: center; gap: var(--tq-space-m);
  margin: var(--tq-space-l) var(--tq-space-xl) 0; padding: var(--tq-space-m) var(--tq-space-l);
  background: var(--tq-sand-fill); border-radius: var(--tq-radius-medium); color: var(--tq-navy); font: var(--tq-type-caption); }
.tq-thread__body { flex: 1; padding: var(--tq-space-xl); display: flex; flex-direction: column; gap: var(--tq-space-l); overflow-y: auto; }
.tq-thread__foot { border-block-start: 1px solid var(--tq-line); padding: var(--tq-space-l) var(--tq-space-xl); }

.tq-daysep { display: grid; place-items: center; }
.tq-daysep span { background: var(--tq-navyWash); color: var(--tq-navy); border-radius: var(--tq-radius-pill);
  padding: var(--tq-space-xs) var(--tq-space-m); font: var(--tq-type-micro); }

.tq-bubble { max-inline-size: 62%; padding: var(--tq-space-m) var(--tq-space-l); border-radius: var(--tq-radius-medium); }
/* رسائل الطالب: تعبئة باستيل ومحاذاة start، ونصّها كحلي لا حبر العائلة. */
.tq-bubble--me { align-self: flex-start; background: var(--tq-sky-fill); color: var(--tq-navy); }
/* رسائل المعلم: بيضاء بحدّ. */
.tq-bubble--them { align-self: flex-end; background: var(--tq-surface); border: 1px solid var(--tq-line); color: var(--tq-text); }
.tq-bubble__meta { display: flex; align-items: center; gap: var(--tq-space-xs); margin-block-start: var(--tq-space-xs); }
.tq-bubble__time { font: var(--tq-type-micro); color: var(--tq-text2); }
.tq-bubble__seen { color: var(--tq-teal); display: inline-flex; }
.tq-bubble__seen--sent { color: var(--tq-text3); }

.tq-attach { display: flex; align-items: center; gap: var(--tq-space-m); margin-block-start: var(--tq-space-s);
  padding: var(--tq-space-m); background: var(--tq-surface); border: 1px solid var(--tq-line); border-radius: var(--tq-radius-medium); }

.tq-composer { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: var(--tq-space-m); align-items: center; }
.tq-composer__send { inline-size: var(--tq-touch-min); block-size: var(--tq-touch-min); border-radius: var(--tq-radius-pill);
  background: var(--tq-actionPrimary); color: var(--tq-onAction); display: grid; place-items: center; flex: none; }
.tq-composer__send:hover { background: var(--tq-navySoft); }
html[dir='rtl'] .tq-composer__send svg { transform: scaleX(-1); }

.tq-inforow { display: flex; align-items: center; justify-content: space-between; gap: var(--tq-space-m);
  padding: var(--tq-space-m) 0; color: var(--tq-text2); font: var(--tq-type-caption); }
.tq-inforow + .tq-inforow { border-block-start: 1px solid var(--tq-line); }
.tq-inforow a, .tq-inforow button { color: var(--tq-text2); }

.tq-danger-link { color: var(--tq-danger); display: inline-flex; align-items: center; gap: var(--tq-space-s); }

@media (max-width: 1023.98px) { .tq-chatwrap { grid-template-columns: minmax(0, 1fr); } .tq-thread { min-block-size: 480px; } }
</style>

<div class="tq-cols">
    <div class="tq-chatwrap">

        <!-- العمود الأول: قائمة المحادثات -->
        <section class="tq-convlist" aria-labelledby="tq-conv-h">
            <h2 class="tq-sr" id="tq-conv-h">قائمة المحادثات</h2>

            <nav class="tq-tabs" aria-label="تصفية المحادثات" style="margin-block-end:0;gap:var(--tq-space-l)">
                <?php foreach ($tq_filters as $key => $label): ?>
                    <a class="tq-tab"
                       href="<?php echo base_url('student/messages') . ($key === 'all' ? '' : '?filter=' . $key); ?>"
                       <?php echo tq_active($key, $tq_filter); ?>>
                        <?php echo html_escape($label); ?>
                        <?php if ($key === 'unread' && $tq_unread_total > 0): ?>
                            <span class="tq-conv__count"><?php echo TQ_LRI . $tq_unread_total . TQ_PDI; ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <form class="tq-convsearch" role="search" method="get" action="<?php echo base_url('student/messages'); ?>">
                <label class="tq-sr" for="tq-conv-q">ابحث في المحادثات</label>
                <input class="tq-input" id="tq-conv-q" name="q" type="search" placeholder="ابحث في المحادثات…"
                       value="<?php echo html_escape($tq_query); ?>">
            </form>

            <?php if (!$tq_threads): ?>
                <div class="tq-empty" style="padding-block:var(--tq-space-xl)">
                    <div class="tq-empty__art tq-pastel tq-pastel--sky" style="inline-size:88px;block-size:88px;display:grid;place-items:center;border-radius:var(--tq-radius-pill)">
                        <span class="tq-pastel__icon" aria-hidden="true"><?php echo tq_icon('chat', 36); ?></span>
                    </div>
                    <h3 class="tq-empty__title" style="font:var(--tq-type-bodyStrong)">لا محادثات بعد</h3>
                    <p class="tq-empty__text tq-caption">
                        اسأل معلّمك عمّا لم يتّضح لك في الدرس، أو راسل الدعم الفنّي إن واجهتك مشكلة.
                        كل محادثة ستظهر هنا بآخر رسالة ووقتها.
                    </p>
                </div>
            <?php else: ?>
                <ul>
                    <?php foreach ($tq_threads as $t): ?>
                        <li>
                            <a class="tq-conv" href="<?php echo base_url('taqdar/messages?t=' . urlencode($t['code'])); ?>"
                               <?php echo ($tq_open && $t['code'] === $tq_open['code']) ? ' aria-current="page"' : ''; ?>>
                                <span class="tq-conv__ava">
                                    <img class="tq-avatar" src="<?php echo html_escape($tq_photo_of($t['person'])); ?>"
                                         alt="صورة <?php echo html_escape($tq_name_of($t['person'])); ?>">
                                    <span class="tq-conv__on" aria-hidden="true"></span>
                                </span>
                                <span>
                                    <span class="tq-conv__name"><?php echo html_escape($tq_name_of($t['person'])); ?></span>
                                    <span class="tq-conv__last"><?php echo html_escape(mb_substr(strip_tags((string) ($t['last']['message'] ?? '')), 0, 60)); ?></span>
                                </span>
                                <span class="tq-conv__meta">
                                    <span class="tq-micro"><?php echo $tq_clock($t['ts']); ?></span>
                                    <?php if ($t['unread'] > 0): ?>
                                        <span class="tq-conv__count"><?php echo TQ_LRI . $t['unread'] . TQ_PDI; ?></span>
                                        <span class="tq-sr">رسائل غير مقروءة</span>
                                    <?php endif; ?>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <details style="margin-block-start:var(--tq-space-l)">
                <summary class="tq-btn tq-btn--secondary tq-btn--block" style="list-style:none">
                    <span aria-hidden="true"><?php echo tq_icon('plus', 18); ?></span>
                    رسالة جديدة
                </summary>
                <?php if (!$tq_allowed): ?>
                    <p class="tq-caption" style="margin-block-start:var(--tq-space-m)">
                        لا مستقبِل متاح بعد. سجّل في مادة ليصبح معلّمها ضمن من تراسلهم، أو تواصل مع الدعم الفنّي.
                    </p>
                <?php else: ?>
                    <form method="post" action="<?php echo base_url('home/my_messages/send_new'); ?>" style="margin-block-start:var(--tq-space-m)">
                        <div class="tq-field">
                            <label class="tq-field__label" for="tq-new-to">إلى</label>
                            <select class="tq-select" id="tq-new-to" name="receiver" required>
                                <?php foreach ($tq_allowed as $p): ?>
                                    <option value="<?php echo (int) $p['id']; ?>">
                                        <?php echo html_escape($p['name'] . ' — ' . $p['kind']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="tq-field__msg tq-field__hint">
                                المراسلة متاحة مع معلّميك والدعم فقط، ولا رسائل خاصّة بين الطلاب.
                            </span>
                        </div>
                        <div class="tq-field">
                            <label class="tq-field__label" for="tq-new-body">نصّ الرسالة</label>
                            <textarea class="tq-textarea" id="tq-new-body" name="message" required placeholder="اكتب سؤالك هنا…"></textarea>
                        </div>
                        <button class="tq-btn tq-btn--primary tq-btn--block" type="submit">إرسال</button>
                    </form>
                <?php endif; ?>
            </details>
        </section>

        <!-- العمود الثاني: نافذة الحوار -->
        <section class="tq-thread" aria-labelledby="tq-thread-h">
            <?php if (!$tq_open): ?>
                <div class="tq-thread__body" style="justify-content:center">
                    <h2 class="tq-sr" id="tq-thread-h">نافذة الحوار</h2>
                    <div class="tq-empty">
                        <div class="tq-empty__art tq-pastel tq-pastel--lilac" style="display:grid;place-items:center;border-radius:var(--tq-radius-pill)">
                            <span class="tq-pastel__icon" aria-hidden="true"><?php echo tq_icon('chat', 44); ?></span>
                        </div>
                        <h3 class="tq-empty__title">اختر محادثة لتبدأ</h3>
                        <p class="tq-empty__text">
                            حين تفتح محادثة تظهر هنا رسائلك ورسائل معلّمك مرتّبة بالتاريخ،
                            مع إيصال قراءة لكل رسالة أرسلتها ومرفقاتها جاهزة للتنزيل.
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <header class="tq-thread__head">
                    <img class="tq-avatar" src="<?php echo html_escape($tq_photo_of($tq_open['person'])); ?>"
                         alt="صورة <?php echo html_escape($tq_name_of($tq_open['person'])); ?>">
                    <span>
                        <span class="tq-strong" style="display:block;color:var(--tq-navy)" id="tq-thread-h">
                            <?php echo html_escape($tq_name_of($tq_open['person'])); ?>
                        </span>
                        <span class="tq-micro">
                            <?php echo !empty($tq_open['person']['is_instructor']) ? 'معلّم' : 'الدعم الفنّي'; ?>
                        </span>
                    </span>
                </header>

                <!-- شريط مثبَّت: تنبيه المنصّة الدائم لا رسالة عابرة -->
                <p class="tq-thread__pin">
                    <span aria-hidden="true"><?php echo tq_icon('lock', 18); ?></span>
                    محادثة مُشرَف عليها بين طالب ومعلّم. لا تشارك كلمة مرورك ولا بيانات الدفع في الرسائل.
                </p>

                <div class="tq-thread__body">
                    <?php if (!$tq_messages): ?>
                        <p class="tq-caption" style="text-align:center;margin:0">
                            لا رسائل في هذه المحادثة بعد — اكتب أوّل رسالة من الشريط أسفل الشاشة.
                        </p>
                    <?php else: ?>
                        <?php $tq_last_day = ''; ?>
                        <?php foreach ($tq_messages as $m): ?>
                            <?php
                            $ts  = (int) $m['timestamp'];
                            $day = date('Y-m-d', $ts);
                            $me  = ((int) $m['sender'] === $uid);
                            $body = strip_tags((string) $m['message']);
                            $attach = null;
                            if (preg_match('#(uploads/[^\s"\'<>]+)#u', (string) $m['message'], $mm)) {
                                $attach = $mm[1];
                            }
                            ?>
                            <?php if ($day !== $tq_last_day): ?>
                                <div class="tq-daysep"><span><?php echo $tq_daylabel($ts); ?></span></div>
                                <?php $tq_last_day = $day; ?>
                            <?php endif; ?>

                            <div class="tq-bubble tq-bubble--<?php echo $me ? 'me' : 'them'; ?>">
                                <p style="margin:0"><?php echo tq_iso(html_escape($body)); ?></p>

                                <?php if ($attach !== null): ?>
                                    <a class="tq-attach" href="<?php echo base_url($attach); ?>" download>
                                        <span class="tq-icon-box tq-pastel--rose" aria-hidden="true"><?php echo tq_icon('file', 20); ?></span>
                                        <span>
                                            <span class="tq-caption" style="display:block;color:var(--tq-navy)"><?php echo html_escape(basename($attach)); ?></span>
                                            <span class="tq-micro">اضغط للتنزيل</span>
                                        </span>
                                        <span style="margin-inline-start:auto;color:var(--tq-navy)" aria-hidden="true"><?php echo tq_icon('download', 18); ?></span>
                                    </a>
                                <?php endif; ?>

                                <span class="tq-bubble__meta">
                                    <span class="tq-bubble__time"><?php echo $tq_clock($ts); ?></span>
                                    <?php if ($me): ?>
                                        <?php $seen = ((int) $m['read_status'] === 1); ?>
                                        <span class="tq-bubble__seen<?php echo $seen ? '' : ' tq-bubble__seen--sent'; ?>" aria-hidden="true">
                                            <?php echo tq_icon('check', 14); ?><?php echo $seen ? tq_icon('check', 14) : ''; ?>
                                        </span>
                                        <span class="tq-sr"><?php echo $seen ? 'قُرئت' : 'أُرسلت'; ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="tq-thread__foot">
                    <form class="tq-composer" method="post"
                          action="<?php echo base_url('home/my_messages/send_reply/' . urlencode($tq_open['code'])); ?>">
                        <button class="tq-iconbtn" type="button" aria-label="إرفاق ملف">
                            <?php echo tq_icon('upload'); ?>
                        </button>
                        <span>
                            <label class="tq-sr" for="tq-reply">اكتب رسالتك</label>
                            <input class="tq-input" id="tq-reply" name="message" type="text" required placeholder="اكتب رسالتك هنا…">
                        </span>
                        <button class="tq-composer__send" type="submit" aria-label="إرسال الرسالة">
                            <?php echo tq_icon('play', 18); ?>
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </section>

    </div>

    <!-- العمود الثالث: معلومات المحادثة -->
    <aside class="tq-aside">
        <section class="tq-card tq-card--panel" aria-labelledby="tq-info-h">
            <div class="tq-card__head"><h2 class="tq-card__title" id="tq-info-h">معلومات المحادثة</h2></div>

            <?php if (!$tq_open): ?>
                <p class="tq-caption" style="margin:0">
                    اختر محادثة لترى صورة مُراسِلك، ومرفقاتها، وخيار كتمها أو حذفها.
                </p>
            <?php else: ?>
                <div style="display:grid;justify-items:center;gap:var(--tq-space-s);margin-block-end:var(--tq-space-xl)">
                    <img class="tq-avatar tq-avatar--lg" src="<?php echo html_escape($tq_photo_of($tq_open['person'])); ?>"
                         alt="صورة <?php echo html_escape($tq_name_of($tq_open['person'])); ?>">
                    <span class="tq-strong" style="color:var(--tq-navy)"><?php echo html_escape($tq_name_of($tq_open['person'])); ?></span>
                    <span class="tq-micro"><?php echo !empty($tq_open['person']['is_instructor']) ? 'معلّم' : 'الدعم الفنّي'; ?></span>
                    <a class="tq-btn tq-btn--secondary tq-btn--sm tq-btn--block"
                       href="<?php echo base_url('home/instructor_page/' . (int) $tq_open['other']); ?>">عرض الملف الشخصي</a>
                </div>

                <div>
                    <div class="tq-inforow">
                        <span class="tq-row">
                            <span class="tq-icon-box tq-pastel--mint" aria-hidden="true"><?php echo tq_icon('folder', 18); ?></span>
                            الوسائط والمرفقات
                        </span>
                        <span class="tq-micro">
                            <?php
                            $tq_attach_count = 0;
                            foreach ($tq_messages as $m) {
                                if (preg_match('#uploads/[^\s"\'<>]+#u', (string) $m['message'])) {
                                    $tq_attach_count++;
                                }
                            }
                            echo tq_num($tq_attach_count, 'tq-num--sm');
                            ?>
                        </span>
                    </div>

                    <div class="tq-inforow">
                        <span class="tq-row">
                            <span class="tq-icon-box tq-pastel--sky" aria-hidden="true"><?php echo tq_icon('search', 18); ?></span>
                            البحث في المحادثة
                        </span>
                        <span class="tq-micro"><?php echo tq_num(count($tq_messages), 'tq-num--sm'); ?> رسالة</span>
                    </div>

                    <!-- كتم بمفتاح تبديل -->
                    <div class="tq-inforow">
                        <label class="tq-row" for="tq-mute">
                            <span class="tq-icon-box tq-pastel--peach" aria-hidden="true"><?php echo tq_icon('bell', 18); ?></span>
                            كتم الإشعارات
                        </label>
                        <span class="tq-switch">
                            <input id="tq-mute" name="mute" type="checkbox" data-tq-pref="mute-<?php echo html_escape($tq_open['code']); ?>">
                            <span class="tq-switch__track" aria-hidden="true"></span>
                            <span class="tq-switch__knob" aria-hidden="true"></span>
                        </span>
                    </div>
                </div>

                <!-- حذف المحادثة: فعل خطر، ولذلك بتأكيد صريح لا بضغطة واحدة -->
                <details style="margin-block-start:var(--tq-space-xl)">
                    <summary class="tq-danger-link" style="cursor:pointer">
                        <span aria-hidden="true"><?php echo tq_icon('x', 18); ?></span>
                        حذف المحادثة
                    </summary>
                    <p class="tq-caption" style="margin-block:var(--tq-space-m)">
                        سيُحذف سجلّ هذه المحادثة من حسابك ولا يمكن التراجع. رسائل معلّمك تبقى في سجلّه.
                    </p>
                    <form method="post" action="<?php echo base_url('student/messages'); ?>">
                        <input type="hidden" name="thread" value="<?php echo html_escape($tq_open['code']); ?>">
                        <button class="tq-btn tq-btn--danger tq-btn--sm tq-btn--block" type="submit" name="action" value="delete_thread">
                            تأكيد الحذف
                        </button>
                    </form>
                </details>
            <?php endif; ?>
        </section>
    </aside>
</div>

<script>
/* تفضيل الكتم يُحفظ في المتصفّح — تفضيل عرض محلّي صريح، لا وعدٌ بحفظ خادمي
   غير موجود بعد. حين يوجد جدول تفضيلات المحادثة يُستبدل هذا بحفظ حقيقي. */
(function () {
  var boxes = document.querySelectorAll('[data-tq-pref]');
  Array.prototype.forEach.call(boxes, function (box) {
    var key = 'tq-pref-' + box.getAttribute('data-tq-pref');
    try { box.checked = localStorage.getItem(key) === '1'; } catch (e) {}
    box.addEventListener('change', function () {
      try { localStorage.setItem(key, box.checked ? '1' : '0'); } catch (e) {}
    });
  });
})();
</script>

<?php include 'portal_close.php'; ?>
