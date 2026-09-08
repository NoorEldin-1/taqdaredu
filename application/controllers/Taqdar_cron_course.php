<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * إنشاء المقرَّرات الناقصة — أداة سطر أوامر.
 *
 * ثلاث موادّ لها فيديوهات على فيميو ولا مقرَّر لها على المنصّة. وتُنشأ
 * **مسودّاتٍ**: `course.status='draft'` يجعل `path_status()` يردّ `draft`،
 * فلا تُعرض في الكتالوج ولا تُباع حتّى يراجعها صاحب المنصّة وينشرها.
 *
 * والفاعل هو **المعلّم نفسه** لا الإدارة: `save_course()` تكتب
 * `creator = actor.id`، ومنه يملأ `Taqdar_course_link_model::sync()` حقل
 * `paths.teacher_id`. فالتنفيذ بصفة الإدارة كان ينسب المقرَّر إلى حساب
 * الاختبار الإداريّ ويحرم المعلّم من حصّته.
 *
 * و`sub_category_id` ليس ترفًا: `sync()` يقرأ منه `category_id` للمسار،
 * وبدونه يسقط البرنامج من مرشّح «القسم» في الكتالوج.
 *
 * الأفعال:  make [apply]
 */
class Taqdar_cron_course extends CI_Controller
{
    /** ابتدائيّ ٣ · متوسط ٤ — من جدول `category`. */
    const CAT_PRIMARY = 3;
    const CAT_MIDDLE  = 4;

    private function specs()
    {
        return array(
            array(
                'title'      => 'الرياضيات — الثالث المتوسط',
                'subject_id' => 9,   'subject' => 'الرياضيات',
                'grade_id'   => 19,  'grade'   => 'الثالث المتوسط',
                'category'   => self::CAT_MIDDLE,
                'teacher_id' => 328, 'teacher' => 'عماد الكومي',
                'section'    => 'الوحدة الأولى',
                'short'      => 'منهج الرياضيات للصف الثالث المتوسط مشروحًا درسًا درسًا.',
            ),
            array(
                'title'      => 'الرياضيات — الأول المتوسط',
                'subject_id' => 9,   'subject' => 'الرياضيات',
                'grade_id'   => 17,  'grade'   => 'الأول المتوسط',
                'category'   => self::CAT_MIDDLE,
                'teacher_id' => 327, 'teacher' => 'عبد الله الباز',
                'section'    => 'الوحدة الأولى',
                'short'      => 'منهج الرياضيات للصف الأول المتوسط مشروحًا درسًا درسًا.',
            ),
            array(
                'title'      => 'الدراسات الاجتماعية — الثالث المتوسط',
                'subject_id' => 17,  'subject' => 'الدراسات الاجتماعية',
                'grade_id'   => 19,  'grade'   => 'الثالث المتوسط',
                'category'   => self::CAT_MIDDLE,
                'teacher_id' => 331, 'teacher' => 'محمد العجمي',
                'section'    => 'الوحدة الأولى',
                'short'      => 'منهج الدراسات الاجتماعية للصف الثالث المتوسط مشروحًا درسًا درسًا.',
            ),
            array(
                'title'      => 'الدراسات الاجتماعية — الصف السادس الابتدائي',
                'subject_id' => 17,  'subject' => 'الدراسات الاجتماعية',
                'grade_id'   => 16,  'grade'   => 'الصف السادس الابتدائي',
                'category'   => self::CAT_PRIMARY,
                'teacher_id' => 322, 'teacher' => 'أحمد الدسوقي',
                'section'    => 'الوحدة الأولى',
                'short'      => 'منهج الدراسات الاجتماعية للصف السادس الابتدائي مشروحًا درسًا درسًا.',
            ),
        );
    }

    public function __construct()
    {
        parent::__construct();
        if (!$this->input->is_cli_request()) show_404();
        $this->load->database();
        $this->load->model('taqdar_curriculum_model', 'curr');
    }

    public function index($apply = '') { $this->make($apply); }

