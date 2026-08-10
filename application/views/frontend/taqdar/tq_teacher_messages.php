<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * بوابة المعلم — الرسائل.
 *
 * ── لماذا وجدت هذه الشاشة ─────────────────────────────────────────────────
 * كان في المنصة نصف قناة: `Taqdar::students_message()` يرسل من شاشة
 * «طلابي» رسالة تصل إلى صندوق الطالب فعلا، والطالب يرد عليها من بوابته —
 * ثم لا شيء. لا صندوق للمعلم، ولا جرس في ترويسته (`portal_topbar.php`
 * كان يضبط `messages => null` للمعلم)، ولا بند في قائمته. فرد الطالب
 * يجلس في `message` لا يقرؤه أحد إلى الأبد. والمعلم الذي كتب «أخبرني إن
 * لم يتضح لك» طلب ردا لا يستطيع أن يراه.
 * ─────────────────────────────────────────────────────────────────────────
 *
 * القاعدة الحاكمة لبوابة المعلم كلها تسري هنا كما تسري في بقية شاشاته:
 * النطاق يفرض في طبقة الاستعلام لا في الواجهة. فقائمة من يراسلهم المعلم
 * تبنى من `enrol` مقيدا بكورساته — بالصيغة نفسها التي يفرضها
 * `Taqdar::students_message()` (`creator` أو `FIND_IN_SET`) — والخادم
 * يعيد الفحص عند الإرسال ولا يكتفي بأن الخيار غير معروض.
 *
 * والحفظ يمر بـ`crud_model` كما يمر من بوابة الطالب: كاتب واحد للجدول
 * المشترك، فلا يتفرق شكل الرسالة بين بوابتين.
 *
 * والأنماط ودوال العرض في `tq_chat_styles.php` — مشتركة مع صندوق الطالب،
 * فالمحادثة الواحدة شكل واحد في المنصة كلها.
 */

$tq_nav   = 'messages';
$tq_role  = 'teacher';
$tq_title = 'الرسائل';
$tq_sub   = 'ردود طلابك على ما كتبته لهم، ومراسلة الإدارة';
$tq_icon  = 'chat';

$uid = (int) $this->session->userdata('user_id');

/** نطاق المعلم: طلاب كورساته وحدهم — يبنى مرة ويستعمل للعرض وللفحص معا. */
$tq_scope_students = $this->db->query(
    'SELECT DISTINCT u.id, u.first_name, u.last_name
       FROM `enrol` e
       JOIN `course` c ON c.id = e.course_id
       JOIN `users`  u ON u.id = e.user_id
      WHERE c.creator = ? OR FIND_IN_SET(?, c.user_id) > 0
      ORDER BY u.first_name ASC',
    [$uid, $uid]
)->result_array();

$tq_scope_ids = array_map('intval', array_column($tq_scope_students, 'id'));

/** الإدارة قناة الدعم للمعلم كما هي للطالب. */
$tq_admin = $this->db->select('id, first_name, last_name')
    ->where('role_id', 1)->order_by('id', 'ASC')->limit(1)
    ->get('users')->row_array();
$tq_admin_id = $tq_admin ? (int) $tq_admin['id'] : 0;

/* ---- حذف المحادثة: قبل أي إخراج، وبفحص ملكية على الخادم -------------- */
if ($this->input->post('action') === 'delete_thread') {
    $tq_del = (string) $this->input->post('thread', true);
    $tq_own = $this->db->where('message_thread_code', $tq_del)
        ->group_start()->where('sender', (string) $uid)->or_where('receiver', (string) $uid)->group_end()
        ->count_all_results('message_thread');
    if ($tq_del !== '' && $tq_own > 0) {
        $this->db->where('message_thread_code', $tq_del)->delete('message');
        $this->db->where('message_thread_code', $tq_del)->delete('message_thread');
        $this->session->set_flashdata('flash_message', 'حذفت المحادثة من صندوقك.');
    }
    redirect(site_url('teacher/messages'), 'location', 302);
}

