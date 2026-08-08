<?php
/**
 * قائمة المفضلة المصغرة — جزء يطلبه `Home::toggleWishlistItems()`.
 *
 * ثيم تقدر لا يحمل قائمة مفضلة منسدلة في ترويسته (لا عنصر `#wishlistItems`
 * ولا `#wishlistItemsCounter`)، فالجزء الذي يبنى هنا لا يستهلكه شيء في
 * الواجهة. ومع ذلك **يجب أن يوجد الملف**: النقطة تحمله بـ`$this->load->view()`
 * بلا فحص، فغيابه يوقف الطلب كله بـ«Unable to load the requested file:
 * frontend/taqdar/wishlist_items.php» — صفحة خطأ بيضاء بدل رد JSON.
 *
 * فيبقى مختصرا وصحيحا: عناصر المفضلة بأسمائها وروابطها، بلا ترميز Bootstrap
 * ولا أيقونات FontAwesome التي يستعملها قالب Academy الأصلي ولا وجود لها هنا.
 *
 * وزر المفضلة في صفحة الكورس لم يعد يمر بهذه النقطة أصلا: صار نموذج POST
 * إلى `student/favourite`، وهو المسار نفسه الذي تستعمله شاشات البوابة.
 */
defined('BASEPATH') or exit('No direct script access allowed');

$my_wishlist_items = isset($my_wishlist_items) && is_array($my_wishlist_items) ? $my_wishlist_items : array();
?>
<?php if (!$my_wishlist_items): ?>
    <p class="tq-caption" style="margin:0">لا كورسات في مفضلتك بعد.</p>
<?php else: ?>
    <ul class="tq-stack">
        <?php foreach ($my_wishlist_items as $tqw_id): ?>
            <?php
            $tqw = $this->crud_model->get_course_by_id((int) $tqw_id)->row_array();
            if (!$tqw) continue;   // كورس حذف ومعرفه باق في القائمة
            ?>
            <li>
                <a href="<?php echo site_url('home/course/' . rawurlencode(slugify($tqw['title'])) . '/' . (int) $tqw['id']); ?>">
                    <?php echo html_escape($tqw['title']); ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
