<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * استيراد المنهج من ملف: المواد والصفوف والمسارات دفعة واحدة.
 *
 * الوحدة المستوردة **صف = مسار**، لأن المسار هو وحدة البيع؛ ومادته
 * وصفه يستنتجان منه وينشآن إن غابا. فملف من ثلاثة جداول يلزم المستورد
 * بترتيب الإدخال وربط المفاتيح بيده، وهو ما يخطئ فيه البشر أولا.
 *
 * والعمل على مرحلتين لا واحدة: **قراءة وتحقق ومعاينة** ثم **كتابة**.
 * فملف منهج فيه خطأ في الصف المئة يجب أن يعرف قبل أن يكتب تسعة وتسعون.
 *
 * والكتابة **رفع لا تكرار** (upsert): إعادة استيراد الملف نفسه بعد تصحيحه
 * تحدث ولا تنشئ نسخة ثانية — إذ الاستيراد يعاد في الواقع مرارا.
 */
class Taqdar_import_model extends CI_Model
{
    /** الأعمدة المقبولة، بالعربية والإنجليزية معا. */
    private $aliases = array(
        'subject'      => array('subject', 'المادة', 'المادة', 'subject_ar'),
        'subject_en'   => array('subject_en', 'المادة_en', 'subject_english'),
        'grade'        => array('grade', 'الصف', 'الصف', 'grade_ar'),
        'grade_en'     => array('grade_en', 'الصف_en', 'grade_english'),
        'title'        => array('title', 'path', 'المسار', 'عنوان_المسار', 'path_title'),
        'price'        => array('price', 'السعر', 'price_sar', 'سعر'),
        'teacher'      => array('teacher', 'المعلم', 'المعلم', 'teacher_email', 'بريد_المعلم'),
        'share'        => array('share', 'النسبة', 'نسبة_المعلم', 'share_percent'),
        'weeks'        => array('weeks', 'الاسابيع', 'الأسابيع', 'expected_weeks', 'مدة'),
        'course'       => array('course', 'الدورة', 'course_title', 'عنوان_الدورة'),
        'status'       => array('status', 'الحالة'),
    );

    /* =====================================================================
       القراءة
       ===================================================================== */

    /**
     * يقرأ الملف ويعيد صفوفا موحدة المفاتيح.
     * يقبل CSV (بفاصلة أو فاصلة منقوطة) وJSON.
     */
    public function parse($path, $ext)
    {
        $raw = @file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return array('ok' => false, 'errors' => array('الملف فارغ أو تعذرت قراءته.'));
        }