/* ---- الإرسال: رسالة جديدة أو رد ------------------------------------- */
$tq_send = (string) $this->input->post('action');
if ($tq_send === 'send_new' || $tq_send === 'send_reply') {

    $tq_body = trim((string) $this->input->post('message'));
    $tq_to   = 'teacher/messages';

    if ($tq_body === '') {
        $this->session->set_flashdata('error_message', 'اكتب نص الرسالة قبل الإرسال.');
        redirect(site_url($tq_to), 'location', 302);
    }

    if ($tq_send === 'send_new') {
        /* النطاق يفرض هنا لا في القائمة: من بدل معرفا في النموذج لا يمر. */
        $tq_rcv = (int) $this->input->post('receiver');
        $tq_ok  = in_array($tq_rcv, $tq_scope_ids, true) || ($tq_admin_id && $tq_rcv === $tq_admin_id);

        if (!$tq_ok) {
            $this->session->set_flashdata('error_message', 'لا ترسل الرسائل إلا إلى طلاب كورساتك أو إلى الإدارة.');
            redirect(site_url($tq_to), 'location', 302);
        }

        $tq_code = $this->crud_model->send_new_private_message();
        $this->session->set_flashdata('flash_message', 'وصلت رسالتك.');
        redirect(site_url($tq_to . '?t=' . urlencode((string) $tq_code)), 'location', 302);
    }

    $tq_code = (string) $this->input->post('thread', true);
    $tq_mine = $this->db->where('message_thread_code', $tq_code)
        ->group_start()->where('sender', (string) $uid)->or_where('receiver', (string) $uid)->group_end()
        ->count_all_results('message_thread');

    if ($tq_code === '' || $tq_mine < 1) {
        $this->session->set_flashdata('error_message', 'هذه المحادثة ليست لك.');
        redirect(site_url($tq_to), 'location', 302);
    }

    $this->crud_model->send_reply_message($tq_code);
    $this->session->set_flashdata('flash_message', 'أرسل ردك.');
    redirect(site_url($tq_to . '?t=' . urlencode($tq_code)), 'location', 302);
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
        'student' => in_array($other, $tq_scope_ids, true),
    ];
}

/* ---- التصفية: تبويبات وبحث يعملان على الخادم ------------------------- */
$tq_filter = $this->input->get('filter', true);
$tq_filter = in_array($tq_filter, ['unread', 'students', 'admin'], true) ? $tq_filter : 'all';
$tq_query  = trim((string) $this->input->get('q', true));

$tq_all_threads = $tq_threads;
$tq_threads = array_values(array_filter($tq_threads, static function ($t) use ($tq_filter, $tq_query) {
    if ($tq_filter === 'unread'   && $t['unread'] < 1) return false;
    if ($tq_filter === 'students' && !$t['student'])   return false;
    if ($tq_filter === 'admin'    && (int) ($t['person']['role_id'] ?? 0) !== 1) return false;
    if ($tq_query !== '') {
        $hay = ($t['person']['first_name'] ?? '') . ' ' . ($t['person']['last_name'] ?? '') . ' ' . ($t['last']['message'] ?? '');
        if (mb_stripos($hay, $tq_query) === false) return false;
    }
    return true;
}));