    public function make($apply = '')
    {
        echo "المقرَّرات الناقصة\n" . str_repeat('─', 62) . "\n";
        $this->load->model('taqdar_course_link_model', 'link_m');
        $made = 0;

        foreach ($this->specs() as $s) {

            /* مسارات هذه المادّة وهذا الصفّ. وقد وُجد أن للثلاثة مسارات
               مبذورة قائمة (`tq_seed=1`) بلا مقرَّر — منشورة وفارغة. فلا
               يُنشأ مسار ثانٍ: يُربط المقرَّر بالقائم، وإلّا صار في
               الكتالوج برنامجان لمادّة واحدة في صفّ واحد. */
            $paths = $this->db->select('id, title, slug, course_id, status, tq_seed', false)
                              ->where('subject_id', (int) $s['subject_id'])
                              ->where('grade_id', (int) $s['grade_id'])
                              ->order_by('id', 'ASC')->get('paths')->result_array();

            $live = null; $empty = null;
            foreach ($paths as $p) {
                if ((int) $p['course_id'] > 0) { $live = $p; break; }
                if ($empty === null) $empty = $p;
            }

            if ($live) {
                echo '  — موجود سلفًا: ' . $s['title']
                   . '  ← المقرَّر ' . (int) $live['course_id'] . "\n";
                continue;
            }

            $t = $this->db->select('id')->where('id', (int) $s['teacher_id'])
                          ->where('status', 1)->get('users')->row_array();
            if (!$t) { echo '  ✗ ' . $s['title'] . ' — لا حساب للمعلّم ' . $s['teacher_id'] . "\n"; continue; }

            echo '  + ' . $s['title'] . "\n";
            echo '      المادّة ' . $s['subject'] . ' · الصفّ ' . $s['grade']
               . ' · المعلّم ' . $s['teacher'] . ' (#' . $s['teacher_id'] . ')'
               . ' · القسم «' . $s['section'] . '»' . "\n";
            echo '      المسار: ' . ($empty
                    ? 'يُربط بالقائم #' . (int) $empty['id'] . ' «' . $empty['title'] . '»'
                      . ' (حالته الآن ' . $empty['status'] . ' وسيصير مسودّة)'
                    : 'يُنشأ جديد') . "\n";

            if ($apply !== 'apply') continue;

            $actor = $this->curr->actor_as('teacher', (int) $s['teacher_id']);

            /* بلا صفّ ومادّة هنا: تمريرهما ينادي `sync()` فيُنشئ مسارًا
               ثانيًا قبل أن نربط القائم. فالربط أوّلًا ثمّ `sync()` يدويًّا. */
            $post = array(
                'title'             => $s['title'],
                'short_description' => $s['short'],
                'sub_category_id'   => (int) $s['category'],
                'status'            => 'draft',
                'level'             => 'beginner',
            );
            if (!$empty) {
                $post['tq_grade_id']   = (int) $s['grade_id'];
                $post['tq_subject_id'] = (int) $s['subject_id'];
            }

            $r = $this->curr->save_course($actor, 0, $post);
            if (empty($r['ok'])) {
                echo '      ✗ ' . (isset($r['message']) ? $r['message'] : 'رُفض') . "\n";
                continue;
            }
            $cid = (int) $r['id'];

            if ($empty) {
                /* عمودٌ واحد يصل المقرَّر بمساره، ثمّ `sync()` يملأ الباقي
                   بقواعده هو: المعلّم من `creator`، والفئة، والحالة —
                   ولا يدهس عنوان المسار المكتوب (TQ-LINK-STOMP). */
                $this->db->where('id', (int) $empty['id'])
                         ->update('paths', array('course_id' => $cid));
                $this->link_m->sync($cid, (int) $s['grade_id'], (int) $s['subject_id']);
            }

            $rs = $this->curr->save_section($actor, $cid, 0, array('title' => $s['section']));
            if (empty($rs['ok'])) {
                echo '      ✗ القسم: ' . (isset($rs['message']) ? $rs['message'] : 'رُفض') . "\n";
                continue;
            }
            $sid = (int) $this->db->select('id')->where('course_id', $cid)
                                  ->order_by('id', 'DESC')->limit(1)->get('section')->row('id');
            $pid = (int) $this->db->select('id')->where('course_id', $cid)
                                  ->limit(1)->get('paths')->row('id');

            $made++;
            echo '      ✓ المقرَّر #' . $cid . ' · القسم #' . $sid . ' · المسار #' . $pid . "\n";
        }

        echo str_repeat('─', 62) . "\n";
        if ($apply === 'apply') {
            echo '✓ أُنشئ ' . $made . " مقرَّرًا — كلّها مسودّات لا تُعرض ولا تُباع.\n";
        } else {
            echo "معاينة — أضف apply للتنفيذ.\n";
        }
    }

}
