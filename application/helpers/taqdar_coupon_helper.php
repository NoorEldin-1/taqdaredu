<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * TQ-COUPON — حقل كود الخصم في شاشات الدفع، مصدر واحد لأربع شاشات وولي الأمر.
 *
 * شاشات التأكيد أربع (الباقة · الكورس · الكتاب · باقة الحصص)، ونسخة من
 * الحقل في كل واحدة تفترق عند أول تعديل: يضاف سطر «بحد أقصى» في واحدة
 * وينسى في ثلاث. فالحقل والسطر والإجمالي يطبعون من هنا.
 *
 * ═══ والحال في الخادم لا في المتصفح ═══
 *
 * `tq_coupon_state()` تحكم على الكود **قبل أن تطبع الشاشة**: من جاء برابط
 * فيه `?coupon=` أو طبق الكود بلا جافاسكربت يرى الخصم مطبوعا في الملخص
 * والبطاقة اللاصقة معا. والسكربت تحسين لا شرط — يطبق بلا إعادة تحميل
 * ويسأل الخادم نفسه (`coupon/check`) فلا يحسب رقما بنفسه.
 *
 * ═══ والكود يتبع صاحبه في الجلسة ═══
 *
 * رابط حملة فيه `?coupon=RAMADAN` يفتح صفحة الباقات، ثم يختار الزائر،
 * ثم يسجل، ثم يعود إلى الدفع — أربع صفحات بين الرابط والزر. فالكود يحفظ
 * في الجلسة حين يصل، ويملأ الحقل حين يبلغ الشاشة. ومن نسي الكود لا يدفع
 * السعر كاملا وهو يظن أنه خصم.
 */

if (!function_exists('tq_coupon_capture')) {
    /**
     * يلتقط `?coupon=` من أي رابط ويحفظه في الجلسة — ينادى من متحكم الموقع.
     *
     * والالتقاط لا يحكم: الكود يفحص في الشاشة التي يستعمل فيها، بسعر ما
     * يشترى فيها. وكود يرد في صفحة الباقات لأنه للكتب صحيح في صفحة الكتاب.
     */
    function tq_coupon_capture()
    {
        $CI = get_instance();
        if (!isset($CI->input) || !isset($CI->session)) return;
        $raw = $CI->input->get('coupon');
        if (!is_string($raw) || trim($raw) === '' || $raw === '-') return;
        $CI->load->model('taqdar_coupon_model', 'tq_cp');
        $code = $CI->tq_cp->normalize($raw);
        if ($code !== '' && strlen($code) <= 40) {
            $CI->session->set_userdata('tq_coupon', array('code' => $code, 'at' => time()));
        }
    }
}

if (!function_exists('tq_coupon_remember')) {
    /**
     * يحفظ الكود في الجلسة أو يمحوه — `''` تمحو.
     *
     * و`$for` يقول على أي شراء طبق (`plan:12`): نية الشراء بعد التسجيل
     * (TQ-AUTOPAY) تمضي بالكود **الذي رآه مطبقا على هذه الباقة بعينها**
     * وحده — لا بكود التقط من رابط لسلعة أخرى فيرد شراءه.
     */
    function tq_coupon_remember($code, $for = '')
    {
        $CI = get_instance();
        if (!isset($CI->session)) return;
        $code = (string) $code;
        if ($code === '') { $CI->session->unset_userdata('tq_coupon'); return; }
        $CI->session->set_userdata('tq_coupon', array('code' => $code, 'at' => time(), 'for' => (string) $for));
    }
}

if (!function_exists('tq_coupon_posted')) {
    /**
     * الكود الذي أرسل مع نموذج الشراء — ويحفظ قبل أن يحكم عليه.
     *
     * فشراء يرد لأن الكود منته يعود إلى شاشة الدفع **والكود في حقله**
     * ومعه سبب الرد، لا حقلا فارغا يعيد صاحبه الكتابة فيه ولا يعرف ما
     * جرى.
     */
    function tq_coupon_posted()
    {
        $CI = get_instance();
        $CI->load->model('taqdar_coupon_model', 'tq_cp');
        $code = $CI->tq_cp->normalize((string) $CI->input->post('coupon'));
        tq_coupon_remember($code);
        return $code;
    }
}

