<?php
/**
 * TQ-I18N — قاموس english · components
 *
 * المفتاح هو النص العربي كما كتب في الشيفرة، والقيمة ترجمته.
 * وقيمة فارغة تعني «لم يترجم بعد»: `tq_catalog()` تسقطها، فيرد
 * `t()` العربية كما هي — شاشة بلا ترجمة تعرض ما كانت تعرضه، ولا
 * تعرض مفتاحا عاريا ولا فراغا.
 *
 * يولد بـ`php scripts/i18n_skeleton.php --apply`، وهو يحفظ ما ترجم
 * ويضيف ما جد. فلا تحرر ترتيبه بيد — حرر القيم وحدها.
 */

return array(
    '(اختياري)' => '(optional)',
    'آخر نتيجة في كل درس. واختبار الدرس هو ما يفتح الدرس الذي بعده، فالدرس الذي لم يجتز يقف عنده الطريق.' => 'The latest result for each lesson. A lesson\'s quiz is what opens the lesson after it, so a lesson you have not passed is where the road stops.',
    'آخر نتيجة في كل درس. وباجتياز اختبار الدرس يفتح الدرس الذي بعده.' => 'The latest result for each lesson. Passing a lesson\'s quiz opens the lesson after it.',
    'آخر نتيجة لكل طالب في كل درس. وعدد المحاولات يقرأ عن الشرح: من اجتاز من الرابعة لم يفهم من الأولى.' => 'The latest result for each student in each lesson. The number of attempts tells you about the explanation: a student who passed on the fourth try did not understand on the first.',
    'أسئلة هذا الاختبار (' => 'Questions in this quiz (',
    'أضف السؤال' => 'Add question',
    'أضف سؤالا' => 'Add a question',
    'أعد المحاولة — لا حد لعددها' => 'Try again — there is no limit',
    'اجتاز' => 'Passed',
    'اجتاز بعد محاولات — يستحق إعادة شرح' => 'Passed after several attempts — worth re-explaining',
    'اجتزته بعد محاولات — راجعه ثانية' => 'You passed it after several attempts — review it again',
    'احذف الصورة الحالية' => 'Delete the current image',
    'احذف هذا السؤال' => 'Delete this question',
    'احفظ التعديل' => 'Save changes',
    'الخيار ____ هو الصحيح' => 'Option ____ is the correct one',
    'الخيار ________' => 'Option ________',
    'الخيارات والإجابة الصحيحة' => 'Options and the correct answer',
    'الدرس التالي مقفل' => 'The next lesson is locked',
    'المحاولات' => 'Attempts',
    'الهدف الذي يقيسه' => 'The objective it measures',
    'بالهدف يعرف النظام أي مفهوم تعثر فيه الطالب، فيعيده إلى دقيقته في الشرح ويكتب الخطأ في دفتره وخريطة إتقانه. وسؤال بلا هدف يصحح ولا يعلم شيئا بعد ذلك.' => 'The objective is how the system knows which concept a student stumbled on, so it can send them back to that minute of the explanation and record the mistake in their notebook and mastery map. A question with no objective is marked and teaches nothing after that.',
    'بلا هدف — لا يدخل خريطة الإتقان ولا دفتر الأخطاء.' => 'No objective — it enters neither the mastery map nor the mistake notebook.',
    'تحرير هذا السؤال' => 'Edit this question',
    'خياران على الأقل وستة على الأكثر. علم الدائرة أمام الصحيح، والفارغ يهمل.' => 'Two options at least and six at most. Mark the radio next to the correct one; empty options are ignored.',
    'صورة السؤال' => 'Question image',
    'لا نتائج بعد.' => 'No results yet.',
    'لغة الواجهة' => 'Interface language',
    'للمعادلات والرسوم البيانية ولقطات الشاشة — تعرض تحت نص السؤال. jpg · png · gif · webp، وحتى' => 'For equations, charts and screenshots — shown beneath the question text. jpg · png · gif · webp, up to',
    'لم يجتز بعد' => 'Not passed yet',
    'ميجابايت.' => 'MB.',
    'نتائج اختبارات الدروس' => 'Lesson quiz results',
    'نص السؤال' => 'Question text',
    'يقيس:' => 'Measures:',
    '— الصحيح' => '— correct',
    '— بلا هدف' => '— no objective',
);
