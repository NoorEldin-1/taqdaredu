<?php
/**
 * رسائلي — بوابة الطالب.
 *
 * ── قاعدة ملزمة لهذه الشاشة ──────────────────────────────────────────────
 * المراسلة هنا بين الطالب ومعلمه، أو الدعم الفني، أو مجموعة مادة يشرف
 * عليها معلم — ولا رسائل خاصة بين الطلاب بعضهم ببعض، لأن أغلب مستخدمي
 * المنصة قاصرون، وقناة خاصة بين قاصرين بلا إشراف باب لا يفتح.
 * قائمة المستقبلين أدناه مبنية على هذا الأساس: معلمو موادك المسجلة
 * وحساب الدعم فقط. وهذا نصف القاعدة؛ نصفها الآخر أن يفرضها الخادم عند
 * الإرسال ولا يكتفي بإخفاء الخيار — إخفاء الزر ليس صلاحية.
 * ─────────────────────────────────────────────────────────────────────────
 */

$tq_nav   = 'messages';
$tq_role  = 'student';
$tq_title = t('رسائلي');
$tq_sub   = t('تواصل مع معلميك والدعم الفني بسهولة');
$tq_icon  = 'chat';

$uid = (int) $this->session->userdata('user_id');

/* القواعد كلها في `Taqdar_student_model`: الملكية ونطاق المستقبل
   وبناء المحادثات — والواجهة (`Api_v1`) تنادي الدوال نفسها، فلا يرسل
   التطبيق رسالة يرفضها الموقع ولا العكس. والحفظ يبقى في `crud_model`:
   هو المشترك مع مسارات LMS الأصلية ولا يكرر. */
$this->load->model('taqdar_student_model', 'tq_stu');

/* ---- حذف المحادثة: ينفذ قبل أي إخراج، وبتحقق ملكية على الخادم ------
   زر الخطر يجب أن يفعل ما يقوله. والتحقق في النموذج لا في الواجهة. */
if ($this->input->post('action') === 'delete_thread') {
    $this->tq_stu->delete_thread($uid, (string) $this->input->post('thread', true));
    redirect(site_url('student/messages'), 'location', 302);
}

/* ---- إرسال رسالة: جديدة أو ردا ---------------------------------------
 *
 * كان النموذجان يرسلان إلى `home/my_messages/send_new` و`.../send_reply`،
 * وهما مساران من Academy يحفظان الرسالة فعلا ثم **يحولان إلى
 * `home/my_messages/read_message/<الرمز>`** — وهي شاشة Academy التي لا قالب
 * لها في ثيم تقدر. فيرد الغلاف `show_404()` بعد أن يكون قد طبع ترويسة
 * الصفحة: يضغط الطالب «إرسال» فيرى نصف صفحة ثم «404 Page Not Found»،
 * ورسالته قد وصلت وهو لا يدري. (وهو عطل `home/my_wishlist` نفسه.)
 *
 * وهذه الطبقة تضيف ما كان ناقصا في `crud_model`:
 *
 *   ١ — **نطاق المستقبل.** `send_new_private_message()` تقرأ `receiver` من
 *       الطلب ولا تفحصه، والشاشة تقول للطالب «المراسلة متاحة مع معلميك
 *       والدعم فقط». فكان القيد وعدا في العرض لا شرطا في الخادم.
 *   ٢ — **ملكية الخيط.** `send_reply_message()` تقرأ الخيط برمزه وتستنتج
 *       الطرف الآخر، ولا تتحقق أن المرسل طرف فيه أصلا — فمن خمن رمزا حقن
 *       رسالة في محادثة غيره.
 *   ٣ — **الوجهة.** يعاد إلى محادثته في البوابة، لا إلى 404.
 */
$tq_send = (string) $this->input->post('action');
if ($tq_send === 'send_new' || $tq_send === 'send_reply') {

    $tq_body = trim((string) $this->input->post('message'));
    $tq_to   = 'student/messages';

    if ($tq_body === '') {
        $this->session->set_flashdata('error_message', t('اكتب نص الرسالة قبل الإرسال.'));
        redirect(site_url($tq_to), 'location', 302);
    }

    if ($tq_send === 'send_new') {
        if (!$this->tq_stu->may_message($uid, (int) $this->input->post('receiver'))) {
            $this->session->set_flashdata('error_message', t('لا ترسل الرسائل إلا إلى معلمي موادك أو الدعم الفني.'));
            redirect(site_url($tq_to), 'location', 302);
        }

        $tq_code = $this->crud_model->send_new_private_message();
        $this->session->set_flashdata('flash_message', t('وصلت رسالتك.'));
        redirect(site_url($tq_to . '?t=' . urlencode((string) $tq_code)), 'location', 302);
    }

    $tq_code = (string) $this->input->post('thread', true);
    if (!$this->tq_stu->owns_thread($uid, $tq_code)) {
        $this->session->set_flashdata('error_message', t('هذه المحادثة ليست لك.'));
        redirect(site_url($tq_to), 'location', 302);
    }

    $this->crud_model->send_reply_message($tq_code);
    $this->session->set_flashdata('flash_message', t('أرسل ردك.'));
    redirect(site_url($tq_to . '?t=' . urlencode($tq_code)), 'location', 302);
}