if (!function_exists('tq_coupon_state')) {
    /**
     * حال الكود لشراء بعينه — يحكم عليه الخادم قبل أن تطبع الشاشة.
     *
     * والمصدر بترتيب: ما أرسل الآن (زر «طبق» بلا سكربت)، ثم ما في
     * الرابط، ثم ما في الجلسة. و**كود الجلسة لا يرد بصوت عال**: من جاء
     * برابط كود للكتب ثم فتح شاشة باقة لم يكتب شيئا هنا، ورسالة «لا
     * ينطبق» فوق شاشته تبدو عطلا. فيحمل صامتا ولا يطبق.
     *
     * @return array show · code · applied · ok · msg · gross · net · discount
     *               · percent · kind · item · cycle
     */
    function tq_coupon_state($kind, $item_id, $gross, $user_id = 0, $cycle = '')
    {
        $CI = get_instance();
        $CI->load->model('taqdar_coupon_model', 'tq_cp');

        $st = array('show' => false, 'code' => '', 'applied' => false, 'ok' => false,
                    'msg' => '', 'gross' => (int) $gross, 'net' => (int) $gross,
                    'discount' => 0, 'percent' => 0, 'kind' => (string) $kind,
                    'item' => (int) $item_id, 'cycle' => (string) $cycle);

        if ((string) $CI->input->post('coupon_clear') === '1') {
            tq_coupon_remember('');
            $st['show'] = $CI->tq_cp->any_live($kind);
            return $st;
        }

        $typed = $CI->input->post('coupon');
        $url   = $CI->input->get('coupon');
        /* `?coupon=-` هو «إزالة الكود» في الشاشة التي لا نموذج فيها. */
        if ($url === '-') { tq_coupon_remember(''); $url = ''; }
        $sess  = $CI->session->userdata('tq_coupon');
        $loud  = true;
        if (is_string($typed) && trim($typed) !== '') {
            $code = $typed;
        } elseif (is_string($url) && trim($url) !== '') {
            $code = $url;
        } elseif (is_array($sess) && !empty($sess['code'])) {
            $code = (string) $sess['code'];
            $loud = false;
        } else {
            $code = '';
        }
        $code = $CI->tq_cp->normalize($code);

        $st['show'] = ($code !== '') || $CI->tq_cp->any_live($kind);
        if ($code === '' || (int) $gross <= 0) return $st;

        $q = $CI->tq_cp->quote($code, (int) $user_id, $kind, (int) $item_id, (int) $gross);
        if (!empty($q['ok'])) {
            tq_coupon_remember($code, $kind . ':' . (int) $item_id);
            return array_merge($st, array(
                'code' => $code, 'applied' => true, 'ok' => true, 'msg' => $q['message'],
                'net' => (int) $q['net'], 'discount' => (int) $q['discount'],
                'percent' => (int) $q['percent'], 'show' => true,
            ));
        }

        /* كود الجلسة لا ينطبق هنا: يحمل صامتا ولا يطبق — انظر رأس الدالة.
           إلا أن يكون خطأ فيه هو (منته · موقوف · استنفد): ذاك يقال، فمن
           حمل كودا من إعلان أمس يعرف لماذا لم يخصم شيء. */
        if (!$loud && in_array($q['reason'], array('kind', 'item', 'min', 'free', 'tiny'), true)) {
            return $st;
        }
        $st['code'] = $code;
        $st['msg']  = (string) $q['message'];
        $st['show'] = true;
        return $st;
    }
}

if (!function_exists('tq_coupon_money')) {
    /** المبلغ بعملة الشاشة — `tqs_money()` نفسها، فلا صيغتان لرقم واحد. */
    function tq_coupon_money($halalas)
    {
        return tqs_money((int) $halalas);
    }
}

