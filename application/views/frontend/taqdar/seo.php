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
            if (!empty($seo['og_image']) && is_file(FCPATH . $og_image_path)) {
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
                $og_image = (!empty($data['banner']) && is_file(FCPATH . $blog_banner))
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

        $CI_ix_r0 =& get_instance();
        $CI_ix_r  = (string) $CI_ix_r0->uri->segment(1);

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

        /* ── كتب الصف: العنوان يحمل صفه، والوصف يعد ما فيه ──
 
           وهي الصفحات التي تجاب بها عبارة البحث الحقيقية: «كتاب العلوم
           رابع ابتدائي». فوصف واحد لتسع صفحات يجعلها تتنافس على عبارة
           واحدة بدل ان تجيب كل واحدة عبارتها. */
        if ($u_page === 'site_books_grade' && !empty($tq_grade) && is_array($tq_grade)) {
            $u_gn = trim((string) $tq_grade['name_ar']);
            $u_bs = isset($tq_books) && is_array($tq_books) ? $tq_books : array();

            $meta_title = t('كتب ') . $u_gn;
            $og_title   = $meta_title;

            /* المواد تعد من الكتب نفسها لا من جدول المواد: الوصف يعد ما
               في الصفحة، ورقم يزيد على ما يرى نقض لا اغراء. */
            $u_subj = array();
            foreach ($u_bs as $u_b) {
                $u_s = trim((string) $u_b['subject']);
                if ($u_s !== '' && !in_array($u_s, $u_subj, true)) $u_subj[] = $u_s;
            }

            /* «لـ» + «الصف» تعطي «لـالصف»: اللام تدغم في «ال» فتكتب
               «للصف»، لكن اسماء الصفوف لا تتفق («الاول المتوسط»). فشرطة
               فاصلة تصح مع الاثنين ولا تكسر لغة. */
            $meta_description = t('كتب المنهج السعودي — ') . $u_gn
                . ($u_bs ? ' — ' . t('____ كتابا', array(count($u_bs))) : '')
                . ($u_subj ? ' ' . t('في') . ' ' . implode(t(' و'), array_slice($u_subj, 0, 5))
                           . (count($u_subj) > 5 ? t(' وغيرها') : '') : '')
                . t('. تصفح مجانا بلا تسجيل.');
            $og_description = $meta_description;
        }

        /* ── فهارس الأقسام: الوصف يعدّ ما فيها ──

           `/books` و`/summaries` و`/teachers` و`/catalog` كانت تحمل جملة
           المنصّة نفسها — أربع صفحات هي أعلى ما في الموقع قيمةً بعد
           الرئيسية، ونتيجة البحث لا تقول عن أيٍّ منها ماذا فيها.

           والأعداد تُقرأ لا تُكتب: `COUNT` واحد على عمود مفهرس، وعلى
           هذه المسارات وحدها. فرقمٌ في الوصف يكذب بعد شهرٍ أسوأ من
           رقمٍ لا يُذكر. */
        $ix_route = trim((string) $CI_ix_r, '/');
        if (in_array($ix_route, array('books', 'summaries', 'teachers'), true)
            || $u_page === 'site_catalog') {
            $CI_ix =& get_instance();
            $ix_d  = '';
            if ($ix_route === 'books') {
                $n = (int) $CI_ix->db->where('status', 'published')
                          ->where("(slug NOT LIKE 'sum-%')", null, false)
                          ->count_all_results('books');
                $ix_d = t('____ كتابا من المنهج السعودي المعتمد للمرحلتين الابتدائية والمتوسطة', array($n))
                      . t('، مرتبة بالصف والمادة — كتاب الطالب والنشاط والتمارين، تتصفح وتحمل مجانا بلا تسجيل.');
            } elseif ($ix_route === 'summaries') {
                $n = (int) $CI_ix->db->where('status', 'published')
                          ->where("(slug LIKE 'sum-%')", null, false)
                          ->count_all_results('books');
                $ix_d = t('____ ملخصا ومراجعة وخريطة ذهنية وورقة عمل للمنهج السعودي', array($n))
                      . t('، مرتبة بالصف والمادة — مراجعة سريعة قبل الاختبار، مجانا بلا تسجيل.');
            } elseif ($ix_route === 'teachers') {
                $n = (int) $CI_ix->db->where('is_public', 1)->where('is_instructor', 1)
                          ->count_all_results('users');
                $ix_d = t('____ معلما ومعلمة في منصة تقدر', array($n))
                      . t('، لكل واحد صفحته وبرامجه ودروسه المصورة في مادته وصفه — اختر معلم ابنك قبل أن تشترك.');
            } else {
                $n = (int) $CI_ix->db->where('status', 'published')->where('course_id >', 0)
                          ->count_all_results('paths');
                $ix_d = t('____ برنامجا من المنهج السعودي للمرحلتين الابتدائية والمتوسطة', array($n))
                      . t(': دروس مصورة واختبار بعد كل درس، مرتبة بالمرحلة والصف والمادة.');
            }
            if ($ix_d !== '') { $meta_description = $ix_d; $og_description = $ix_d; }
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
            /* ⚠ `category` كان في القائمة وهو **معامِل ميّت**: الكتالوج
               يرشّح بـ`cat` و`grade`، و`/catalog?category=x` يعرض
               الكتالوج كاملًا — فإبقاؤه كان يصنع من كلّ رابط تتبّع
               بمعامِل `category` صفحةً مستقلّة تنافس أصلها. */
            $c_keep = array('page', 'cat', 'type', 'sort', 'grade', 'stage');
            $c_qs   = array();
            foreach ($c_keep as $c_k) {
                $c_v = $CI_c->input->get($c_k, true);
                if ($c_v !== null && $c_v !== '' && !is_array($c_v)) $c_qs[$c_k] = $c_v;
            }
            $canonical_url = base_url($c_path) . ($c_qs ? '?' . http_build_query($c_qs) : '');
        }

        /* الفلاتر والبحث والترقيم خدمات داخل الكتالوج، لا صفحات بحث
           مستقلة. إبقاؤها indexable ولّد مئات روابط parameters بلا نقرات
           وسمح لـGoogle باختيار canonical مختلف عن المعلن. */
        $CI_cf =& get_instance();
        if (trim((string) $CI_cf->uri->uri_string(), '/') === 'catalog' && !empty($_GET)) {
            $canonical_url = base_url('catalog');
            $meta_robot = 'noindex, follow';
        }

        /* نتائج البحث الداخلي ورسائل النشرة صفحات حالة وليست وجهات بحث.
           canonical وحده لا يمنع Google من إنفاق الزحف عليها، لذلك تبقى
           قابلة لتتبّع الروابط لكن لا تدخل الفهرس. */
        $tq_query_path = trim((string) $CI_cf->uri->uri_string(), '/');
        if ($tq_query_path === 'search'
            || ($tq_query_path === 'blog' && !empty($_GET))) {
            $canonical_url = base_url($tq_query_path);
            $meta_robot = 'noindex, follow';
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

        /* TQ-SEO-BOOKDESC — وصف الكتاب من الكتاب لا من المنصّة.

           ٤١٦ صفحة كتاب وملخّص كانت تخرج بوصفٍ واحد: «{العنوان} — منصة
           تقدر التعليمية — دورات ومحتوى تعليمي عربي…». والعنوان وحده
           يفرّق بينها، والباقي جملةٌ مكرَّرة ٤١٦ مرّة تُقرأ في نتيجة
           البحث فلا تقول عن الكتاب شيئًا.

           والوصف مكتوبٌ في الصفّ نفسه (‏٧٤–١٠١ كلمة)، فيؤخذ منه أوّلُ
           ١٥٥ حرفًا عند حدّ كلمة — وهو ما يعرضه جوجل. */
        if (!empty($tq_book) && is_array($tq_book)) {
            $bk_d = trim(strip_tags((string) (isset($tq_book['description']) ? $tq_book['description'] : '')));
            if ($bk_d !== '') {
                if (mb_strlen($bk_d) > 158) {
                    $bk_cut = mb_substr($bk_d, 0, 158);
                    $bk_sp  = mb_strrpos($bk_cut, ' ');
                    $bk_d   = ($bk_sp !== false ? mb_substr($bk_cut, 0, $bk_sp) : $bk_cut) . '…';
                }
                $meta_description = $bk_d;
                $og_description   = $bk_d;
            }
        }

        /* TQ-SEO-LEGACY — صفحة المقرر القديمة نسخة ثانية من البرنامج.

           `‎/home/course/{عنوان}/{معرف}` ترد **٢٠٠** لا ٣٠١، وتحمل
           كنونيكال يشير الى نفسها بعنوان عربي مرمز (`%d8%a7...`)، بينما
           `‎/path/{slug}` تعرض المحتوى نفسه من الجدول نفسه. فصفحتان
           تتنافسان على مقرر واحد، وجوجل يختار — وقد يختار الاقبح رابطا.

           والاصلاح كنونيكال لا حذف: الروابط القديمة موزعة خارج الموقع،
           وحذفها يفقدها، وتوجيهها ٣٠١ يكسر لوحة الاكاديمي القديمة التي
           تستعملها. فتبقى تعمل، وتقول للفهرس: الاصل هناك. */
        $CI_l  =& get_instance();
        $l_seg = array($CI_l->uri->segment(1), $CI_l->uri->segment(2));
        if ($l_seg[0] === 'home' && $l_seg[1] === 'course') {
            $l_cid = 0;
            for ($l_i = 5; $l_i >= 3; $l_i--) {
                $l_v = (string) $CI_l->uri->segment($l_i);
                if ($l_v !== '' && ctype_digit($l_v)) { $l_cid = (int) $l_v; break; }
            }
            if ($l_cid > 0) {
                $l_row = $CI_l->db->select('slug')->where('course_id', $l_cid)
                                  ->order_by('id', 'ASC')->limit(1)
                                  ->get('paths')->row_array();
                if (!empty($l_row['slug'])) {
                    $canonical_url = base_url('path/' . $l_row['slug']);
                }
            }
        }

        /* TQ-SEO-BLOGDUP — للمقال وجهان، والمعلَن يجب أن يكون واحدًا.

           `‎/blog/details/{عنوان عربيّ}/{معرف}` و`‎/blog/{slug}/{معرف}`
           كلاهما يردّ ٢٠٠ ويحمل المقال نفسه. وصفحة المدوّنة تربط الثاني
           (‏`blogs`.`slug` لاتينيّ مخزَّن)، بينما الأوّل يخرج كنونيكاله
           سطرًا من `%d8%aa%d9%86...`. فالعربيّ يشير إلى اللاتينيّ. */
        if ($l_seg[0] === 'blog' && $l_seg[1] === 'details') {
            $b_id = (int) $CI_l->uri->segment(4);
            if ($b_id > 0) {
                $b_row = $CI_l->db->select('slug')->where('blog_id', $b_id)
                                  ->get('blogs')->row_array();
                if (!empty($b_row['slug'])) {
                    $canonical_url = base_url('blog/' . $b_row['slug'] . '/' . $b_id);
                }
            }
        }

        /* TQ-SEO-BLOGS — `/blogs` و`/blog` صفحتان بالمقالات نفسها،
           كلٌّ بكنونيكال يشير إلى نفسه. والقالب الذي يربطه الموقع
           ويحمل تصميم تقدّر هو `/blog`. فالوارثة تشير إليه. */
        if ($l_seg[0] === 'blogs' && (string) $CI_l->uri->segment(2) === '') {
            $canonical_url = base_url('blog');
        }

        /* TQ-SEO-CRUMB — فتات الخبز، وكانت غائبة عن الموقع كله.

           تفيد مرتين: تعطي نتيجة البحث سطر المسار بدل الرابط الخام،
           وتقول للفهرس ان الصفحة العميقة ابنة قسم لا صفحة يتيمة. والاسم
           الورقي يؤخذ من `$meta_title` — وهو محسوب اعلاه لكل نوع صفحة،
           فلا استعلام ولا خريطة عناوين ثانية. */
        $tq_crumb = array();
        $c_leaf   = trim((string) $meta_title);
        $c_s1     = (string) $CI_l->uri->segment(1);
        $c_s2     = (string) $CI_l->uri->segment(2);
        $c_parent = null;
        if ($c_s1 === 'path' && $c_s2 !== '')        $c_parent = array(t('البرامج'),   'catalog');
        elseif ($c_s1 === 'book' && $c_s2 !== '')    $c_parent = (strpos($c_s2, 'sum-') === 0)
                                                        ? array(t('الملخّصات'), 'summaries')
                                                        : array(t('الكتب'),     'books');
        elseif ($c_s1 === 'instructor' && $c_s2 !== '') $c_parent = array(t('المعلّمون'), 'teachers');
        elseif ($c_s1 === 'plan' && $c_s2 !== '')    $c_parent = array(t('الباقات'),   'plans');
        elseif ($c_s1 === 'blog' && $c_s2 !== '')    $c_parent = array(t('المدوّنة'),   'blog');

        if ($c_parent !== null && $c_leaf !== '') {
            $tq_crumb = array(
                '@context'        => 'https://schema.org',
                '@type'           => 'BreadcrumbList',
                'itemListElement' => array(
                    array('@type' => 'ListItem', 'position' => 1,
                          'name'  => t('الرئيسية'), 'item' => base_url()),
                    array('@type' => 'ListItem', 'position' => 2,
                          'name'  => $c_parent[0], 'item' => base_url($c_parent[1])),
                    array('@type' => 'ListItem', 'position' => 3,
                          'name'  => $c_leaf,
                          'item'  => ($canonical_url !== '' ? $canonical_url : current_url())),
                ),
            );
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
        if (isset($page_name) && $page_name === 'site_path'
            && isset($tq_detail['totals']['lessons'])
            && (int) $tq_detail['totals']['lessons'] < 1) {
            $meta_robot = 'noindex, follow';
        }

        /* فشل الغلاف لا يجب أن يخرج URL لمجلد أو صفحة 403. لا نتحقق من
           مصادر خارجية هنا؛ أما ملفات نطاقنا فيجب أن تكون ملفات صور فعلية. */
        if (!empty($tq_book) && is_array($tq_book)
            && !empty($tq_book['cover']) && function_exists('tqs_img')) {
            $og_image = tqs_img($tq_book['cover'], 'subj-math');
        }
        $tq_og_host = (string) parse_url($og_image, PHP_URL_HOST);
        $tq_site_host = (string) parse_url(base_url(), PHP_URL_HOST);
        $tq_og_path = (string) parse_url($og_image, PHP_URL_PATH);
        if (($tq_og_host === '' || $tq_og_host === $tq_site_host)
            && ($tq_og_path === '' || !is_file(FCPATH . ltrim($tq_og_path, '/')))) {
            $og_image = base_url($tq_og);
        }

        $tq_og_type = 'website';
        $tq_article_ld = null;
        if ($r_first === 'blog' && !empty($blog_details) && is_array($blog_details)) {
            $tq_og_type = 'article';
            $tq_blog_title = trim((string) ($blog_details['title'] ?? ''));
            $tq_blog_desc = trim(strip_tags((string) ($blog_details['description'] ?? '')));
            if ($tq_blog_desc === '') $tq_blog_desc = $meta_description;
            if (mb_strlen($tq_blog_desc) > 158) {
                $tq_blog_cut = mb_substr($tq_blog_desc, 0, 158);
                $tq_blog_sp  = mb_strrpos($tq_blog_cut, ' ');
                $tq_blog_desc = ($tq_blog_sp !== false ? mb_substr($tq_blog_cut, 0, $tq_blog_sp) : $tq_blog_cut) . '…';
            }
            $meta_description = $tq_blog_desc;
            $og_description   = $tq_blog_desc;
            $tq_article_ld = array(
                '@context' => 'https://schema.org',
                '@type' => 'BlogPosting',
                'headline' => $tq_blog_title !== '' ? $tq_blog_title : $meta_title,
                'description' => $tq_blog_desc,
                'image' => $og_image,
                'mainEntityOfPage' => array('@type' => 'WebPage', '@id' => $canonical_url),
                'author' => array('@type' => 'Organization', 'name' => 'منصة تقدر'),
                'publisher' => array('@type' => 'EducationalOrganization', 'name' => 'منصة تقدر', 'url' => base_url()),
            );
            foreach (array('added_date' => 'datePublished', 'updated_date' => 'dateModified') as $tq_col => $tq_key) {
                $tq_stamp = isset($blog_details[$tq_col]) ? (int) $blog_details[$tq_col] : 0;
                if ($tq_stamp > 0) $tq_article_ld[$tq_key] = date('c', $tq_stamp);
            }
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
    <meta property="og:url" content="<?php echo htmlspecialchars(($canonical_url !== '' ? $canonical_url : current_url()), ENT_QUOTES, 'UTF-8'); ?>">
<?php /* الوسوم التي كانت غائبة: بلا `og:type` تصنف الصفحة افتراضا،
        وبلا `twitter:card` تظهر المشاركة سطرا بلا صورة، وبلا `og:locale`
        لا يعرف أن المحتوى عربي. و`hreflang` يقول للفهرس إن هذه هي
        النسخة العربية الوحيدة — الموقع أحادي اللغة. */ ?>
    <meta property="og:type" content="<?php echo $tq_og_type; ?>">
    <meta property="og:locale" content="ar_SA">
    <meta property="og:site_name" content="منصة تقدر">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($og_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($og_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($og_image, ENT_QUOTES, 'UTF-8'); ?>">
<?php /* TQ-SEO-ALT — الوسمان كانا يطبعان `current_url()` فيحتفظان
        بالمعاملات التي يجردها الكنونيكال — فـ`/catalog?utm_source=x` تقول
        «اصلي /catalog» وتقول في السطر التالي «نسختي العربية هي انا بمعاملي».
        اشارتان متضاربتان لمحرك واحد. فصارتا تتبعان الكنونيكال نفسه. */
      $tq_alt = ($canonical_url !== '' ? $canonical_url : current_url()); ?>
    <link rel="alternate" hreflang="ar" href="<?php echo htmlspecialchars($tq_alt, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="alternate" hreflang="x-default" href="<?php echo htmlspecialchars($tq_alt, ENT_QUOTES, 'UTF-8'); ?>">

    <!-- JSON-LD (Schema Markup) -->
<?php /* TQ-SEO-LDEMPTY — كان الوسم يطبع `{}` على كل صفحة لا صف لها في
        جدول `seo`: كائن فارغ لا يقول شيئا، ويظهر في كل فاحص بيانات
        مهيكلة سطرا بلا نوع. فالفراغ لا يطبع. */
      $tq_ld_out = trim((string) $json_ld);
      if ($tq_ld_out !== '' && $tq_ld_out !== '{}' && $tq_ld_out !== '[]'): ?>
    <script type="application/ld+json">
        <?php echo $json_ld; ?>
    </script>
<?php endif; ?>
<?php if (!empty($tq_crumb)): ?>
    <script type="application/ld+json"><?php
        echo json_encode($tq_crumb, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    ?></script>
<?php endif; ?>
<?php if ($tq_article_ld !== null): ?>
    <script type="application/ld+json"><?php
        echo json_encode($tq_article_ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    ?></script>
<?php endif; ?>
