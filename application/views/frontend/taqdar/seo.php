<?php 
        $seo = get_seo_data();

        // Default Meta Data
        $meta_title = ucwords($page_title);
        $meta_description = '';
        $meta_keywords = '';
        $meta_robot = 'index, follow';
        $meta_author = get_settings('author') ?? 'Creativeitem';
        $canonical_url = '';
        $og_title = $meta_title;
        $og_description = $meta_description;
        /* الصورة الافتراضية كانت `placeholder.png` — أي أن كل مشاركة للموقع
           على الشبكات تظهر بمربع فارغ. وهذه صورة حقيقية من أصول الموقع. */
        $tq_og = 'assets/taqdar/site/img/og-cover.jpg';
        $og_image = is_file(FCPATH . $tq_og)
            ? base_url($tq_og)
            : base_url('uploads/seo-og-images/placeholder.png');
        $json_ld = '{}';

        if (!empty($seo)) {
            // Populate SEO data if available
            $meta_title = $seo['meta_title'] ?? $meta_title;
            $meta_description = $seo['meta_description'] ?? '';
            $meta_keywords = $seo['meta_keywords'] ?? '';
            $meta_robot = $seo['meta_robot'] ?? 'index, follow';
            $meta_author = $seo['meta_author'] ?? 'Creativeitem';
            $canonical_url = $seo['canonical_url'] ?? '';
            $og_title = $seo['og_title'] ?? $meta_title;
            $og_description = $seo['og_description'] ?? $meta_description;
            $json_ld = $seo['json_ld'] ?? '{}';

            // Handle OG Image
            $og_image_path = 'uploads/seo-og-images/' . $seo['og_image'];
            if (!empty($seo['og_image']) && file_exists($og_image_path) && is_file($og_image_path)) {
                $og_image = base_url($og_image_path);
            }
        } else {
            // Fallback to specific routes
            $CI =& get_instance();
            $route_third_segment = $CI->uri->segment(3);
            $route_second_segment = $CI->uri->segment(2);
            $route_first_segment = $CI->uri->segment(1);

            /* `home/` صراحة: الفرع لصفحة الكورس العامة وحدها.
               وبدونها يلتقط **كل** مسار مقطعه الثاني `course` — ومنها
               `teacher/course/<id>` و`teacher/course/new` في بوابة
               المعلم، فيصير عنوان تبويب «إعدادات الكورس» عنوان الكورس،
               وعنوان شاشة الإنشاء الكلمة الحرفية `Title`. */
            if ($route_first_segment == 'home' && $route_second_segment == 'course' && isset($course_id)) {
                $data = $CI->crud_model->get_course_by_id($course_id)->row_array();
                $meta_title = $data['title'] ?? 'Title';
                $meta_description = $data['meta_description'] ?? '';
                $meta_keywords = $data['meta_keywords'] ?? '';
                $og_title = $meta_title;
                $og_description = $meta_description;
                $og_image = $CI->crud_model->get_course_thumbnail_url($course_id);

            } elseif ($route_second_segment == 'ebook_details' && isset($ebook_id)) {
                $data = $CI->ebook_model->get_ebook_by_id($ebook_id)->row_array();
                $meta_title = $data['meta_title'] ?? $data['title'];
                $meta_description = $data['meta_description'] ?? $data['description'];
                $meta_keywords = $data['meta_keywords'] ?? '';
                $og_title = $data['og_title'] ?? '';
                $og_description = $data['og_description'] ?? '';

                $ebook_thumbnail = 'uploads/ebook/thumbnails/' . $data['thumbnail'];
                $og_image = (!empty($data['thumbnail']) && file_exists($ebook_thumbnail)) 
                            ? base_url($ebook_thumbnail) 
                            : base_url('uploads/ebook/thumbnails/placeholder.png');

                $json_ld = $data['json_ld'] ?? '{}';

            } elseif ($route_second_segment == 'details' && isset($blog_id)) {
                $data = $CI->crud_model->get_all_blogs($blog_id)->row_array();
                $meta_title = $data['title'] ?? 'Title';
                $meta_description = $data['description'] ?? '';
                $meta_keywords = $data['keywords'] ?? '';
                $og_title = $meta_title;
                $og_description = $meta_description;

                $blog_banner = 'uploads/blog/banner/' . $data['banner'];
                $og_image = (!empty($data['banner']) && file_exists($blog_banner)) 
                            ? base_url($blog_banner) 
                            : base_url('uploads/blog/banner/placeholder.png');

            } elseif ($route_first_segment == 'bundle_details' && isset($bundle_details)) {
                $data = $bundle_details;
                
                $meta_title = $data['title'] ?? 'Title';
                $meta_description = $data['bundle_details'] ?? '';
                $og_title = $meta_title;
                $og_description = $meta_description;

                $bundle_banner = 'uploads/course_bundle/banner/' . $data['banner'];
                $og_image = (!empty($data['banner']) && file_exists($bundle_banner)) 
                            ? base_url($bundle_banner) 
                            : base_url('uploads/course_bundle/banner/thumbnail.png');
            } elseif ($route_second_segment == 'bootcamp' && $route_third_segment == 'details' && isset($bootcamp_details)) {
                $data = $bootcamp_details;
                
                $meta_title = $data['meta_title'] ?? $data['title'];
                $meta_description = $data['meta_description'] ?? $data['description'];
                $meta_keywords = $data['meta_keywords'] ?? '';
                $og_title = $data['og_title'] ?? '';
                $og_description = $data['og_description'] ?? '';

                $bootcamp_thumbnail = 'uploads/bootcamp/bootcamp_thumbnail/' . $data['bootcamp_thumbnail'];
                $og_image = (!empty($data['bootcamp_thumbnail']) && file_exists($bootcamp_thumbnail)) 
                            ? base_url($bootcamp_thumbnail) 
                            : base_url('uploads/bootcamp/bootcamp_thumbnail/placeholder.png');

                $json_ld = $data['json_ld'] ?? '{}';
                
            } elseif ($route_second_segment == 'team_training' && $route_third_segment == 'package_details' && isset($package)) {
                $data = $package;
                
                $meta_title = $data['title'] ?? 'Title';
                $meta_description = $data['meta_description'] ?? '';
                $meta_keywords = $data['meta_keywords'] ?? '';
                $og_title = $meta_title;
                $og_description = $meta_description;

                $package_thumbnail = 'uploads/team_training/thumbnail/' . $data['thumbnail'];
                $og_image = (!empty($data['thumbnail']) && file_exists($package_thumbnail)) 
                            ? base_url($package_thumbnail) 
                            : base_url('uploads/seo-og-images/placeholder.png');
            } else {

                $meta_title = $page_title;
                $meta_description = get_settings('website_description');
                $meta_keywords = get_settings('website_keywords');
                $og_title = $page_title;
                $og_description = $meta_description;
                $og_image = base_url("uploads/system/".get_current_banner('banner_image'));
            }
        }

        /* TQ-SEO-UNIQUE — عنوان ووصف يخصان الصفحة لا المنصة.

           كان ٢٧ صفحة من ٣٨ تحمل وصف المنصة العام نفسه حرفا بحرف، و١٦ تحمل
           عنوانا يحمله غيرها — و«الرياضيات» وحدها ثلاث صفحات في ثلاثة صفوف.
           والوصف هو سطر الاعلان في نتائج البحث: سبع باقات مختلفة السعر
           والمرحلة كانت تعلن بجملة واحدة.

           والبيانات كلها في المدى بلا استعلام: `index.php:99` يدرج هذا
           الملف بـ`include` عادي لا بـ`load->view`، فمتغيرات العرض مشتركة
           معه. فلا نموذج ينادى ولا متحكم يمس. */
        $u_page = isset($page_name) ? (string) $page_name : '';

        /* ── المسار: العنوان يحمل صفه، والوصف وصف المادة ── */
        if ($u_page === 'site_path' && !empty($tq_path) && is_array($tq_path)) {

            /* اسم الصف ليس في `path_by_slug` (تجلب `cat_name` = المرحلة، وثلاث
               مسارات رياضيات كلها ابتدائية فالمرحلة لا تفرق). قراءة صف واحد
               مفهرس، ومشروطة بوجود المعرف. */
            $u_gr = '';
            $u_gid = (int) (isset($tq_path['grade_id']) ? $tq_path['grade_id'] : 0);
            if ($u_gid > 0) {
                $CI_u  =& get_instance();
                $u_row = $CI_u->db->select('name_ar')->from('grades')
                                  ->where('id', $u_gid)->limit(1)->get()->row_array();
                if ($u_row) $u_gr = trim((string) $u_row['name_ar']);
            }

            /* TQ-SUBJ-GRADE — ولا يزاد على عنوان يحمل تمييزه اصلا: المسار ٧
               عنوانه «برنامج الرياضيات — السادس»، فالحاق الصف يعطي شرطتين
               في سطر واحد. الحارس نفسه المستعمل في `tqs_curriculum()`. */
            $u_t = trim((string) $meta_title);
            if ($u_gr !== '' && mb_strpos($u_t, '—') === false && mb_strpos($u_t, $u_gr) === false) {
                $meta_title = $u_t . ' — ' . $u_gr;
                $og_title   = $meta_title;
            }

            /* والوصف من وصف المسار نفسه — وهو مكتوب ومميز لكل مادة وصف.
               يطعم بالصف والمعلم: «الاعداد النسبية وعملياتها» وحدها لا تقول
               لمن هي ولا من يشرحها. */
            $u_d = trim((string) (isset($tq_path['short_description']) ? $tq_path['short_description'] : ''));
            if ($u_d !== '') {
                $u_tail = array();
                if ($u_gr !== '') $u_tail[] = $u_gr;
                $u_tn = trim((string) (isset($tq_path['teacher_name']) ? $tq_path['teacher_name'] : ''));
                if ($u_tn !== '') $u_tail[] = t('مع الأستاذ ') . $u_tn;
                $meta_description = $u_d . ($u_tail ? ' | ' . implode(' — ', $u_tail) . '.' : '');
                $og_description   = $meta_description;
            }
        }

        /* ── الباقة: الوصف يقول ما تفتحه فعلا ── */
        if ($u_page === 'site_plan' && !empty($tq_bundle) && is_array($tq_bundle)) {
            $u_b = $tq_bundle;
            $u_n = trim((string) (isset($u_b['name']) ? $u_b['name'] : ''));
            $u_t2 = isset($u_b['totals']) && is_array($u_b['totals']) ? $u_b['totals'] : array();
            $u_ng = isset($u_b['grades']) && is_array($u_b['grades']) ? count($u_b['grades']) : 0;
            $u_ns = isset($u_t2['subjects']) ? (int) $u_t2['subjects'] : 0;
            $u_nl = isset($u_t2['lessons'])  ? (int) $u_t2['lessons']  : 0;

            $u_seg = array();
            if ($u_ng) $u_seg[] = t('____ صفوف دراسية', array($u_ng));
            if ($u_ns) $u_seg[] = t('____ مادة جاهزة', array($u_ns));
            if ($u_nl) $u_seg[] = t('____ درسا مصورا', array($u_nl));

            if ($u_n !== '') {
                $meta_description = $u_n . t('. اشتراك واحد يفتح المرحلة كاملة')
                    . ($u_seg ? ': ' . implode(t(' و'), $u_seg) : '')
                    . t('، ومعاينة مجانية قبل الاشتراك.');
                $og_description = $meta_description;
            }
        }

        /* ── الكتالوج: صفحة الترقيم الثانية ليست الاولى ── */
        if ($u_page === 'site_catalog') {
            $CI_p = &get_instance();
            $u_pg = (int) $CI_p->input->get('page', true);
            if ($u_pg > 1) {
                $meta_title       = trim((string) $meta_title) . t(' — صفحة ____', array($u_pg));
                $og_title         = $meta_title;
                $meta_description = trim((string) $meta_description) . t(' (صفحة ____)', array($u_pg));
                $og_description   = $meta_description;
            }
        }

        /* وحارس أخير: صفحة لا صف لها في `seo_fields` ولا فرع اعلاه ترث وصف
           المنصة العام حرفا بحرف — وكانت تسع صفحات تتشاركه. والعنوان يميزها
           بلا اختراع: «المعلمون» و«الطلاب» و«المسابقات» كلها عناوين مكتوبة.
           فيصدر الوصف بالعنوان ثم يتبعه الوصف العام. */
        $u_gen = trim((string) get_settings('website_description'));
        $u_now = trim((string) $meta_description);
        if ($u_gen !== '' && $u_now === $u_gen) {
            $u_pt = trim((string) (isset($page_title) ? $page_title : ''));
            if ($u_pt !== '' && mb_strpos($u_gen, $u_pt) === false) {
                $meta_description = $u_pt . ' — ' . $u_gen;
                $og_description   = $meta_description;
            }
        }

        /* TQ-SEO-CANONICAL — الكنونيكال يرتد الى الرابط الحالي حين لا صف
           له في جدول `seo`. كان `''` فارغا في ٢٧ صفحة من ٣٨، والفارغ يعني
           «هذه الصفحة بعنوانها الحالي بكل معاملاته» — فـ`?utm_` و`?fbclid`
           و`?cb` تصير صفحات مستقلة تتنافس على المحتوى نفسه.
           والقيمة كانت موجودة في الصفحة اصلا: وسم `hreflang` يطبع الرابط
           الصحيح كاملا. فالقيمة لم تكن مفقودة، كانت لا توضع في مكانها. */
        if (trim((string) $canonical_url) === '') {
            $CI_c   =& get_instance();
            $c_path = trim((string) $CI_c->uri->uri_string(), '/');

            /* معامل يبقى ان كان يغير المحتوى فعلا: صفحة الترقيم الثانية
               ليست الاولى، وتصنيف الكتالوج ليس كله. وما عداها — التتبع
               وكسر الكاش — لا يصنع صفحة، فاسقاطه هو الغرض من الكنونيكال. */
            $c_keep = array('page', 'category', 'type', 'sort', 'grade', 'stage');
            $c_qs   = array();
            foreach ($c_keep as $c_k) {
                $c_v = $CI_c->input->get($c_k, true);
                if ($c_v !== null && $c_v !== '' && !is_array($c_v)) $c_qs[$c_k] = $c_v;
            }
            $canonical_url = base_url($c_path) . ($c_qs ? '?' . http_build_query($c_qs) : '');
        }

        /* TQ-SEO-TEACHER — صفحة المعلم تعرّف بصاحبها، لا بالمنصة.

           كانت تخرج بثلاثة نواقص، وكلها تظهر خارج الموقع لا داخله:

           · **الوصف** اسم المعلم ثم جملة المنصة العامة نفسها التي تحملها
             كل صفحة — فنتيجة البحث تقول من هو ولا تقول ماذا يدرّس.
           · **`og:image` فارغة**: مشاركة الرابط في واتساب أو تويتر تخرج
             سطرا بلا وجه، وصفحة عن شخص بلا صورته أضعف ما تشارك.
           · **`json_ld` = `{}`**: لا `Person` ولا `jobTitle` ولا انتماء،
             فمحرك البحث يقرأ صفحة نصية لا شخصا في مؤسسة تعليمية.

           والبيانات كلها في المدى بلا استعلام: `index.php` يدرج هذا
           الملف بـ`include` عادي، و`Taqdar::instructor_page()` يمرر
           `tq_teacher`. فلا نموذج ينادى ولا متحكم يمس. */
        if (isset($page_name) && $page_name === 'instructor_page'
            && !empty($tq_teacher) && is_array($tq_teacher)) {

            $t_name  = trim((string) ($tq_teacher['name']  ?? ''));
            $t_title = trim((string) ($tq_teacher['title'] ?? ''));
            $t_bio   = trim((string) ($tq_teacher['bio']   ?? ''));

            if ($t_name !== '') {
                $t_bits = array();
                if ($t_title !== '') $t_bits[] = $t_title;
                if ($t_bio   !== '') $t_bits[] = t('يدرّس ') . $t_bio;

                $meta_title = $t_name . ($t_title !== '' ? ' — ' . $t_title : '');
                $og_title   = $meta_title;

                $meta_description = $t_name
                    . ($t_bits ? ' — ' . implode(t('، '), $t_bits) : '')
                    . t(' في منصة تقدّر التعليمية. تصفّح برامجه ودروسه.');
                $og_description = $meta_description;

                /* الصورة تمر بالدالة نفسها التي تعرضها الصفحة: من لا
                   صورة له يحمل اسم ظله (`avatar-m`)، وهي تحله إلى ملف
                   حقيقي — فلا يخرج `og:image` إلى مسار لا يفتح. */
                if (function_exists('tqs_person_img')) {
                    $t_img = trim((string) ($tq_teacher['img'] ?? ''));
                    if ($t_img !== '') {
                        $t_url = tqs_person_img($t_img, '', true);
                        if ($t_url !== '') $og_image = $t_url;
                    }
                }

                $t_ld = array(
                    '@context' => 'https://schema.org',
                    '@type'    => 'Person',
                    'name'     => $t_name,
                    'url'      => ($canonical_url !== '' ? $canonical_url : current_url()),
                    'worksFor' => array(
                        '@type' => 'EducationalOrganization',
                        'name'  => 'منصة تقدّر',
                        'url'   => base_url(),
                    ),
                );
                if ($t_title !== '')      $t_ld['jobTitle']    = $t_title;
                if (!empty($og_image))    $t_ld['image']       = $og_image;
                if ($t_bio !== '')        $t_ld['description'] = $meta_description;
                if (!empty($tq_teacher['chips']) && is_array($tq_teacher['chips'])) {
                    $t_ld['knowsAbout'] = array_values($tq_teacher['chips']);
                }

                $json_ld = json_encode($t_ld,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        /* TQ-SEO-NOINDEX — لا يدعى الى فهرسة ما لا يفهرس. كان
           `index, follow` على ٣٨ صفحة من ٣٨ — ومنها الدخول والتسجيل
           وصفحة الدفع **وصفحة ٤٠٤ نفسها**، فنتيجة بحث تقود الى خطأ.
           و`follow` تبقى: الروابط تتبع وان لم تفهرس الصفحة. */
        $CI_r    =& get_instance();
        $r_first = (string) $CI_r->uri->segment(1);
        $r_priv  = array('login', 'sign_up', 'checkout', 'student', 'teacher',
                         'parent', 'admin', 'payment', 'cart', 'taqdar_admin');
        $r_code  = function_exists('http_response_code') ? (int) http_response_code() : 200;
        if (in_array($r_first, $r_priv, true) || $r_code >= 400) {
            $meta_robot = 'noindex, follow';
        }
    ?>

    <!-- Meta Tags -->
    <meta name="description" content="<?php echo htmlspecialchars($meta_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($meta_keywords, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="robots" content="<?php echo $meta_robot; ?>">
    <meta name="author" content="<?php echo htmlspecialchars($meta_author, ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo htmlspecialchars($meta_title, ENT_QUOTES, 'UTF-8') . ' | ' . t(get_settings('system_name')); ?></title>

    <!-- Canonical URL -->
    <link rel="canonical" href="<?php echo htmlspecialchars($canonical_url, ENT_QUOTES, 'UTF-8'); ?>">

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?php echo htmlspecialchars($og_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($og_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($og_image, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?php echo current_url(); ?>">
<?php /* الوسوم التي كانت غائبة: بلا `og:type` تصنف الصفحة افتراضا،
        وبلا `twitter:card` تظهر المشاركة سطرا بلا صورة، وبلا `og:locale`
        لا يعرف أن المحتوى عربي. و`hreflang` يقول للفهرس إن هذه هي
        النسخة العربية الوحيدة — الموقع أحادي اللغة. */ ?>
    <meta property="og:type" content="website">
    <meta property="og:locale" content="ar_SA">
    <meta property="og:site_name" content="منصة تقدر">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($og_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($og_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($og_image, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="alternate" hreflang="ar" href="<?php echo current_url(); ?>">
    <link rel="alternate" hreflang="x-default" href="<?php echo current_url(); ?>">

    <!-- JSON-LD (Schema Markup) -->
    <script type="application/ld+json">
        <?php echo $json_ld; ?>
    </script>