if (!function_exists('tq_coupon_box')) {
    /**
     * حقل «لديك كود خصم؟» — يطبع لا شيء إن لم يكن في المنصة كود يعمل.
     *
     * **وبلا جافاسكربت يعمل**: زر «طبق» زر إرسال إلى الشاشة نفسها
     * (`formaction`)، فيعيد الخادم بناءها والخصم مطبوع. وحيث لا نموذج
     * شراء يحيط بالحقل (شاشة الزائر) يطبع نموذجه الصغير بـ`GET`.
     *
     * @param array $st  من `tq_coupon_state()`
     * @param array $opt here (رابط الشاشة) · standalone (نموذج مستقل)
     */
    function tq_coupon_box($st, $opt = array())
    {
        if (empty($st['show'])) return '';

        $CI   = get_instance();
        $here = isset($opt['here']) ? (string) $opt['here'] : current_url();
        $solo = !empty($opt['standalone']);
        $id   = 'tqCpn' . substr(md5($st['kind'] . $st['item']), 0, 6);
        $open = ($st['code'] !== '');
        $tone = $st['code'] === '' ? '' : ($st['applied'] ? ' is-ok' : ' is-err');

        $csrf_name = isset($CI->security) ? $CI->security->get_csrf_token_name() : '';
        $csrf_hash = isset($CI->security) ? $CI->security->get_csrf_hash() : '';

        ob_start(); ?>
<div class="icard co-coupon" data-tq-coupon
     data-kind="<?php echo html_escape($st['kind']); ?>"
     data-item="<?php echo (int) $st['item']; ?>"
     data-cycle="<?php echo html_escape($st['cycle']); ?>"
     data-url="<?php echo html_escape(site_url('coupon/check')); ?>"
     data-csrf-name="<?php echo html_escape($csrf_name); ?>"
     data-busy="<?php echo te('جار التحقق…'); ?>"
     data-csrf="<?php echo html_escape($csrf_hash); ?>">
  <?php if ($solo): ?><form method="get" action="<?php echo html_escape($here); ?>" class="co-coupon__solo">
    <?php if ($st['cycle'] !== ''): ?><input type="hidden" name="cycle" value="<?php echo html_escape($st['cycle']); ?>"><?php endif; ?>
  <?php endif; ?>
  <details<?php echo $open ? ' open' : ''; ?>>
    <summary>
      <svg aria-hidden="true"><use href="#i-tag"></use></svg>
      <span data-tq-coupon-head data-off="<?php echo te('لديك كود خصم؟'); ?>" data-on="<?php echo te('كود الخصم ____ مطبق', array('%s')); ?>"><?php echo $st['applied']
          ? t('كود الخصم ____ مطبق', array('<b class="tq-ltr">' . html_escape($st['code']) . '</b>'))
          : te('لديك كود خصم؟'); ?></span>
    </summary>
    <div class="co-coupon__row">
      <label class="sr-only" for="<?php echo $id; ?>"><?php echo t('كود الخصم'); ?></label>
      <input id="<?php echo $id; ?>" type="text" name="coupon" dir="ltr" maxlength="40"
             value="<?php echo html_escape($st['code']); ?>" placeholder="<?php echo te('مثال: TAQDAR20'); ?>"
             autocomplete="off" autocapitalize="characters" spellcheck="false"
             data-tq-coupon-in>
      <?php if ($solo): ?>
        <button type="submit" class="btn btn--ghost" data-tq-coupon-go><?php echo t('طبق'); ?></button>
      <?php else: ?>
        <button type="submit" class="btn btn--ghost" name="tq_coupon_apply" value="1"
                formaction="<?php echo html_escape($here); ?>" formmethod="post" formnovalidate
                data-tq-coupon-go><?php echo t('طبق'); ?></button>
      <?php endif; ?>
    </div>
    <p class="co-coupon__msg<?php echo $tone; ?>" role="status" aria-live="polite"
       data-tq-coupon-msg><?php echo html_escape($st['msg']); ?></p>
    <?php if (!$solo): ?>
      <button type="submit" class="co-coupon__clear" name="coupon_clear" value="1"
              formaction="<?php echo html_escape($here); ?>" formmethod="post" formnovalidate
              data-tq-coupon-clear<?php echo $st['applied'] ? '' : ' hidden'; ?>><?php echo t('إزالة الكود'); ?></button>
    <?php else: ?>
      <a class="co-coupon__clear" href="<?php echo html_escape($here . (strpos($here, '?') === false ? '?' : '&') . 'coupon=-'); ?>"
         data-tq-coupon-clear<?php echo $st['applied'] ? '' : ' hidden'; ?>><?php echo t('إزالة الكود'); ?></a>
    <?php endif; ?>
    <p class="tq-caption co-coupon__note"><?php echo t('يخصم من هذا الشراء وحده، ويفحص مرة أخرى عند التأكيد.'); ?></p>
  </details>
  <?php if ($solo): ?></form><?php endif; ?>
</div>
<?php
        static $js = false;
        if (!$js) {
            $js = true;
            echo '<script src="' . base_url('assets/taqdar/js/tq-coupon.js?v=1') . '" defer></script>';
        }
        return ob_get_clean();
    }
}

