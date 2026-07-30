<!-- CSS لإصلاح مشاكل الصفحة وإزالة تحديد النصوص -->
<style>
/* إصلاح الهيدر والفوتر */
.blog-main-section.courses.blog {
    padding-top: 40px !important;
    padding-bottom: 60px !important;
    margin-bottom: 0 !important;
    background-color: var(--bg-white);
}

/* إزالة جميع قيود النص في بطاقات المدونة */
.blog-main-section .courses-text p {
    max-height: none !important;
    overflow: visible !important;
    white-space: normal !important;
    text-overflow: unset !important;
    -webkit-line-clamp: unset !important;
    display: block !important;
    line-height: 1.7 !important;
    color: #68768b !important;
    font-size: 15px !important;
    margin-bottom: 15px !important;
}

/* إزالة قيود النص من العناوين */
.blog-main-section .courses-text h5 {
    max-height: none !important;
    overflow: visible !important;
    white-space: normal !important;
    text-overflow: unset !important;
    -webkit-line-clamp: unset !important;
    display: block !important;
    line-height: 1.5 !important;
}

/* تحسين بطاقات المدونة */
.blog-main-section .blogCard {
    display: block !important;
    height: auto !important;
    min-height: auto !important;
    transition: all 0.3s ease;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    margin-bottom: 25px;
}

.blog-main-section .blogCard:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
}

.blog-main-section .courses-card-image img {
    width: 100%;
    height: 200px;
    object-fit: cover;
}

.blog-main-section .courses-text {
    padding: 20px !important;
}

.blog-main-section .courses-price-border {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

/* تحسين للجوال */
@media (max-width: 768px) {
    .blog-main-section.courses.blog {
        padding-top: 20px !important;
        padding-bottom: 40px !important;
    }
    
    .blog-main-section .blogCard {
        margin-bottom: 20px;
    }
    
    .blog-main-section .courses-text {
        padding: 15px !important;
    }
}
</style>

<!--------- Blog section start ---------->
<section class="blog-main-section courses blog pb-3 mb-5 pt-4">
    <div class="container">
        <h1 class="text-center mb-2"><span><?php echo get_phrase('Latest from our blog'); ?></span></h1>
        <p class="text-center mb-5"><?php echo get_phrase('Exploring the Cutting-Edge Insights and Updates on Our Blog') ?></p>
        <div class="courses-card">
            <div class="row justify-content-center">
                <?php foreach($latest_blogs->result_array() as $latest_blog): ?>
                    <?php $user_details = $this->user_model->get_all_user($latest_blog['user_id'])->row_array(); ?>
                    <div class="col-lg-4 col-md-6 ">
                        <a href="<?php echo site_url('blog/details/'.slugify($latest_blog['title']).'/'.$latest_blog['blog_id']); ?>" class="courses-card-body blogCard">
                            <div class="courses-card-image">
                                <div class="courses-card-image">
                                    <?php $blog_thumbnail = 'uploads/blog/thumbnail/'.$latest_blog['thumbnail']; ?>
                                    <?php if(file_exists($blog_thumbnail) && is_file($blog_thumbnail)): ?>
                                        <img loading="lazy" src="<?php echo base_url($blog_thumbnail); ?>" alt="<?php echo $latest_blog['title']; ?>">
                                    <?php else: ?>
                                        <img loading="lazy" src="<?php echo base_url('uploads/blog/thumbnail/placeholder.png'); ?>" alt="<?php echo $latest_blog['title']; ?>">
                                    <?php endif; ?>
                                </div>
                                <div class="courses-card-image-text position-absolute">
                                    <h3><?php echo $this->crud_model->get_blog_categories($latest_blog['blog_category_id'])->row('title'); ?></h3>
                                </div> 
                            </div>
                            <div class="courses-text">
                                <h5><?php echo $latest_blog['title']; ?></h5>
                                <p><?php echo strip_tags(htmlspecialchars_decode_($latest_blog['description'])); ?></p>
                                    <div class="courses-price-border">
                                        <div class="courses-price">
                                            <div class="courses-price-left">
                                                <img loading="lazy" class="rounded-circle" src="<?php echo $this->user_model->get_user_image_url($user_details['id']); ?>">
                                                <h5><?php echo $user_details['first_name'].' '.$user_details['last_name']; ?></h5>
                                            </div>
                                            <div class="courses-price-right ">
                                                <p><?php echo get_past_time($latest_blog['added_date']); ?></p>
                                            </div>
                                        </div>
                                    </div>
                               </div>
                         </a>
                    </div>
                <?php endforeach; ?>
                <div class="col-12 text-center">
                    <a href="<?php echo site_url('blogs'); ?>" class="btn btn-primary px-5"><?php echo get_phrase('See all'); ?></a>
                </div>
            </div>
        </div>
    </div>

    <?php if($popular_blogs->num_rows() > 0): ?>
        <div class="container mb-4 mt-4">
            <h1 class="text-center mb-2"><span><?php echo get_phrase('Popular blogs'); ?></span></h1>
            <p class="text-center mb-5"><?php echo get_phrase('Learn from Expert Bloggers and Expand Your Knowledge') ?></p>
            <div class="courses-card">
                <div class="row justify-content-center">
                    <?php foreach($popular_blogs->result_array() as $popular_blog): ?>
                        <?php $user_details = $this->user_model->get_all_user($popular_blog['user_id'])->row_array(); ?>
                        <div class="col-lg-4 col-md-6 ">
                            <a href="<?php echo site_url('blog/details/'.slugify($popular_blog['title']).'/'.$popular_blog['blog_id']); ?>" class="courses-card-body blogCard">
                                <div class="courses-card-image">
                                    <div class="courses-card-image">
                                        <?php $blog_thumbnail = 'uploads/blog/thumbnail/'.$popular_blog['thumbnail']; ?>
                                        <?php if(file_exists($blog_thumbnail) && is_file($blog_thumbnail)): ?>
                                            <img loading="lazy" src="<?php echo base_url($blog_thumbnail); ?>" alt="<?php echo $popular_blog['title']; ?>">
                                        <?php else: ?>
                                            <img loading="lazy" src="<?php echo base_url('uploads/blog/thumbnail/placeholder.png'); ?>" alt="<?php echo $popular_blog['title']; ?>">
                                        <?php endif; ?>
                                    </div>
                                    <div class="courses-card-image-text position-absolute">
                                        <h3><?php echo $this->crud_model->get_blog_categories($popular_blog['blog_category_id'])->row('title'); ?></h3>
                                    </div> 
                                </div>
                                <div class="courses-text">
                                    <h5><?php echo $popular_blog['title']; ?></h5>
                                    <p><?php echo strip_tags(htmlspecialchars_decode_($popular_blog['description'])); ?></p>
                                        <div class="courses-price-border">
                                            <div class="courses-price">
                                                <div class="courses-price-left">
                                                    <img loading="lazy" class="rounded-circle" src="<?php echo $this->user_model->get_user_image_url($user_details['id']); ?>">
                                                    <h5><?php echo $user_details['first_name'].' '.$user_details['last_name']; ?></h5>
                                                </div>
                                                <div class="courses-price-right ">
                                                    <p><?php echo get_past_time($popular_blog['added_date']); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                   </div>
                             </a>
                        </div>
                    <?php endforeach; ?>
                    <div class="col-12 text-center">
                        <a href="<?php echo site_url('blogs'); ?>" class="btn btn-primary px-5"><?php echo get_phrase('See all'); ?></a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>
<!--------- Blog section end ---------->