/* ---- المحادثات ------------------------------------------------------- */
$tq_threads = $this->tq_stu->threads($uid);

/* ---- تصفية القائمة: تبويبات وبحث يعملان على الخادم فعلا -------------- */
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
    'all'      => t('الكل'),
    'unread'   => t('غير مقروءة'),
    'teachers' => t('المعلمون'),
    'support'  => t('الدعم'),
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

/* فتح المحادثة يجعلها مقروءة — كما يتوقع من فتحها فعلا. والنموذج يشترط
   الملكية في `UPDATE` نفسه، فرمز مخمن لا يمسح «غير مقروء» عن غيره. */
if ($tq_open && $tq_open_code === $tq_open['code'] && $tq_open['unread'] > 0) {
    $this->tq_stu->read_thread($uid, $tq_open['code']);
    $tq_open['unread'] = 0;
}

$tq_messages = $tq_open ? $this->tq_stu->messages($uid, $tq_open['code']) : [];

/* ---- المستقبلون المسموح بهم: معلمو موادك + الدعم -------------------
   من `messageable()` نفسها التي يفحص بها الحفظ أعلاه: قائمتان تبنيان
   من استعلامين تفترقان عند أول تعديل، فيعرض المنتقي حسابا يرده الحارس
   — ويقرأ الطالب «لا ترسل الرسائل إلا إلى معلمي موادك» عن اسم اختاره
   من قائمة عرضناها نحن. */
$tq_allowed = [];
foreach ($this->tq_stu->messageable($uid) as $p) {
    $tq_support = ((int) ($p['role_id'] ?? 0) === 1) && empty($p['is_instructor']);
    $tq_allowed[] = [
        'id'   => (int) $p['id'],
        'name' => $tq_support ? t('الدعم الفني')
                              : trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')),
        'kind' => $tq_support ? t('دعم') : t('معلم'),
    ];
}

/* ---- أدوات عرض ------------------------------------------------------
   في `tq_chat_styles.php` مع أنماط المحادثة: صندوق المعلم يعرض الشيء
   نفسه، ونسختان من دالة الساعة تعنيان ساعتين مختلفتين يوما ما. */
$tq_photo_of = 'tq_chat_photo';
$tq_name_of  = 'tq_chat_name';
$tq_clock    = 'tq_chat_clock';
$tq_daylabel = 'tq_chat_daylabel';

include 'portal_open.php';
include 'tq_chat_styles.php';
?>