$tq_filters = [
    'all'      => 'الكل',
    'unread'   => 'غير مقروءة',
    'students' => 'طلابي',
    'admin'    => 'الإدارة',
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

/* فتحها يجعلها مقروءة — وإلا بقي جرس الترويسة يحمل الرقم نفسه إلى الأبد. */
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

/** من يجوز للمعلم أن يبدأ معه محادثة. */
$tq_allowed = [];
foreach ($tq_scope_students as $s) {
    $tq_allowed[] = [
        'id'   => (int) $s['id'],
        'name' => trim($s['first_name'] . ' ' . $s['last_name']) ?: 'طالب',
        'kind' => 'طالب',
    ];
}
if ($tq_admin_id) {
    $tq_allowed[] = ['id' => $tq_admin_id, 'name' => 'إدارة المنصة', 'kind' => 'إدارة'];
}

/** صفة المحدث كما تكتب في الترويسة والشريط الجانبي. */
$tq_kind_of = static function ($t) {
    if ((int) ($t['person']['role_id'] ?? 0) === 1) return 'إدارة المنصة';
    if (!empty($t['person']['is_instructor']))      return 'معلم';
    return $t['student'] ? 'طالب في كورساتك' : 'طالب';
};

$tq_ok  = $this->session->flashdata('flash_message');
$tq_err = $this->session->flashdata('error_message');

include 'portal_open.php';
include 'tq_chat_styles.php';
?>

<?php if ($tq_ok): ?>
    <p class="tq-card tq-section" role="status" style="border-inline-start:4px solid var(--tq-mint-ink)">
        <?php echo tq_iso(html_escape($tq_ok)); ?>
    </p>
<?php endif; ?>
<?php if ($tq_err): ?>
    <p class="tq-card tq-section" role="alert" style="border-inline-start:4px solid var(--tq-rose-ink)">
        <?php echo tq_iso(html_escape($tq_err)); ?>
    </p>
<?php endif; ?>

<div class="tq-cols">
    <div class="tq-chatwrap">

        <!-- العمود الأول: قائمة المحادثات -->
        <section class="tq-convlist" aria-labelledby="tq-conv-h">
            <h2 class="tq-sr" id="tq-conv-h">قائمة المحادثات</h2>

            <nav class="tq-tabs" aria-label="تصفية المحادثات" style="margin-block-end:0;gap:var(--tq-space-l)">
                <?php foreach ($tq_filters as $key => $label): ?>
                    <a class="tq-tab"
                       href="<?php echo base_url('teacher/messages') . ($key === 'all' ? '' : '?filter=' . $key); ?>"
                       <?php echo tq_active($key, $tq_filter); ?>>
                        <?php echo html_escape($label); ?>
                        <?php if ($key === 'unread' && $tq_unread_total > 0): ?>
                            <span class="tq-conv__count"><?php echo TQ_LRI . $tq_unread_total . TQ_PDI; ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <form class="tq-convsearch" role="search" method="get" action="<?php echo base_url('teacher/messages'); ?>">
                <label class="tq-sr" for="tq-conv-q">ابحث في المحادثات</label>
                <input class="tq-input" id="tq-conv-q" name="q" type="search" placeholder="ابحث باسم الطالب أو نص الرسالة…"
                       value="<?php echo html_escape($tq_query); ?>">
            </form>

            <?php if (!$tq_threads): ?>
                <div class="tq-empty" style="padding-block:var(--tq-space-xl)">
                    <div class="tq-empty__art tq-pastel tq-pastel--sky" style="inline-size:88px;block-size:88px;display:grid;place-items:center;border-radius:var(--tq-radius-pill)">
                        <span class="tq-pastel__icon" aria-hidden="true"><?php echo tq_icon('chat', 36); ?></span>
                    </div>
                    <h3 class="tq-empty__title" style="font:var(--tq-type-bodyStrong)">لا محادثات بعد</h3>
                    <p class="tq-empty__text tq-caption">
                        ابدأ من «طلابي»: رسالة قصيرة لمن انقطع أو تعثر تعيده قبل أن يبتعد.
                        وكل محادثة تظهر هنا بآخر رسالة ووقتها.
                    </p>
                    <a class="tq-btn tq-btn--secondary tq-btn--sm" href="<?php echo base_url('teacher/students'); ?>">طلابي</a>
                </div>
            <?php else: ?>
                <ul>
                    <?php foreach ($tq_threads as $t): ?>
                        <li>
                            <a class="tq-conv" href="<?php echo base_url('teacher/messages?t=' . urlencode($t['code'])); ?>"
                               <?php echo ($tq_open && $t['code'] === $tq_open['code']) ? ' aria-current="page"' : ''; ?>>
                                <span class="tq-conv__ava">
                                    <img class="tq-avatar" src="<?php echo html_escape(tq_chat_photo($t['person'])); ?>"
                                         alt="صورة <?php echo html_escape(tq_chat_name($t['person'])); ?>">
                                    <span class="tq-conv__on" aria-hidden="true"></span>
                                </span>
                                <span>
                                    <span class="tq-conv__name"><?php echo html_escape(tq_chat_name($t['person'])); ?></span>
                                    <span class="tq-conv__last"><?php echo html_escape(mb_substr(strip_tags((string) ($t['last']['message'] ?? '')), 0, 60)); ?></span>
                                </span>
                                <span class="tq-conv__meta">
                                    <span class="tq-micro"><?php echo tq_chat_clock($t['ts']); ?></span>
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
                        لا طالب مسجل في كورساتك بعد، فلا أحد تراسله من هنا.
                    </p>
                <?php else: ?>
                    <form method="post" action="<?php echo base_url('teacher/messages'); ?>" style="margin-block-start:var(--tq-space-m)">
                        <?php echo tq_csrf(); ?>
                        <input type="hidden" name="action" value="send_new">
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
                                طلاب كورساتك وحدهم، ومعهم الإدارة. والخادم يعيد فحص النطاق عند الإرسال.
                            </span>
                        </div>
                        <div class="tq-field">
                            <label class="tq-field__label" for="tq-new-body">نص الرسالة</label>
                            <textarea class="tq-textarea" id="tq-new-body" name="message" required
                                      placeholder="سطران يكفيان: ما الذي لاحظته، وما الخطوة التالية."></textarea>
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
                            حين تفتح محادثة تظهر هنا رسائلك ورسائل طالبك مرتبة بالتاريخ،
                            ومعها إيصال قراءة لكل رسالة أرسلتها.
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <header class="tq-thread__head">
                    <img class="tq-avatar" src="<?php echo html_escape(tq_chat_photo($tq_open['person'])); ?>"
                         alt="صورة <?php echo html_escape(tq_chat_name($tq_open['person'])); ?>">
                    <span style="flex:1;min-inline-size:0">
                        <span class="tq-strong" style="display:block;color:var(--tq-navy)" id="tq-thread-h">
                            <?php echo html_escape(tq_chat_name($tq_open['person'])); ?>
                        </span>
                        <span class="tq-micro"><?php echo html_escape($tq_kind_of($tq_open)); ?></span>
                    </span>
                    <?php if ($tq_open['student']): ?>
                        <a class="tq-btn tq-btn--ghost tq-btn--sm"
                           href="<?php echo base_url('teacher/students'); ?>#student-<?php echo (int) $tq_open['other']; ?>">
                            تقدمه
                        </a>
                    <?php endif; ?>
                </header>

                <p class="tq-thread__pin">
                    <span aria-hidden="true"><?php echo tq_icon('lock', 18); ?></span>
                    محادثة مشرف عليها بين معلم وطالب. لا تطلب بيانات دفع ولا تشارك كلمة مرورك في الرسائل.
                </p>

                <div class="tq-thread__body">
                    <?php if (!$tq_messages): ?>
                        <p class="tq-caption" style="text-align:center;margin:0">
                            لا رسائل في هذه المحادثة بعد — اكتب أول رسالة من الشريط أسفل الشاشة.
                        </p>
                    <?php else: ?>
                        <?php $tq_last_day = ''; ?>
                        <?php foreach ($tq_messages as $m): ?>
                            <?php
                            $ts   = (int) $m['timestamp'];
                            $day  = date('Y-m-d', $ts);
                            $me   = ((int) $m['sender'] === $uid);
                            $body = strip_tags((string) $m['message']);
                            $attach = null;
                            if (preg_match('#(uploads/[^\s"\'<>]+)#u', (string) $m['message'], $mm)) {
                                $attach = $mm[1];
                            }
                            ?>
                            <?php if ($day !== $tq_last_day): ?>
                                <div class="tq-daysep"><span><?php echo tq_chat_daylabel($ts); ?></span></div>
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
                                    <span class="tq-bubble__time"><?php echo tq_chat_clock($ts); ?></span>
                                    <?php if ($me): ?>
                                        <?php $seen = ((int) $m['read_status'] === 1); ?>
                                        <span class="tq-bubble__seen<?php echo $seen ? '' : ' tq-bubble__seen--sent'; ?>" aria-hidden="true">
                                            <?php echo tq_icon('check', 14); ?><?php echo $seen ? tq_icon('check', 14) : ''; ?>
                                        </span>
                                        <span class="tq-sr"><?php echo $seen ? 'قرئت' : 'أرسلت'; ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="tq-thread__foot">
                    <form class="tq-composer" method="post" action="<?php echo base_url('teacher/messages'); ?>">
                        <?php echo tq_csrf(); ?>
                        <input type="hidden" name="action" value="send_reply">
                        <input type="hidden" name="thread" value="<?php echo html_escape($tq_open['code']); ?>">
                        <span>
                            <label class="tq-sr" for="tq-reply">اكتب ردك</label>
                            <input class="tq-input" id="tq-reply" name="message" type="text" required placeholder="اكتب ردك هنا…">
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
                    اختر محادثة لترى صورة مراسلك وعدد رسائلها وخيار حذفها.
                </p>
            <?php else: ?>
                <div style="display:grid;justify-items:center;gap:var(--tq-space-s);margin-block-end:var(--tq-space-xl)">
                    <img class="tq-avatar tq-avatar--lg" src="<?php echo html_escape(tq_chat_photo($tq_open['person'])); ?>"
                         alt="صورة <?php echo html_escape(tq_chat_name($tq_open['person'])); ?>">
                    <span class="tq-strong" style="color:var(--tq-navy)"><?php echo html_escape(tq_chat_name($tq_open['person'])); ?></span>
                    <span class="tq-micro"><?php echo html_escape($tq_kind_of($tq_open)); ?></span>
                </div>

                <div>
                    <div class="tq-inforow">
                        <span class="tq-row">
                            <span class="tq-icon-box tq-pastel--sky" aria-hidden="true"><?php echo tq_icon('chat', 18); ?></span>
                            رسائل المحادثة
                        </span>
                        <span class="tq-micro"><?php echo tq_num(count($tq_messages), 'tq-num--sm'); ?></span>
                    </div>

                    <div class="tq-inforow">
                        <span class="tq-row">
                            <span class="tq-icon-box tq-pastel--mint" aria-hidden="true"><?php echo tq_icon('folder', 18); ?></span>
                            المرفقات
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
                </div>

                <?php /* الحذف فعل خطر: حوار التأكيد الموحد في البوابات لا ضغطة واحدة. */ ?>
                <form method="post" action="<?php echo base_url('teacher/messages'); ?>"
                      style="margin-block-start:var(--tq-space-xl)"
                      data-tq-confirm-title="حذف محادثتك مع <?php echo html_escape(tq_chat_name($tq_open['person'])); ?>؟"
                      data-tq-confirm="يحذف سجل المحادثة من الطرفين ولا يمكن التراجع."
                      data-tq-confirm-note="ما كتبته من ملاحظات على المحاولات يبقى في صفحة التصحيح، فهو ليس من الرسائل."
                      data-tq-confirm-ok="أحذف المحادثة"
                      data-tq-confirm-tone="danger">
                    <?php echo tq_csrf(); ?>
                    <input type="hidden" name="thread" value="<?php echo html_escape($tq_open['code']); ?>">
                    <button class="tq-btn tq-btn--danger tq-btn--sm tq-btn--block" type="submit" name="action" value="delete_thread">
                        <span aria-hidden="true"><?php echo tq_icon('x', 18); ?></span>
                        حذف المحادثة
                    </button>
                </form>
            <?php endif; ?>
        </section>

        <div class="tq-pastel tq-pastel--peach">
            <span class="tq-pastel__label tq-micro">متى تكتب</span>
            <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0">
                رسالة قصيرة عند أول انقطاع أنفع من تقرير بعد شهر. وشاشة «طلابي» تقول لك
                من يستحقها اليوم.
            </p>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
