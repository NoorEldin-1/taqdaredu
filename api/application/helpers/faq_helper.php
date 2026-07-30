<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * FAQ content — the single source of truth shared by:
 *   - the crawler SEO page  (Seo::faq → views/seo/faq.php + FAQPage JSON-LD)
 *   - the SPA page content  (Api_frontend_model::get_page('faq'))
 * Answers are plain text so they render safely as both HTML and JSON-LD.
 * Edit the Q&A here once and both surfaces stay in sync.
 *
 * The answers below describe how the platform behaves and make no verifiable
 * claim about accreditation, partners, or numbers. TODO(client): review and
 * expand with Tagdar's real policies before launch — this text is indexed by
 * search engines as FAQPage structured data, so anything inaccurate here is
 * published as a factual claim.
 *
 * The `mycom_` prefix is legacy and kept only because Seo.php calls these
 * names; renaming would need both call sites changed together.
 */

if (!function_exists('mycom_faq_items')) {
    function mycom_faq_items(): array
    {
        return [
            ['q' => 'ما هي منصة تقدر؟',
             'a' => 'تقدر منصة تعليمية رقمية تقدّم دورات مسجّلة في مسارات متدرّجة، يشرحها مدرّبون من أهل الاختصاص، وتنتهي كل دورة بشهادة إتمام.'],
            ['q' => 'هل الدورات مسجّلة وأتعلّم فيها بإيقاعي؟',
             'a' => 'نعم. جميع الدورات متاحة عبر الإنترنت ومسجّلة مسبقاً، فتتعلّم في الوقت الذي يناسبك وتتوقف وتُكمل من حيث انتهيت.'],
            ['q' => 'هل أحصل على شهادة بعد إنهاء الدورة؟',
             'a' => 'نعم. عند إتمامك متطلبات الدورة تحصل على شهادة إتمام من منصة تقدر يمكنك حفظها أو إضافتها إلى ملفك المهني.'],
            ['q' => 'هل أحتاج خبرة سابقة للبدء؟',
             'a' => 'معظم الدورات تبدأ من الأساسيات، والمبتدئ مُرحَّب به. تُوضّح صفحة كل دورة المستوى المطلوب وأي متطلبات سابقة إن وُجدت.'],
            ['q' => 'كيف أشترك في دورة وأدفع قيمتها؟',
             'a' => 'تصفّح قائمة الدورات، وافتح الدورة التي تناسبك، ثم اضغط على زر الاشتراك. تتم عملية الدفع إلكترونياً، وبمجرد تأكيدها يُفتح لك محتوى الدورة مباشرة.'],
            ['q' => 'إلى متى يبقى وصولي إلى الدورة؟',
             'a' => 'يبقى وصولك إلى الدورات التي اشتركت فيها متاحاً دون تاريخ انتهاء، بما في ذلك أي تحديثات تُضاف إلى محتواها لاحقاً.'],
            ['q' => 'هل يمكنني استرداد المبلغ؟',
             'a' => 'يمكنك ذلك ضمن شروط سياسة الاسترجاع. يُرجى مراجعة صفحة سياسة الاسترجاع للاطلاع على الحالات المشمولة والمدة المتاحة وخطوات تقديم الطلب.'],
            ['q' => 'كيف تساعدني الدورات في مساري المهني؟',
             'a' => 'تركّز الدورات على مهارات عملية قابلة للتطبيق، مع تطبيقات ومشاريع تتيح لك تحويل ما تعلّمته إلى عمل ملموس تضيفه إلى ملفك المهني.'],
            ['q' => 'كيف أتواصل مع الدعم؟',
             'a' => 'من خلال صفحة «تواصل معنا» تصل إلى فريق المنصة، وسنساعدك في الاشتراك أو الدفع أو أي مشكلة تقنية أو في اختيار الدورة المناسبة لك.'],
        ];
    }
}

if (!function_exists('mycom_faq_html')) {
    /** FAQ rendered as semantic HTML — used as the SPA page content. */
    function mycom_faq_html(): string
    {
        $out = '';
        foreach (mycom_faq_items() as $f) {
            $out .= '<h2>' . htmlspecialchars($f['q'], ENT_QUOTES, 'UTF-8') . '</h2>';
            $out .= '<p>'  . htmlspecialchars($f['a'], ENT_QUOTES, 'UTF-8') . '</p>';
        }
        return $out;
    }
}
