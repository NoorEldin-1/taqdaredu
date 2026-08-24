<?php defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * ربط الكورس بالمنصة — الجسر بين `course` و`paths`.
 *
 * ═══ لماذا وجد ═══
 *
 * الكورس في هذا التركيب **وعاء دروس لا أكثر**: جدول `course` لا يحمل
 * صفا ولا مادة، ولا يقرؤه الكتالوج، ولا يعرفه محرك الاشتراكات. ومن
 * أنشأ كورسا من اللوحة ونشره وجده لا يظهر في `/catalog` ولا تفتحه باقة
 * ولا يصل إليه طالب — ولا شيء في الشاشة يقول له لماذا.
 *
 * والسبب أن **الجسر الوحيد هو `paths`**، وهو ما تثبته ثلاثة مواضع
 * مستقلة تقرأ منه كلها:
 *
 * ١ — `Taqdar_catalog_model::paths()` — لا يقرأ `course` إطلاقا. ما يظهر
 *     في الكتالوج صف في `paths` حالته `published`.
 * ٢ — `Taqdar_billing_model::subscription_grants()` — بنود الاشتراك
 *     (`path` · `subject` · `grade`) كلها تصل إلى الكورس **عبر
 *     `paths.course_id`**. فكورس بلا صف في `paths` لا تفتحه باقة البتة.
 * ٣ — `Taqdar_site_model::plans_for_course()` — الباقات التي تفتح كورسا
 *     تعرف من `paths.grade_id` لا من الكورس.
 *
 * فهذا النموذج يجعل ذلك الصف **أثرا لحفظ الكورس** لا عملا يدويا يذكره
 * المسؤول أو ينساه: يكتب المرحلة والصف والمادة في شاشة الكورس، فيقابله
 * برنامج يحمل العنوان نفسه والصورة نفسها والحالة نفسها.
 *
 * ═══ ما لا يفعله ═══
 *
 * لا يحذف برنامجا كتب بيد ولا يكتب فوق سعره ولا فوق مدته ولا فوق
 * ترتيبه: البرامج المستوردة (`tq_seed`) والمحررة من `taqdar_admin/paths`
 * تبقى ملك من كتبها. وهو يعدل من الحقول ما يشتق من الكورس وحده.
 */
class Taqdar_course_link_model extends CI_Model
{
    /** حالات الكورس التي تعني «منشور» في لغة البرامج. */
    private static function path_status($course_status)
    {
        return ((string) $course_status === 'active') ? 'published' : 'draft';
    }

    /** البرنامج المرتبط بكورس — أو `null`. */
    public function path_of($course_id)
    {
        $course_id = (int) $course_id;
        if ($course_id <= 0) return null;

        return $this->db->where('course_id', $course_id)
                        ->order_by('id', 'ASC')->limit(1)
                        ->get('paths')->row_array() ?: null;
    }

    /** الصف والمادة المسجلان لكورس — من البرنامج، وصفران إن لم يربط. */
    public function link_of($course_id)
    {
        $p = $this->path_of($course_id);
        return array(
            'path_id'    => $p ? (int) $p['id'] : 0,
            'grade_id'   => $p ? (int) $p['grade_id'] : 0,
            'subject_id' => $p ? (int) $p['subject_id'] : 0,
            'slug'       => $p ? (string) $p['slug'] : '',
            'status'     => $p ? (string) $p['status'] : '',
        );
    }

    /**
     * مسمى لاتيني فريد للبرنامج.
     *
     * لاتيني لا عربي عمدا: `paths.slug` يدخل في الرابط، والرابط العربي
     * يرد مشفرا فيصير سطرا من `%D8` لا يقرأ ولا ينسخ. والمعرف ملحق به
     * فلا يتصادم كورسان بالعنوان نفسه.
     */
    private function slug_for($title, $path_id)
    {
        $s = function_exists('slugify') ? (string) slugify((string) $title) : '';
        $s = trim(preg_replace('~[^a-z0-9]+~i', '-', $s), '-');
        if ($s === '') $s = 'path';
        return strtolower(substr($s, 0, 150)) . '-' . (int) $path_id;
    }