<div class="tq-cols">
    <div class="tq-chatwrap">

        <!-- العمود الأول: قائمة المحادثات -->
        <section class="tq-convlist" aria-labelledby="tq-conv-h">
            <h2 class="tq-sr" id="tq-conv-h"><?php echo t('قائمة المحادثات'); ?></h2>

            <nav class="tq-tabs" aria-label="<?php echo te('تصفية المحادثات'); ?>" style="margin-block-end:0;gap:var(--tq-space-l)">
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
                <label class="tq-sr" for="tq-conv-q"><?php echo t('ابحث في المحادثات'); ?></label>
                <input class="tq-input" id="tq-conv-q" name="q" type="search" placeholder="<?php echo te('ابحث في المحادثات…'); ?>"
                       value="<?php echo html_escape($tq_query); ?>">
            </form>

            <?php if (!$tq_threads): ?>
                <div class="tq-empty" style="padding-block:var(--tq-space-xl)">
                    <div class="tq-empty__art tq-pastel tq-pastel--sky" style="inline-size:88px;block-size:88px;display:grid;place-items:center;border-radius:var(--tq-radius-pill)">
                        <span class="tq-pastel__icon" aria-hidden="true"><?php echo tq_icon('chat', 36); ?></span>
                    </div>
                    <h3 class="tq-empty__title" style="font:var(--tq-type-bodyStrong)"><?php echo t('لا محادثات بعد'); ?></h3>
                    <p class="tq-empty__text tq-caption">
                        <?php echo t('اسأل معلمك عما لم يتضح لك في الدرس، أو راسل الدعم الفني إن واجهتك مشكلة. كل محادثة ستظهر هنا بآخر رسالة ووقتها.'); ?>
                    </p>
                </div>
            <?php else: ?>
                <ul>
                    <?php foreach ($tq_threads as $t): ?>
                        <li>
                            <a class="tq-conv" href="<?php echo base_url('student/messages?t=' . urlencode($t['code'])); ?>"
                               <?php echo ($tq_open && $t['code'] === $tq_open['code']) ? ' aria-current="page"' : ''; ?>>
                                <span class="tq-conv__ava">
                                    <img class="tq-avatar" src="<?php echo html_escape($tq_photo_of($t['person'])); ?>"
                                         alt="<?php echo te('صورة ____', array(html_escape($tq_name_of($t['person'])))); ?>">
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
                                        <span class="tq-sr"><?php echo t('رسائل غير مقروءة'); ?></span>
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
                    <?php echo t('رسالة جديدة'); ?>
                </summary>
                <?php if (!$tq_allowed): ?>
                    <p class="tq-caption" style="margin-block-start:var(--tq-space-m)">
                        <?php echo t('لا مستقبل متاح بعد. سجل في مادة ليصبح معلمها ضمن من تراسلهم، أو تواصل مع الدعم الفني.'); ?>
                    </p>
                <?php else: ?>
                    <form method="post" action="<?php echo base_url('student/messages'); ?>" style="margin-block-start:var(--tq-space-m)">
                        <input type="hidden" name="action" value="send_new">
                        <div class="tq-field">
                            <label class="tq-field__label" for="tq-new-to"><?php echo t('إلى'); ?></label>
                            <select class="tq-select" id="tq-new-to" name="receiver" required>
                                <?php foreach ($tq_allowed as $p): ?>
                                    <option value="<?php echo (int) $p['id']; ?>">
                                        <?php echo html_escape($p['name'] . ' — ' . $p['kind']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="tq-field__msg tq-field__hint">
                                <?php echo t('المراسلة متاحة مع معلميك والدعم فقط، ولا رسائل خاصة بين الطلاب.'); ?>
                            </span>
                        </div>
                        <div class="tq-field">
                            <label class="tq-field__label" for="tq-new-body"><?php echo t('نص الرسالة'); ?></label>
                            <textarea class="tq-textarea" id="tq-new-body" name="message" required placeholder="<?php echo te('اكتب سؤالك هنا…'); ?>"></textarea>
                        </div>
                        <button class="tq-btn tq-btn--primary tq-btn--block" type="submit"><?php echo t('إرسال'); ?></button>
                    </form>
                <?php endif; ?>
            </details>
        </section>

        <!-- العمود الثاني: نافذة الحوار -->
        <section class="tq-thread" aria-labelledby="tq-thread-h">
            <?php if (!$tq_open): ?>
                <div class="tq-thread__body" style="justify-content:center">
                    <h2 class="tq-sr" id="tq-thread-h"><?php echo t('نافذة الحوار'); ?></h2>
                    <div class="tq-empty">
                        <div class="tq-empty__art tq-pastel tq-pastel--lilac" style="display:grid;place-items:center;border-radius:var(--tq-radius-pill)">
                            <span class="tq-pastel__icon" aria-hidden="true"><?php echo tq_icon('chat', 44); ?></span>
                        </div>
                        <h3 class="tq-empty__title"><?php echo t('اختر محادثة لتبدأ'); ?></h3>
                        <p class="tq-empty__text">
                            <?php echo t('حين تفتح محادثة تظهر هنا رسائلك ورسائل معلمك مرتبة بالتاريخ، مع إيصال قراءة لكل رسالة أرسلتها ومرفقاتها جاهزة للتنزيل.'); ?>
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <header class="tq-thread__head">
                    <img class="tq-avatar" src="<?php echo html_escape($tq_photo_of($tq_open['person'])); ?>"
                         alt="<?php echo te('صورة ____', array(html_escape($tq_name_of($tq_open['person'])))); ?>">
                    <span>
                        <span class="tq-strong" style="display:block;color:var(--tq-navy)" id="tq-thread-h">
                            <?php echo html_escape($tq_name_of($tq_open['person'])); ?>
                        </span>
                        <span class="tq-micro">
                            <?php echo !empty($tq_open['person']['is_instructor']) ? t('معلم') : t('الدعم الفني'); ?>
                        </span>
                    </span>
                </header>

                <!-- شريط مثبت: تنبيه المنصة الدائم لا رسالة عابرة -->
                <p class="tq-thread__pin">
                    <span aria-hidden="true"><?php echo tq_icon('lock', 18); ?></span>
                    <?php echo t('محادثة مشرف عليها بين طالب ومعلم. لا تشارك كلمة مرورك ولا بيانات الدفع في الرسائل.'); ?>
                </p>

                <div class="tq-thread__body">
                    <?php if (!$tq_messages): ?>
                        <p class="tq-caption" style="text-align:center;margin:0">
                            <?php echo t('لا رسائل في هذه المحادثة بعد — اكتب أول رسالة من الشريط أسفل الشاشة.'); ?>
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
                                            <span class="tq-micro"><?php echo t('اضغط للتنزيل'); ?></span>
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
                                        <span class="tq-sr"><?php echo $seen ? t('قرئت') : t('أرسلت'); ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="tq-thread__foot">
                    <form class="tq-composer" method="post"
                          action="<?php echo base_url('student/messages'); ?>">
                        <input type="hidden" name="action" value="send_reply">
                        <input type="hidden" name="thread" value="<?php echo html_escape($tq_open['code']); ?>">
                        <?php /* لا زر «إرفاق ملف»: كان `type="button"` بلا معالج ولا حقل ملف،
                                 و`message` جدول بلا عمود مرفق و`send_reply_message()` لا تقرأ
                                 رفعا. فالزر يفتح شهية الطالب لإرسال صورة سؤاله ثم لا يفعل شيئا
                                 — والوعد الكاذب أسوأ من غيابه. */ ?>
                        <span>
                            <label class="tq-sr" for="tq-reply"><?php echo t('اكتب رسالتك'); ?></label>
                            <input class="tq-input" id="tq-reply" name="message" type="text" required placeholder="<?php echo te('اكتب رسالتك هنا…'); ?>">
                        </span>
                        <button class="tq-composer__send" type="submit" aria-label="<?php echo te('إرسال الرسالة'); ?>">
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
            <div class="tq-card__head"><h2 class="tq-card__title" id="tq-info-h"><?php echo t('معلومات المحادثة'); ?></h2></div>

            <?php if (!$tq_open): ?>
                <p class="tq-caption" style="margin:0">
                    <?php echo t('اختر محادثة لترى صورة مراسلك، ومرفقاتها، وخيار كتمها أو حذفها.'); ?>
                </p>
            <?php else: ?>
                <div style="display:grid;justify-items:center;gap:var(--tq-space-s);margin-block-end:var(--tq-space-xl)">
                    <img class="tq-avatar tq-avatar--lg" src="<?php echo html_escape($tq_photo_of($tq_open['person'])); ?>"
                         alt="<?php echo te('صورة ____', array(html_escape($tq_name_of($tq_open['person'])))); ?>">
                    <span class="tq-strong" style="color:var(--tq-navy)"><?php echo html_escape($tq_name_of($tq_open['person'])); ?></span>
                    <span class="tq-micro"><?php echo !empty($tq_open['person']['is_instructor']) ? t('معلم') : t('الدعم الفني'); ?></span>
                    <a class="tq-btn tq-btn--secondary tq-btn--sm tq-btn--block"
                       href="<?php echo base_url('home/instructor_page/' . (int) $tq_open['other']); ?>"><?php echo t('عرض الملف الشخصي'); ?></a>
                </div>

                <div>
                    <div class="tq-inforow">
                        <span class="tq-row">
                            <span class="tq-icon-box tq-pastel--mint" aria-hidden="true"><?php echo tq_icon('folder', 18); ?></span>
                            <?php echo t('الوسائط والمرفقات'); ?>
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
                            <?php echo t('البحث في المحادثة'); ?>
                        </span>
                        <span class="tq-micro"><?php echo tq_num(count($tq_messages), 'tq-num--sm'); ?> <?php echo t('رسالة'); ?></span>
                    </div>

                    <!-- كتم بمفتاح تبديل -->
                    <div class="tq-inforow">
                        <label class="tq-row" for="tq-mute">
                            <span class="tq-icon-box tq-pastel--peach" aria-hidden="true"><?php echo tq_icon('bell', 18); ?></span>
                            <?php echo t('كتم الإشعارات'); ?>
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
                        <?php echo t('حذف المحادثة'); ?>
                    </summary>
                    <p class="tq-caption" style="margin-block:var(--tq-space-m)">
                        <?php echo t('سيحذف سجل هذه المحادثة من حسابك ولا يمكن التراجع. رسائل معلمك تبقى في سجله.'); ?>
                    </p>
                    <form method="post" action="<?php echo base_url('student/messages'); ?>">
                        <input type="hidden" name="thread" value="<?php echo html_escape($tq_open['code']); ?>">
                        <button class="tq-btn tq-btn--danger tq-btn--sm tq-btn--block" type="submit" name="action" value="delete_thread">
                            <?php echo t('تأكيد الحذف'); ?>
                        </button>
                    </form>
                </details>
            <?php endif; ?>
        </section>
    </aside>
</div>

<script>
/* تفضيل الكتم يحفظ في المتصفح — تفضيل عرض محلي صريح، لا وعد بحفظ خادمي
   غير موجود بعد. حين يوجد جدول تفضيلات المحادثة يستبدل هذا بحفظ حقيقي. */
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