if (!function_exists('tq_coupon_field')) {
    /**
     * حقل الكود في بوابة ولي الأمر — نموذج فيه خيارات لا عنصر واحد.
     *
     * شاشة «ادفع عن ابنك» تعرض الباقات أو الكورسات أزرار راديو في النموذج
     * نفسه، فلا عنصر ثابت يعاين عليه. فالحقل يقرأ **المختار الآن** من
     * نموذجه (`data-item-from`) ومعه الابن (`data-child-from`): حد «مرة
     * لكل حساب» على الابن الذي يكتب الشراء باسمه، لا على الأب.
     *
     * وبلا سكربت يرسل الكود مع النموذج، ويحكم عليه الخادم عند الإصدار.
     */
    function tq_coupon_field($kind, $item_from, $opt = array())
    {
        $CI = get_instance();
        $CI->load->model('taqdar_coupon_model', 'tq_cp');
        if (!$CI->tq_cp->any_live($kind)) return '';

        $id   = 'tqCpnF' . preg_replace('/\W/', '', $kind);
        $csrf_name = isset($CI->security) ? $CI->security->get_csrf_token_name() : '';
        $csrf_hash = isset($CI->security) ? $CI->security->get_csrf_hash() : '';

        ob_start(); ?>
<div class="tq-field tq-cpn" data-tq-coupon data-portal
     data-kind="<?php echo html_escape($kind); ?>"
     data-item-from="<?php echo html_escape($item_from); ?>"
     data-child-from="child_id"
     data-cycle-from="<?php echo html_escape(isset($opt['cycle_from']) ? $opt['cycle_from'] : ''); ?>"
     data-url="<?php echo html_escape(site_url('coupon/check')); ?>"
     data-csrf-name="<?php echo html_escape($csrf_name); ?>"
     data-busy="<?php echo te('جار التحقق…'); ?>"
     data-csrf="<?php echo html_escape($csrf_hash); ?>">
  <label class="tq-field__label" for="<?php echo $id; ?>"><?php echo t('كود الخصم'); ?> <span class="tq-caption"><?php echo t('— اختياري'); ?></span></label>
  <div class="tq-cpn__row">
    <input class="tq-input" id="<?php echo $id; ?>" type="text" name="coupon" dir="ltr" maxlength="40"
           autocomplete="off" autocapitalize="characters" spellcheck="false"
           placeholder="TAQDAR20" data-tq-coupon-in>
    <button type="button" class="tq-btn tq-btn--ghost tq-btn--sm" data-tq-coupon-go><?php echo t('تحقق'); ?></button>
  </div>
  <p class="tq-field__msg tq-cpn__msg" role="status" aria-live="polite" data-tq-coupon-msg></p>
</div>
<?php
        static $js = false;
        if (!$js) {
            $js = true;
            echo '<style>.tq-cpn__row{display:flex;gap:var(--tq-space-s)}.tq-cpn__row .tq-input{flex:1 1 auto;'
               . 'text-align:left;letter-spacing:.06em;text-transform:uppercase}.tq-cpn__msg:empty{display:none}'
               . '.tq-cpn__msg.is-ok{color:var(--tq-teal)}.tq-cpn__msg.is-err{color:var(--tq-danger)}</style>';
            echo '<script src="' . base_url('assets/taqdar/js/tq-coupon.js?v=1') . '" defer></script>';
        }
        return ob_get_clean();
    }
}

if (!function_exists('tq_coupon_row')) {
    /**
     * سطر «خصم الكود» في جدول الإجمالي — مطبوع دائما ومخفي بلا كود، ليظهره
     * السكربت بلا أن يبني وسما.
     */
    function tq_coupon_row($st)
    {
        if (empty($st['show'])) return '';
        return '<div class="co-total__disc" data-tq-coupon-row' . ($st['applied'] ? '' : ' hidden') . '>'
             . '<dt>' . t('خصم الكود') . ' <b class="tq-ltr" data-tq-coupon-code>' . html_escape($st['code']) . '</b></dt>'
             . '<dd>− <span data-tq-coupon-disc>' . tq_coupon_money($st['discount']) . '</span></dd></div>';
    }
}

if (!function_exists('tq_coupon_total')) {
    /**
     * الإجمالي بعد الكود — ومعه القديم مشطوبا حين يطبق.
     *
     * وكل موضع يطبع المبلغ الذي سيدفع يمر بها: الملخص، والبطاقة اللاصقة،
     * وسطر «يخصم كذا مرة واحدة». وموضع ينسى يطبع السعر الكامل تحت زر
     * سيطلب الصافي — والرقمان المختلفان في شاشة واحدة يجعلان المشتري يشك
     * في كليهما.
     */
    function tq_coupon_total($st, $gross)
    {
        $gross = (int) $gross;
        $net   = !empty($st['applied']) ? (int) $st['net'] : $gross;
        return '<s class="co-was" data-tq-coupon-was' . (!empty($st['applied']) ? '' : ' hidden') . '>'
             . tq_coupon_money($gross) . '</s> '
             . '<span data-tq-coupon-net>' . tq_coupon_money($net) . '</span>';
    }
}

if (!function_exists('tq_coupon_net')) {
    /** الصافي رقما — لما يطبع خارج وسم (سطر الشرح، ومنصة ميتا). */
    function tq_coupon_net($st, $gross)
    {
        return !empty($st['applied']) ? (int) $st['net'] : (int) $gross;
    }
}
