<?php
/** تذييل الموقع العام — روابط · الدعم · الشروط · الخصوصية · التواصل · النشرة */
$tq_contact = json_decode(get_frontend_settings('contact_info') ?: '{}', true);
?>
<footer class="tq-site-footer">
    <div class="tq-container">
        <div class="tq-site-footer__cols">

            <div>
                <img src="<?php echo tq_asset('brand/wordmark_light.png'); ?>" alt="تقدر" height="44"
                     style="margin-block-end:var(--tq-space-l)">
                <p class="tq-caption" style="color:rgba(255,255,255,.72);max-inline-size:34ch">
                    منصة تعليمية عربية تقود الطالب خطوة بخطوة حتى الإتقان — لا تقدم بلا إتقان.
                </p>
            </div>

            <div>
                <h3>المنصة</h3>
                <ul class="tq-stack" style="--tq-space-l:var(--tq-space-s)">
                    <li><a href="<?php echo base_url('plans'); ?>">الدورات</a></li>
                    <li><a href="<?php echo base_url('categories'); ?>">الأقسام</a></li>
                    <li><a href="<?php echo base_url('plans'); ?>">الاشتراكات</a></li>
                    <li><a href="<?php echo base_url('blog'); ?>">المدونة</a></li>
                </ul>
            </div>

            <div>
                <h3>الدعم</h3>
                <ul class="tq-stack" style="--tq-space-l:var(--tq-space-s)">
                    <li><a href="<?php echo base_url('faq'); ?>">الأسئلة الشائعة</a></li>
                    <li><a href="<?php echo base_url('contact'); ?>">تواصل معنا</a></li>
                    <li><a href="<?php echo base_url('terms'); ?>">الأحكام والشروط</a></li>
                    <li><a href="<?php echo base_url('privacy'); ?>">سياسة الخصوصية</a></li>
                    <li><a href="<?php echo base_url('refund'); ?>">سياسة الاسترجاع</a></li>
                </ul>
            </div>

            <div>
                <h3>النشرة البريدية</h3>
                <p class="tq-caption" style="color:rgba(255,255,255,.72)">
                    جديد البرامج والمواد، مرة واحدة كل أسبوع.
                </p>
                <form class="tq-row" action="<?php echo base_url('home/subscribe_to_our_newsletter'); ?>" method="post"
                      style="margin-block-start:var(--tq-space-m)">
                    <label class="tq-sr" for="tq-news">بريدك الإلكتروني</label>
                    <input class="tq-input" id="tq-news" name="email" type="email" required
                           placeholder="بريدك الإلكتروني">
                    <button class="tq-btn tq-btn--mastery tq-btn--sm" type="submit">اشترك</button>
                </form>
                <?php if (!empty($tq_contact['email'])): ?>
                    <p class="tq-caption" style="color:rgba(255,255,255,.72);margin-block-start:var(--tq-space-l)">
                        <?php echo html_escape($tq_contact['email']); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="tq-site-footer__bottom">
            <span class="tq-micro">
                حقوق النشر <?php echo TQ_LRI . date('Y') . TQ_PDI; ?> — تقدر. كل الحقوق محفوظة.
            </span>
            <span class="tq-micro">صنع بعناية في السعودية</span>
        </div>
    </div>
</footer>