    /**
     * يوائم البرنامج مع الكورس.
     *
     * `$grade_id` و`$subject_id` صفران يعنيان «لا ربط»: البرنامج الموجود
     * يعاد إلى مسودة (فيغيب عن الكتالوج) ولا يحذف — حذفه يقطع اشتراكات
     * قائمة تشير إليه، وهي في `subscription_items` بمعرفه.
     *
     * ترد مصفوفة تصف ما جرى، لتقولها الشاشة للمسؤول بلغته.
     */
    public function sync($course_id, $grade_id, $subject_id)
    {
        $course_id  = (int) $course_id;
        $grade_id   = (int) $grade_id;
        $subject_id = (int) $subject_id;
        if ($course_id <= 0) return array('ok' => false, 'action' => 'none');

        $course = $this->db->where('id', $course_id)->get('course')->row_array();
        if (!$course) return array('ok' => false, 'action' => 'none');

        $existing = $this->path_of($course_id);

        /* لا صف ولا مادة: البرنامج القائم ينزل إلى مسودة، ولا ينشأ جديد. */
        if ($grade_id <= 0 || $subject_id <= 0) {
            if ($existing && (string) $existing['status'] === 'published') {
                $this->db->where('id', (int) $existing['id'])
                         ->update('paths', array('status' => 'draft'));
                return array('ok' => true, 'action' => 'unpublished', 'path_id' => (int) $existing['id']);
            }
            return array('ok' => true, 'action' => 'none',
                         'path_id' => $existing ? (int) $existing['id'] : 0);
        }

        /* المرحلة تشتق من الكورس: هي ما اختير في «التصنيف»، أو أبوه إن
           كان تصنيفا فرعيا — فلا يطالب المسؤول بكتابتها مرتين. */
        $cat = (int) $course['category_id'];
        if ($cat <= 0) $cat = (int) $course['sub_category_id'];

        /**
         * TQ-LINK-STOMP — الملء لا الكتابة فوق.
         *
         * كانت هذه الدالة تكتب `title` و`short_description` و`category_id`
         * في كل حفظ. والبرنامج **كائن تحريري له عنوانه**: يحرر من
         * `taqdar_admin/paths`، وبعضه مستورد بوصف مكتوب وصورة. فمن ربط
         * برنامجا قائما بكورس ثم حفظ الكورس **فقد عنوان برنامجه ووصفه**
         * وحل محلهما عنوان الكورس — ومرحلته تصير صفرا متى كان الكورس
         * بلا تصنيف، فيسقط من مرشح «القسم» في الكتالوج.
         *
         * فالقاعدة: ما كتب في البرنامج يبقى، وما كان فارغا يملأ من
         * الكورس. والاستثناء `status` وحده — وهو الحقل الذي **يجب** أن
         * يتبع الكورس، وإلا عرض الكتالوج برنامجا لكورس أوقف نشره.
         */
        $data = array(
            'subject_id' => $subject_id,
            'grade_id'   => $grade_id,
            'course_id'  => $course_id,
            'status'     => self::path_status($course['status']),
        );

        /** يكتب من الكورس ما لم يكن للبرنامج قيمة خاصة به. */
        $fill = function ($col, $value) use (&$data, $existing) {
            if ($value === '' || $value === 0) return;                 /* لا نكتب فراغا */
            if ($existing && trim((string) $existing[$col]) !== ''
                          && (string) $existing[$col] !== '0') return;  /* ولا فوق مكتوب */
            $data[$col] = $value;
        };

        $fill('title',             (string) $course['title']);
        $fill('short_description', (string) $course['short_description']);
        $fill('category_id',       $cat);
        $fill('teacher_id',        (int) $course['creator']);

        if ($existing) {
            $pid = (int) $existing['id'];
            if ((string) $existing['slug'] === '') {
                $data['slug'] = $this->slug_for($existing['title'] ?: $course['title'], $pid);
            }
            $this->db->where('id', $pid)->update('paths', $data);
            $reach = $this->reach($data['status'], $grade_id, $subject_id, $course_id);
            return array('ok' => true, 'action' => 'updated', 'path_id' => $pid, 'reached' => $reach);
        }

        $this->db->insert('paths', $data);
        $pid = (int) $this->db->insert_id();
        $this->db->where('id', $pid)->update('paths', array(
            'slug'     => $this->slug_for($course['title'], $pid),
            'tq_order' => $pid,
        ));
        $reach = $this->reach($data['status'], $grade_id, $subject_id, $course_id);
        return array('ok' => true, 'action' => 'created', 'path_id' => $pid, 'reached' => $reach);
    }

