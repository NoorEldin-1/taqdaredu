<?php
/**
 * بوابة ولي الأمر — الرسائل.
 *
 * المرجع التصميمي: تطبيق البنك، لا لوحة تعليمية — محادثة واحدة في كل سطر،
 * بمن تتحدث وآخر ما قيل ومتى. بلا مصطلحات ولا خيوط متشعبة.
 *
 * كل ما في هذه الصفحة حقيقي: `message_thread` و`message` مقيدان بمعرف
 * ولي الأمر نفسه في الاستعلام — لا يفتح خيط لا يكون طرفا فيه، ولو كتب
 * رمز الخيط في الرابط بنفسه.
 *
 * الحلقة الناقصة التي أغلقت: كان الرد ممكنا وبدء المحادثة مستحيلا —
 * لا زر يفتح محادثة مع معلم. صار «محادثة جديدة» هنا، ومن يجوز مراسلته
 * قائمة بيضاء تحسب في الخادم: معلمو كورسات أبنائه المربوطين وحدهم،
 * وتفحص عند الكتابة لا عند رسم القائمة — فاختيار معرف آخر في النموذج
 * يرفض.
 *
 * حاجز الرؤية: هذه رسائل ولي الأمر مع المعلمين والإدارة.
 * محادثات ابنه مع المساعد الذكي ومنشوراته لا تظهر هنا ولا في أي صفحة —
 * «الرقابة الكاملة تنتج طالبا يخفي، لا طالبا يتعلم.»
 */

$tq_ci = &get_instance();   // الطبقة المحملة داخل عرض لا تنسخ على $this، فتؤخذ من النسخة
$tq_ci->load->model('taqdar_parent_model');
$tq_pm = $tq_ci->taqdar_parent_model;
/* بدء محادثة ينشر إلى `POST parent/messages/compose` في المتحكم، والرد
   داخل خيط قائم ينشر إلى برنامج الشاشة ويعالج هنا (المتحكم يفتح خيوطا
   جديدة لا يرد فيها). والمعالجة في الحالتين من هذا النموذج. */
$tq_pm->handle_post('messages');

$tq_nav   = 'messages';
$tq_role  = 'parent';
$tq_title = 'الرسائل';
$tq_sub   = 'محادثاتك مع معلمي أبنائك وإدارة المنصة';
$tq_icon  = 'chat';

$tq_uid = (int) $this->session->userdata('user_id');

$tq_teachers = $tq_pm->teachers_for($tq_uid);
$tq_children = $tq_pm->children($tq_uid);

$tq_threads = $this->db->query(
    "SELECT message_thread_code, sender, receiver, last_message_timestamp
       FROM message_thread
      WHERE sender = ? OR receiver = ?
      ORDER BY last_message_timestamp DESC
      LIMIT 30",
    [$tq_uid, $tq_uid]
)->result_array();

foreach ($tq_threads as &$tq_t) {
    $tq_other = (int) $tq_t['sender'] === $tq_uid ? (int) $tq_t['receiver'] : (int) $tq_t['sender'];

    $tq_t['other'] = $this->db->query(
        "SELECT id, first_name, last_name, image FROM users WHERE id = ? LIMIT 1", [$tq_other]
    )->row_array();

    $tq_t['last'] = $this->db->query(
        "SELECT message, timestamp, sender FROM message
          WHERE message_thread_code = ? ORDER BY message_id DESC LIMIT 1",
        [$tq_t['message_thread_code']]
    )->row_array();

    $tq_t['unread'] = (int) $this->db->query(
        "SELECT COUNT(*) AS n FROM message
          WHERE message_thread_code = ? AND receiver = ? AND read_status = 0",
        [$tq_t['message_thread_code'], $tq_uid]
    )->row('n');
}
unset($tq_t);

/* فتح خيط بعينه — يتحقق من أنه طرف فيه في الاستعلام نفسه. */
$tq_open_code = (string) $this->input->get('thread', true);
$tq_open      = null;
$tq_messages  = [];