        // شارة BOM تلتصق بأول عمود فتفسد مطابقة اسمه
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);

        if (strtolower($ext) === 'json') {
            $rows = json_decode($raw, true);
            if (!is_array($rows)) {
                return array('ok' => false, 'errors' => array('ملف JSON غير صالح.'));
            }
        } else {
            $rows = $this->parse_csv($raw);
            if ($rows === false) {
                return array('ok' => false, 'errors' => array('تعذر فهم ترويسة الملف.'));
            }
        }

        $out = array();
        foreach ($rows as $r) {
            if (!is_array($r)) continue;
            $out[] = $this->normalise($r);
        }

        if (!$out) return array('ok' => false, 'errors' => array('لا صفوف في الملف.'));
        return array('ok' => true, 'rows' => $out);
    }

    private function parse_csv($raw)
    {
        $delim = (substr_count($raw, ';') > substr_count($raw, ',')) ? ';' : ',';

        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $head  = null; $rows = array();

        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            $cells = str_getcsv($line, $delim);
            if ($head === null) {
                $head = array_map(function ($h) { return trim(mb_strtolower($h)); }, $cells);
                continue;
            }
            $row = array();
            foreach ($head as $i => $h) {
                $row[$h] = isset($cells[$i]) ? trim($cells[$i]) : '';
            }
            $rows[] = $row;
        }
        return $head === null ? false : $rows;
    }

    /** يوحد أسماء الأعمدة مهما كتبها المستورد. */
    private function normalise($row)
    {
        $low = array();
        foreach ($row as $k => $v) {
            $low[trim(mb_strtolower((string) $k))] = is_scalar($v) ? trim((string) $v) : '';
        }

        $out = array();
        foreach ($this->aliases as $key => $names) {
            $out[$key] = '';
            foreach ($names as $n) {
                if (isset($low[mb_strtolower($n)]) && $low[mb_strtolower($n)] !== '') {
                    $out[$key] = $low[mb_strtolower($n)];
                    break;
                }
            }
        }
        return $out;
    }

    /* =====================================================================
       التحقق — قبل أي كتابة
       ===================================================================== */

    /**
     * يفحص كل صف ويعيده موسوما بحاله وبما سيقع له.
     * لا يكتب شيئا: هذه هي المعاينة التي تعرض قبل التأكيد.
     */
    public function validate_rows($rows)
    {
        $seen_titles = array();
        $out = array();

        foreach ($rows as $i => $r) {
            $errs = array(); $warns = array();
            $line = $i + 2;   // الترويسة سطر، والعد من واحد

            if ($r['subject'] === '') $errs[] = 'المادة مطلوبة';
            if ($r['grade']   === '') $errs[] = 'الصف مطلوب';
            if ($r['title']   === '') $errs[] = 'عنوان المسار مطلوب';

            // السعر يدخل بالريال ويخزن هللات — التحويل مرة واحدة هنا
            $price_halalas = 0;
            if ($r['price'] !== '') {
                $p = str_replace(array(',', ' '), '', $r['price']);
                if (!is_numeric($p) || (float) $p < 0) {
                    $errs[] = 'السعر ليس رقما';
                } else {
                    $price_halalas = (int) round(((float) $p) * 100);
                }
            }

            $share = null;
            if ($r['share'] !== '') {
                $sv = str_replace('%', '', $r['share']);
                if (!is_numeric($sv) || (float) $sv < 0 || (float) $sv > 100) {
                    $errs[] = 'النسبة خارج المدى 0–100';
                } else {
                    $share = (float) $sv;
                }
            }

            $weeks = 0;
            if ($r['weeks'] !== '') {
                if (!ctype_digit((string) $r['weeks'])) $errs[] = 'المدة ليست عددا صحيحا';
                else $weeks = (int) $r['weeks'];
            }

            // المعلم: يطابق بالبريد ولا ينشأ — إنشاء حساب من ملف استيراد
            // يفتح بابا لحسابات لا يعرفها أحد
            $teacher_id = 0;
            if ($r['teacher'] !== '') {
                $u = $this->db->select('id, is_instructor')
                              ->where('email', $r['teacher'])->get('users')->row_array();
                if (!$u)                            $errs[]  = 'لا حساب بهذا البريد';
                elseif ((int) $u['is_instructor'] !== 1) $warns[] = 'الحساب ليس معلما';
                if ($u) $teacher_id = (int) $u['id'];
            } else {
                $warns[] = 'بلا معلم';
            }

            // الدورة: تطابق بالعنوان ولا تنشأ — المحتوى يرفع من بوابة المعلم
            $course_id = 0;
            if ($r['course'] !== '') {
                $c = $this->db->select('id')->where('title', $r['course'])->get('course')->row_array();
                if (!$c) $warns[] = 'لا دورة بهذا العنوان';
                else     $course_id = (int) $c['id'];
            }

            // الحالة تكتب بالعربية في الملف النموذجي، فتقبل باللغتين.
            // وما لا يفهم يصير مسودة **مع تنبيه** لا صمتا.
            $raw_status = mb_strtolower(trim($r['status']));
            $map = array(
                'published' => 'published', 'منشور' => 'published', 'نشر' => 'published',
                'draft' => 'draft', 'مسودة' => 'draft', 'مسودة' => 'draft',
            );
            if ($raw_status === '') {
                $status = 'draft';
            } elseif (isset($map[$raw_status])) {
                $status = $map[$raw_status];
            } else {
                $status  = 'draft';
                $warns[] = 'حالة غير مفهومة — اعتبرت مسودة';
            }

            // مسار بلا دورة أو بلا سعر لا يباع — يستورد مسودة لا منشورا،
            // فنشره يعد بما لا يقع
            if ($status === 'published' && ($course_id === 0 || $price_halalas === 0)) {
                $status  = 'draft';
                $warns[] = 'نزل إلى مسودة (بلا دورة أو بلا سعر)';
            }

            $key = mb_strtolower($r['title']);
            if (isset($seen_titles[$key])) $errs[] = 'عنوان مكرر في الملف (سطر ' . $seen_titles[$key] . ')';
            $seen_titles[$key] = $line;

            $exists = $r['title'] !== '' ? $this->db->select('id')->where('title', $r['title'])
                                                    ->get('paths')->row_array() : null;

            $out[] = array(
                'line'       => $line,
                'subject'    => $r['subject'],  'subject_en' => $r['subject_en'],
                'grade'      => $r['grade'],    'grade_en'   => $r['grade_en'],
                'title'      => $r['title'],
                'price'      => $price_halalas,
                'share'      => $share,
                'weeks'      => $weeks,
                'teacher_id' => $teacher_id,    'teacher' => $r['teacher'],
                'course_id'  => $course_id,     'course'  => $r['course'],
                'status'     => $status,
                'action'     => $exists ? 'update' : 'create',
                'path_id'    => $exists ? (int) $exists['id'] : 0,
                'errors'     => $errs,
                'warnings'   => $warns,
            );
        }
        return $out;
    }

    /* =====================================================================
       الكتابة
       ===================================================================== */

    /**
     * يكتب الصفوف السليمة وحدها. الصفوف المعطوبة تترك ولا توقف غيرها —
     * فملف من مئة صف فيه خطأ واحد يجب أن يدخل تسعة وتسعين.
     */
    public function commit($validated)
    {
        $stats = array('subjects' => 0, 'grades' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0);

        foreach ($validated as $r) {
            if (!empty($r['errors'])) { $stats['skipped']++; continue; }

            $subject_id = $this->upsert_lookup('subjects', $r['subject'], $r['subject_en'], $stats, 'subjects');
            $grade_id   = $this->upsert_lookup('grades',   $r['grade'],   $r['grade_en'],   $stats, 'grades');

            $data = array(
                'subject_id'     => $subject_id,
                'grade_id'       => $grade_id,
                'teacher_id'     => $r['teacher_id'],
                'title'          => $r['title'],
                'price'          => $r['price'],
                'status'         => $r['status'],
                'expected_weeks' => $r['weeks'],
                'course_id'      => $r['course_id'],
            );
            if ($r['share'] !== null) $data['teacher_share_percent'] = $r['share'];

            if ($r['path_id'] > 0) {
                $this->db->where('id', $r['path_id'])->update('paths', $data);
                $stats['updated']++;
            } else {
                $this->db->insert('paths', $data);
                $stats['created']++;
            }
        }

        $this->load->model('taqdar_admin_model');
        $this->taqdar_admin_model->audit('curriculum_import', 'paths', null, $stats);

        return $stats;
    }

    /** يجد المادة/الصف بالاسم أو ينشئه. المطابقة بالاسم العربي وهو المفتاح الفعلي. */
    private function upsert_lookup($table, $name_ar, $name_en, &$stats, $stat_key)
    {
        if ($name_ar === '') return 0;

        $row = $this->db->select('id')->where('name_ar', $name_ar)->get($table)->row_array();
        if ($row) return (int) $row['id'];

        $this->db->insert($table, array(
            'name_ar' => $name_ar,
            'name_en' => $name_en !== '' ? $name_en : null,
            'order'   => (int) $this->db->count_all($table) + 1,
            'active'  => 1,
        ));
        $stats[$stat_key]++;
        return (int) $this->db->insert_id();
    }
}