    /**
     * البرنامج صار منشورا: يصل إلى من يملك نطاقه **الآن** لا عند
     * اشتراكه القادم — TQ-ENROL-STALE.
     *
     * `enrol` تكتب مرة واحدة عند التفعيل، فمقرر ينشر بعد البيع كان
     * لا يبلغ مشتركا قائما أبدا: يشاهد دروسه من الوصول الحي، ويقرأ
     * «لا كورسات بعد» في القوائم التي تضم الجدول. فالنشر ينادي
     * التجسيد على نطاقه وحده.
     *
     * ولا يبطل النشر إن تعثر: المهمة الدورية `taqdar_cron enrolments`
     * تلحق ما فات.
     *
     * @return int عدد الاشتراكات التي تغيرت
     */
    private function reach($status, $grade_id, $subject_id, $course_id)
    {
        if ((string) $status !== 'published') return 0;

        try {
            $this->load->model('taqdar_billing_model', 'tq_bill');
            if (!method_exists($this->tq_bill, 'resync_scope')) return 0;
            return (int) $this->tq_bill->resync_scope($grade_id, $subject_id, $course_id);
        } catch (Throwable $e) {
            log_message('error', 'TQ-LINK reach: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * ماذا ينقص هذا الكورس ليصل إلى طالب؟
     *
     * قائمة عوائق مرتبة بترتيب معالجتها، كل عائق نصه ورابط إصلاحه.
     * وهي ما يعرض في شاشة التحرير بدل أن يترك المسؤول يحزر لماذا لا
     * يظهر ما نشره.
     */
    public function diagnose($course_id)
    {
        $course_id = (int) $course_id;
        $course    = $this->db->where('id', $course_id)->get('course')->row_array();
        if (!$course) return array();

        $out  = array();
        $link = $this->link_of($course_id);

        $lessons = (int) $this->db->where('course_id', $course_id)
                                  ->where('lesson_type !=', 'quiz')
                                  ->count_all_results('lesson');
        $sections = (int) $this->db->where('course_id', $course_id)->count_all_results('section');

        if ($sections === 0) {
            $out[] = array('warn', 'لا أقسام في هذا الكورس',
                'القسم وعاء الدروس، ولا يحفظ درس بلا قسم.',
                site_url('admin/course_form/course_edit/' . $course_id . '?tab=curriculum'));
        } elseif ($lessons === 0) {
            $out[] = array('warn', 'لا دروس في هذا الكورس',
                'الطالب يفتح صفحته فيجد منهجا فارغا.',
                site_url('admin/course_form/course_edit/' . $course_id . '?tab=curriculum'));
        }

        if ($link['grade_id'] <= 0 || $link['subject_id'] <= 0) {
            $out[] = array('warn', 'لا صف ولا مادة',
                'بغيرهما لا يظهر الكورس في «المواد والبرامج» ولا تفتحه باقة — '
              . 'فالباقة تمنح صفا ومادة لا كورسا بعينه.',
                site_url('admin/course_form/course_edit/' . $course_id . '?tab=basic'));
        } else {
            if ((string) $course['status'] !== 'active') {
                $out[] = array('info', 'الكورس غير منشور',
                    'حالته «' . html_escape((string) $course['status']) . '»، فلا يعرض في الموقع العام.',
                    site_url('admin/course_form/course_edit/' . $course_id . '?tab=basic'));
            }

            $this->load->model('taqdar_site_model', 'tq_sm');
            $plans = $this->tq_sm->plans_for_course($course_id);
            if (!$plans) {
                $out[] = array('info', 'لا باقة تفتح هذا الكورس',
                    'صفه غير مدرج في نطاق أي باقة نشطة، فلا سبيل للطالب إلى شرائه.',
                    site_url('taqdar_admin/plans'));
            }
        }

        return $out;
    }
}
