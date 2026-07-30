<!-- CSS لإصلاح الهيدر والفوتر في صفحة المدونة -->
<style>
/* إصلاح البانر */
.blog-banner-section {
    position: relative;
    margin-top: 0 !important;
    z-index: 1;
}

.blog-banner-section .image-placeholder-2 {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(42, 18, 116, 0.9), rgba(0, 0, 0, 0.7));
    z-index: 1;
}

.blog-banner-section .container-lg {
    position: relative;
    z-index: 2;
}

/* تحسين عنوان البانر */
.blog-banner-section h1 {
    font-size: 2.5rem;
    font-weight: 700;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    margin-bottom: 15px;
}

.blog-banner-section .text-17px {
    font-size: 1.1rem;
    opacity: 0.95;
}

/* إصلاح التباعد بين البانر والمحتوى */
.blog-banner-section + section,
.blog-banner-section + .blog-body {
    margin-top: 0 !important;
}

/* تحسين للجوال */
@media (max-width: 768px) {
    .blog-banner-section h1 {
        font-size: 1.8rem;
    }
    
    .blog-banner-section .text-17px {
        font-size: 1rem;
    }
    
    .blog-banner-section .container-lg {
        padding: 30px 15px;
    }
}
</style>

<section class="blog-banner-section" style="background-image: url('<?php echo site_url('uploads/blog/page-banner/'.get_frontend_settings('blog_page_banner')); ?>'); background-size: cover; background-position: center; position: relative;">
    <div class="image-placeholder-2"></div>
    <div class="container-lg position-relative py-5">
        <div class="row my-0 my-md-4 justify-content-center">
            <div class="col-lg-7">
                <h1 class="display-4 fw-600 text-center text-white"><?php echo get_frontend_settings('blog_page_title'); ?></h1>
                <div class="text-17px text-center text-white"><?php echo get_frontend_settings('blog_page_subtitle'); ?></div>
            </div>
        </div>
    </div>
</section>

<?php include $included_page; ?>