if ($tq_open_code !== '') {
    $tq_open = $this->db->query(
        "SELECT message_thread_code, sender, receiver FROM message_thread
          WHERE message_thread_code = ? AND (sender = ? OR receiver = ?) LIMIT 1",
        [$tq_open_code, $tq_uid, $tq_uid]
    )->row_array();

    if ($tq_open) {
        $tq_messages = $this->db->query(
            "SELECT m.message_id, m.message, m.sender, m.timestamp,
                    u.first_name, u.last_name
               FROM message m
               LEFT JOIN users u ON u.id = m.sender
              WHERE m.message_thread_code = ?
              ORDER BY m.message_id ASC
              LIMIT 100",
            [$tq_open_code]
        )->result_array();
    }
}

include 'portal_open.php';
?>

<?php echo $tq_pm->flash_html(); ?>

<div class="tq-cols">
    <div>
        <?php if ($tq_open): ?>

            <div class="tq-card tq-card--panel tq-section">
                <div class="tq-card__head">
                    <h2 class="tq-card__title">المحادثة</h2>
                    <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo base_url('parent/messages'); ?>">كل المحادثات</a>
                </div>

                <?php if ($tq_messages): ?>
                    <ul class="tq-stack">
                        <?php foreach ($tq_messages as $tq_m): ?>
                            <?php $tq_mine = (int) $tq_m['sender'] === $tq_uid; ?>
                            <li style="padding:var(--tq-space-l);border-radius:var(--tq-radius-medium);
                                       background:<?php echo $tq_mine ? 'var(--tq-navyWash)' : 'var(--tq-ground)'; ?>">
                                <p class="tq-micro" style="margin:0 0 var(--tq-space-xs)">
                                    <?php echo html_escape($tq_mine ? 'أنت' : trim($tq_m['first_name'] . ' ' . $tq_m['last_name'])); ?>
                                    · <?php echo html_escape(tq_since((int) $tq_m['timestamp'])); ?>
                                </p>
                                <p style="margin:0"><?php echo tq_iso(html_escape($tq_m['message'])); ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="tq-caption">لا رسائل في هذه المحادثة بعد.</p>
                <?php endif; ?>

                <form method="post" style="margin-block-start:var(--tq-space-xl)"
                      action="<?php echo base_url('parent/messages'); ?>">
                    <input type="hidden" name="tq_action" value="message_reply">
                    <input type="hidden" name="thread" value="<?php echo html_escape($tq_open['message_thread_code']); ?>">
                    <div class="tq-field">
                        <label class="tq-field__label" for="tq-reply">ردك</label>
                        <textarea class="tq-textarea" id="tq-reply" name="message" rows="3" required
                                  placeholder="اكتب ردك هنا"></textarea>
                    </div>
                    <button class="tq-btn tq-btn--primary" type="submit">إرسال</button>
                </form>
            </div>

        <?php endif; ?>

        <!-- بدء محادثة جديدة -->
        <section class="tq-section" aria-labelledby="tq-new-h">
            <div class="tq-sectionhead"><h2 id="tq-new-h">محادثة جديدة</h2></div>

            <div class="tq-card">
                <?php if ($tq_teachers): ?>
                    <form method="post" action="<?php echo base_url('parent/messages/compose'); ?>">
                        <input type="hidden" name="tq_action" value="message_new">

                        <div class="tq-field">
                            <label class="tq-field__label" for="tq-receiver">إلى</label>
                            <select class="tq-select" id="tq-receiver" name="receiver" required>
                                <option value="">اختر معلما…</option>
                                <?php foreach ($tq_teachers as $tq_t2): ?>
                                    <option value="<?php echo (int) $tq_t2['id']; ?>">
                                        <?php echo html_escape($tq_t2['name']); ?>
                                        — <?php echo html_escape(implode('، ', $tq_t2['courses'])); ?>
                                        (<?php echo html_escape(implode('، ', $tq_t2['children'])); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="tq-field__hint">
                                القائمة معلمو مواد أبنائك المربوطين وحدهم، وتفحص في الخادم عند الإرسال.
                            </p>
                        </div>

                        <div class="tq-field">
                            <label class="tq-field__label" for="tq-new-message">رسالتك</label>
                            <textarea class="tq-textarea" id="tq-new-message" name="message" rows="4" required
                                      placeholder="اكتب سؤالك القصير — «كيف حال ابني في الرياضيات هذا الأسبوع؟»"></textarea>
                        </div>

                        <button class="tq-btn tq-btn--primary" type="submit">إرسال</button>
                    </form>
                <?php else: ?>
                    <div class="tq-empty">
                        <h3 class="tq-empty__title">لا معلم تجوز مراسلته الآن</h3>
                        <p class="tq-empty__text">
                            <?php if (!$tq_children): ?>
                                لا ابن مربوط بحسابك بعد. المراسلة تفتح بعد ربط حساب ابنك وموافقته.
                            <?php else: ?>
                                أبناؤك المربوطون غير مسجلين في أي مادة لها معلم بعد،
                                فلا أحد نراسله باسمهم. وتظهر القائمة هنا فور تسجيلهم في مادة.
                            <?php endif; ?>
                        </p>
                        <?php if (!$tq_children): ?>
                            <a class="tq-btn tq-btn--primary" href="<?php echo base_url('parent'); ?>">اربط حساب ابنك</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section aria-labelledby="tq-threads-h">
            <div class="tq-sectionhead">
                <h2 id="tq-threads-h">محادثاتك</h2>
                <?php if ($tq_threads): ?>
                    <span class="tq-sectionhead__count"><?php echo TQ_LRI . count($tq_threads) . TQ_PDI; ?></span>
                <?php endif; ?>
            </div>

            <?php if ($tq_threads): ?>
                <div class="tq-card">
                    <ul class="tq-stack">
                        <?php foreach ($tq_threads as $tq_t): ?>
                            <?php
                            $tq_other_name = $tq_t['other']
                                ? trim($tq_t['other']['first_name'] . ' ' . $tq_t['other']['last_name'])
                                : 'إدارة المنصة';
                            $tq_photo = tqs_person_img(isset($tq_t['other']['image']) ? $tq_t['other']['image'] : '');
                            ?>
                            <li>
                                <a class="tq-row" style="gap:var(--tq-space-l);padding-block:var(--tq-space-m);border-block-end:1px solid var(--tq-line)"
                                   href="<?php echo base_url('parent/messages'); ?>?thread=<?php echo rawurlencode($tq_t['message_thread_code']); ?>">
                                    <img class="tq-avatar" src="<?php echo $tq_photo; ?>" alt="صورة <?php echo html_escape($tq_other_name); ?>">
                                    <span style="flex:1;min-inline-size:0">
                                        <span class="tq-strong" style="display:block;color:var(--tq-navy)"><?php echo html_escape($tq_other_name); ?></span>
                                        <span class="tq-micro" style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                            <?php echo html_escape(mb_substr((string) ($tq_t['last']['message'] ?? ''), 0, 70)); ?>
                                        </span>
                                    </span>
                                    <span class="tq-micro"><?php echo html_escape(tq_since((int) $tq_t['last_message_timestamp'])); ?></span>
                                    <?php if ($tq_t['unread'] > 0): ?>
                                        <span class="tq-rail__count tq-rail__count--urgent" style="margin-inline-start:0">
                                            <?php echo TQ_LRI . $tq_t['unread'] . TQ_PDI; ?>
                                        </span>
                                        <span class="tq-sr">رسائل غير مقروءة</span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <div class="tq-card tq-empty">
                    <span class="tq-icon-box tq-pastel--sky" style="color:var(--tq-sky-ink)" aria-hidden="true"><?php echo tq_icon('chat', 24); ?></span>
                    <h3 class="tq-empty__title">لا محادثات بعد</h3>
                    <p class="tq-empty__text">
                        ابدأ محادثة من النموذج فوق، أو انتظر رسالة من معلم أو من إدارة المنصة —
                        تجدها هنا في سطر واحد: من أرسل، وآخر ما قيل، ومتى.
                    </p>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <aside class="tq-aside">
        <div class="tq-pastel tq-pastel--mint">
            <span class="tq-pastel__label tq-micro">متى تراسل المعلم</span>
            <p class="tq-pastel__body" style="margin:var(--tq-space-s) 0 0">
                حين تلاحظ انقطاعا أو تراجعا في مادة. سؤال قصير من ولي الأمر يفتح للمعلم
                ما لا تفتحه التقارير.
            </p>
        </div>

        <div class="tq-card">
            <div class="tq-card__head"><h2 class="tq-card__title">ما لا نعرضه</h2></div>
            <p class="tq-caption">
                محادثات ابنك مع المساعد الذكي ومنشوراته في المجتمع ليست هنا ولا في أي صفحة أخرى.
                نعطيك ما يساعدك على مساعدته، لا ما يجعله يخفي.
            </p>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
