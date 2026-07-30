<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * SEO controller — serves crawler-friendly HTML for Googlebot/Bingbot/etc.
 *
 * Real users hit the React SPA at root URLs. The .htaccess detects bot
 * User-Agents and INTERNALLY rewrites the request to the matching method
 * here. The bot still sees the original root URL in its address bar; the
 * server simply renders this HTML for it.
 *
 * Every method:
 *   1. Queries the same Api_frontend_model the React API uses (single source of truth)
 *   2. Builds a $seo array with title, description, canonical, og:*, JSON-LD
 *   3. Renders application/views/seo/layout.php which embeds the page partial
 *   4. Sets Cache-Control: public, max-age=600 — cuts load 99% on repeat crawls
 *
 * Canonical URLs ALWAYS point to the root domain (https://my-communication.uk/...)
 * never to /api/seo/* — so Search Console indexes clean URLs.
 */
class Seo extends CI_Controller
{
    /** Root URL of the public site — used for canonical + sitemap + og:url */
    private $root;

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->helper(['url', 'text']);
        $this->load->model('api_frontend_model');
        $this->load->model('crud_model');
        $this->root = rtrim(get_settings('system_url') ?: 'https://my-communication.uk', '/');
    }

    // ───────────────────────── HOME ──────────────────────────
    public function home()
    {
        $top_courses = $this->api_frontend_model->get_top_courses(8) ?: [];
        $categories  = $this->api_frontend_model->get_categories() ?: [];

        // Graph: EducationalOrganization + WebSite (with Sitelinks SearchAction)
        $graph = [
            [
                '@context' => 'https://schema.org',
                '@type'    => 'EducationalOrganization',
                'name'     => 'My-Communication Academy',
                'url'      => $this->root,
                'logo'     => $this->root . '/favicon.ico',
                'description' => 'Online courses in telecom, IT, networking, and business.',
                'sameAs'   => array_values(array_filter([
                    get_settings('facebook') ?: 'https://www.facebook.com/myca.international',
                    get_settings('twitter')  ?: 'https://x.com/myca_intl',
                    get_settings('linkedin') ?: 'https://www.linkedin.com/company/mycommunicationacademy',
                    get_settings('youtube')  ?: 'https://www.youtube.com/@mycommunicationacademy',
                    get_settings('instagram') ?: 'https://www.instagram.com/mycommunication2021',
                ])),
            ],
            [
                '@context' => 'https://schema.org',
                '@type'    => 'WebSite',
                'name'     => 'My-Communication Academy',
                'url'      => $this->root,
                'potentialAction' => [
                    '@type'       => 'SearchAction',
                    'target'      => ['@type' => 'EntryPoint', 'urlTemplate' => $this->root . '/courses?search={search_term_string}'],
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ];

        $seo = [
            'title'       => 'My-Communication Academy — Telecom, IT & Networking Courses',
            'description' => 'Master telecom, networking, IT and business skills with industry-expert instructors. Flexible online learning with globally recognized certifications.',
            'canonical'   => $this->root . '/',
            'og_image'    => $this->root . '/og-image.jpg',
            'jsonld'      => json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];

        $seo = $this->_admin_seo('home', $seo);
        $this->_render('home', $seo, [
            'top_courses' => $top_courses,
            'categories'  => $categories,
        ]);
    }

    // ───────────────────────── COURSES LISTING ───────────────
    public function courses()
    {
        $result = $this->api_frontend_model->get_courses(['limit' => 60]) ?: [];
        // get_courses() returns ['courses' => [...], 'pagination' => ...]; tolerate other shapes too.
        $courses = $result['courses'] ?? $result['data'] ?? (isset($result[0]) ? $result : []);

        $seo = [
            'title'       => 'All Courses — My-Communication Academy',
            'description' => 'Browse our full catalog of telecom, networking, and IT engineering courses. Filter by category, level, or price.',
            'canonical'   => $this->root . '/courses',
            'og_image'    => $this->root . '/og-image.jpg',
            'jsonld'      => json_encode([
                '@context' => 'https://schema.org',
                '@type'    => 'CollectionPage',
                'name'     => 'All Courses',
                'url'      => $this->root . '/courses',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];

        $seo = $this->_admin_seo('courses', $seo);
        $this->_render('courses', $seo, ['courses' => $courses]);
    }

    // ───────────────────────── COURSE DETAIL ─────────────────
    public function course_detail($id)
    {
        $id = (int) $id;
        $course = $this->api_frontend_model->get_course_details($id);
        if (!$course) { return $this->_404(); }

        $title = (is_array($course) ? ($course['title'] ?? 'Course') : ($course->title ?? 'Course'));
        // SEO-friendly canonical: /courses/<id>-<slug> (matches the SPA links).
        $courseUrl = $this->root . '/courses/' . $id . '-' . slugify($title);
        // Course thumbnail for og:image (routed through img.php in _render if WebP).
        $lastmod   = is_array($course) ? ($course['last_modified'] ?? '') : ($course->last_modified ?? '');
        $thumbRel  = $this->_course_thumb_rel($id, $lastmod);
        $courseImg = $thumbRel ? ($this->root . '/' . $thumbRel) : ($this->root . '/og-image.jpg');
        $short = (string)(is_array($course) ? ($course['short_description'] ?? '') : ($course->short_description ?? ''));
        $long  = (string)(is_array($course) ? ($course['description'] ?? '') : ($course->description ?? ''));
        // Prefer the short description; fall back to the full description so each page has a unique meta description
        $desc  = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($short ?: $long, ENT_QUOTES, 'UTF-8'))));
        $price = is_array($course) ? ($course['price'] ?? 0) : ($course->price ?? 0);
        $rating = is_array($course) ? ($course['rating'] ?? null) : ($course->rating ?? null);
        $ratingCount = is_array($course) ? ($course['total_ratings'] ?? 0) : ($course->total_ratings ?? 0);

        $courseLd = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Course',
            'name'        => $title,
            'description' => character_limiter($desc ?: 'Course at My-Communication Academy', 300),
            'provider'    => [
                '@type' => 'EducationalOrganization',
                'name'  => 'My-Communication Academy',
                'url'   => $this->root,
            ],
            'offers' => [
                '@type' => 'Offer',
                'price' => $price,
                'priceCurrency' => 'GBP',
                'url'   => $courseUrl,
            ],
        ];
        if ($rating && (float)$rating > 0 && (int)$ratingCount > 0) {
            $courseLd['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => (string) $rating,
                'ratingCount' => (int) $ratingCount,
            ];
        }

        $seo = [
            'title'       => $title . ' — My-Communication Academy',
            'description' => character_limiter($desc ?: 'Learn ' . $title, 160),
            'canonical'   => $courseUrl,
            'og_image'    => $courseImg,
            'jsonld'      => json_encode([
                $courseLd,
                $this->breadcrumb([
                    ['Home', $this->root . '/'],
                    ['Courses', $this->root . '/courses'],
                    [$title, $courseUrl],
                ]),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];

        $this->_render('course_detail', $seo, ['course' => $course]);
    }

    // ───────────────────────── ABOUT US ──────────────────────
    public function about_us()
    {
        $seo = [
            'title'       => 'About Us — My-Communication Academy',
            'description' => 'Learn about My-Communication Academy: our mission, our instructors, and our commitment to telecom education.',
            'canonical'   => $this->root . '/about-us',
            'og_image'    => $this->root . '/og-image.jpg',
            'jsonld'      => json_encode([
                '@context' => 'https://schema.org',
                '@type'    => 'AboutPage',
                'url'      => $this->root . '/about-us',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
        $seo = $this->_admin_seo('about_us', $seo);
        $this->_render('about_us', $seo, []);
    }

    // ───────────────────────── BECOME INSTRUCTOR ─────────────
    public function become_instructor()
    {
        $seo = [
            'title'       => 'Become an Instructor — My-Communication Academy',
            'description' => 'Share your expertise with thousands of learners. Apply to become an instructor at My-Communication Academy.',
            'canonical'   => $this->root . '/become-instructor',
            'og_image'    => $this->root . '/og-image.jpg',
        ];
        $this->_render('become_instructor', $seo, []);
    }

    // ───────────────────────── CONTACT US ────────────────────
    public function contact_us()
    {
        $seo = [
            'title'       => 'Contact Us — My-Communication Academy',
            'description' => 'Get in touch with the My-Communication Academy team — phone, email, and London office address.',
            'canonical'   => $this->root . '/contact-us',
            'og_image'    => $this->root . '/og-image.jpg',
            'jsonld'      => json_encode([
                '@context' => 'https://schema.org',
                '@type'    => 'ContactPage',
                'url'      => $this->root . '/contact-us',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
        $seo = $this->_admin_seo('contact_us', $seo);
        $this->_render('contact_us', $seo, []);
    }

    // ───────────────────────── BLOG INDEX ────────────────────
    public function blog()
    {
        $result = $this->api_frontend_model->get_blogs(['limit' => 30]) ?: [];
        // get_blogs() returns ['blogs' => [...], 'pagination' => ...]; tolerate other shapes too.
        $blogs = $result['blogs'] ?? $result['data'] ?? (isset($result[0]) ? $result : []);

        $seo = [
            'title'       => 'Blog — My-Communication Academy',
            'description' => 'Articles, tutorials, and insights on telecom, networking, and IT engineering from our expert instructors.',
            'canonical'   => $this->root . '/blog',
            'og_image'    => $this->root . '/og-image.jpg',
            'jsonld'      => json_encode([
                '@context' => 'https://schema.org',
                '@type'    => 'Blog',
                'url'      => $this->root . '/blog',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
        $seo = $this->_admin_seo('blog', $seo);
        $this->_render('blog', $seo, ['blogs' => $blogs]);
    }

    // ───────────────────────── BLOG DETAIL ───────────────────
    // Accepts the numeric blog id (React route is /blogs/:id). Canonical uses /blogs/<id>.
    public function blog_detail($id)
    {
        $id = (int) $id;
        $blog = $this->api_frontend_model->get_blog_details($id);
        if (!$blog) { return $this->_404(); }

        $g = function ($k, $d = '') use ($blog) { return is_array($blog) ? ($blog[$k] ?? $d) : ($blog->{$k} ?? $d); };
        $title = $g('title', 'Blog');
        // SEO-friendly canonical: /blogs/<id>-<slug> (matches the SPA links).
        $blogUrl = $this->root . '/blogs/' . $id . '-' . slugify($title);
        $rawExcerpt = $g('excerpt', '') ?: $g('short_description', '') ?: $g('description', '') ?: $g('content', '');
        $desc  = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode((string) $rawExcerpt, ENT_QUOTES, 'UTF-8'))));
        $img   = $this->_abs_img($g('banner', '') ?: $g('thumbnail', ''));
        $author = $g('author', []);
        $authorName = is_array($author) ? ($author['name'] ?? '') : '';
        $created = $g('created_at', '');

        $seo = [
            'title'       => $title . ' — My-Communication Academy',
            'description' => character_limiter($desc ?: 'Read this article on My-Communication Academy', 160),
            'canonical'   => $blogUrl,
            'og_image'    => $img,
            'jsonld'      => json_encode([
                array_filter([
                    '@context' => 'https://schema.org',
                    '@type'    => 'BlogPosting',
                    'headline' => $title,
                    'description' => character_limiter($desc, 300),
                    'image'    => $img,
                    'datePublished' => $created ?: null,
                    'author'   => $authorName ? ['@type' => 'Person', 'name' => $authorName] : null,
                    'publisher' => [
                        '@type' => 'Organization',
                        'name'  => 'My-Communication Academy',
                        'url'   => $this->root,
                    ],
                    'mainEntityOfPage' => $blogUrl,
                    'url'      => $blogUrl,
                ]),
                $this->breadcrumb([
                    ['Home', $this->root . '/'],
                    ['Blog', $this->root . '/blogs'],
                    [$title, $blogUrl],
                ]),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
        $this->_render('blog_detail', $seo, ['blog' => $blog]);
    }

    // ───────────────────────── INSTRUCTORS LISTING ───────────
    public function instructors()
    {
        $result = $this->api_frontend_model->get_instructors(['per_page' => 60]) ?: [];
        $instructors = (is_array($result) && isset($result['instructors'])) ? $result['instructors']
                     : ((is_array($result) && isset($result['data'])) ? $result['data'] : $result);

        $seo = [
            'title'       => 'Our Instructors — My-Communication Academy',
            'description' => 'Meet the industry-expert instructors behind My-Communication Academy — telecom, networking, IT and business professionals.',
            'canonical'   => $this->root . '/instructors',
            'og_image'    => $this->root . '/og-image.jpg',
            'jsonld'      => json_encode([
                '@context' => 'https://schema.org',
                '@type'    => 'CollectionPage',
                'name'     => 'Our Instructors',
                'url'      => $this->root . '/instructors',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
        $this->_render('instructors', $seo, ['instructors' => $instructors]);
    }

    // ───────────────────────── INSTRUCTOR DETAIL ─────────────
    public function instructor_detail($id)
    {
        $id = (int) $id;
        $instructor = $this->api_frontend_model->get_instructor_details($id);
        if (!$instructor) { return $this->_404(); }

        $g = function ($k, $d = '') use ($instructor) { return is_array($instructor) ? ($instructor[$k] ?? $d) : ($instructor->{$k} ?? $d); };
        $name = $g('name', 'Instructor');
        $bio  = trim(preg_replace('/\s+/', ' ', strip_tags((string) $g('biography', ''))));
        $img  = $this->_abs_img($g('image', ''));

        $seo = [
            'title'       => $name . ' — Instructor at My-Communication Academy',
            'description' => character_limiter($bio ?: ($name . ' teaches at My-Communication Academy.'), 160),
            'canonical'   => $this->root . '/instructors/' . $id,
            'og_image'    => $img,
            'jsonld'      => json_encode([
                array_filter([
                    '@context' => 'https://schema.org',
                    '@type'    => 'Person',
                    'name'     => $name,
                    'description' => character_limiter($bio, 300) ?: null,
                    'image'    => $img,
                    'jobTitle' => 'Instructor',
                    'worksFor' => [
                        '@type' => 'Organization',
                        'name'  => 'My-Communication Academy',
                        'url'   => $this->root,
                    ],
                    'url'      => $this->root . '/instructors/' . $id,
                ]),
                $this->breadcrumb([
                    ['Home', $this->root . '/'],
                    ['Instructors', $this->root . '/instructors'],
                    [$name, $this->root . '/instructors/' . $id],
                ]),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
        $this->_render('instructor_detail', $seo, ['instructor' => $instructor]);
    }

    // ───────────────────────── FAQ ───────────────────────────
    public function faq()
    {
        $this->load->helper('faq');
        $items = mycom_faq_items();

        $faqLd = [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => array_map(function ($f) {
                return [
                    '@type'          => 'Question',
                    'name'           => $f['q'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
                ];
            }, $items),
        ];

        $seo = [
            'title'       => 'FAQ — My-Communication Academy',
            'description' => 'Answers to common questions about My-Communication Academy: online Telecom, 5G, networking and IT courses, enrolment, certificates, access, refunds, and support.',
            'canonical'   => $this->root . '/faq',
            'og_image'    => $this->root . '/og-image.jpg',
            'jsonld'      => json_encode($faqLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
        $seo = $this->_admin_seo('faq', $seo);
        $this->_render('faq', $seo, ['items' => $items]);
    }

    // ───────────────────────── STATIC PAGES (privacy/terms/refund) ─────────────
    public function page($slug = '')
    {
        $slug = preg_replace('/[^a-z0-9_\-]/', '', (string) $slug);
        $page = $this->api_frontend_model->get_page($slug);
        if (!$page) { return $this->_404(); }

        $g = function ($k, $d = '') use ($page) { return is_array($page) ? ($page[$k] ?? $d) : ($page->{$k} ?? $d); };
        $title = $g('title', ucwords(str_replace(['-', '_'], ' ', $slug)));
        $desc  = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode((string) $g('content', ''), ENT_QUOTES, 'UTF-8'))));

        $seo = [
            'title'       => $title . ' — My-Communication Academy',
            'description' => character_limiter($desc ?: $title . ' at My-Communication Academy', 160),
            'canonical'   => $this->root . '/' . $slug,
            'og_image'    => $this->root . '/og-image.jpg',
            'jsonld'      => json_encode([
                '@context' => 'https://schema.org',
                '@type'    => 'WebPage',
                'name'     => $title,
                'url'      => $this->root . '/' . $slug,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
        // Map the public slug to the admin seo_fields route key, then overlay.
        $routeMap = [
            'privacy-policy'       => 'privacy_policy',
            'terms'                => 'terms_and_condition',
            'terms-and-conditions' => 'terms_and_condition',
            'refund-policy'        => 'refund_policy',
            'faq'                  => 'faq',
        ];
        if (isset($routeMap[$slug])) $seo = $this->_admin_seo($routeMap[$slug], $seo);

        $this->_render('page', $seo, ['page' => $page]);
    }

    // Public 404 endpoint so seo-router.php can map unknown paths to a real 404.
    public function not_found_page()
    {
        return $this->_404();
    }

    // Build a BreadcrumbList node from [ [name,url], ... ]
    private function breadcrumb(array $items): array
    {
        $elements = [];
        $pos = 1;
        foreach ($items as $it) {
            $elements[] = [
                '@type'    => 'ListItem',
                'position' => $pos++,
                'name'     => $it[0],
                'item'     => $it[1],
            ];
        }
        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }

    // ───────────────────────── HELPERS ───────────────────────

    /**
     * Overlay admin-managed SEO (the `seo_fields` table, edited at
     * /admin/seo_settings) on top of the hardcoded defaults for a given route.
     *
     * ONLY the safe content fields win: title, description, keywords, og image.
     * canonical_url / custom_url / meta_robot / json_ld from the table are
     * DELIBERATELY ignored — stored canonicals point at the wrong domain
     * (.com / demo.creativeitem.com), meta_robot holds the literal placeholder
     * "Meta robot", and json_ld contains CodeCanyon demo data. Trusting any of
     * those would de-index the site. The canonical + JSON-LD generated by this
     * controller (always on the .uk domain) are kept instead.
     */
    /**
     * Make an image path absolute for og:image (Open Graph requires a full URL).
     * Empty -> the default banner; already-absolute -> unchanged; relative -> root-prefixed.
     */
    private function _abs_img($path): string
    {
        $path = trim((string) $path);
        if ($path === '') return $this->root . '/og-image.jpg';
        if (preg_match('#^https?://#i', $path)) return $path;
        return $this->root . '/' . ltrim($path, '/');
    }

    /**
     * Locate a course's thumbnail file and return its path relative to the web root
     * (e.g. uploads/thumbnails/course_thumbnails/optimized/...webp), or '' if none.
     * Mirrors Api_frontend_model::get_course_thumbnail naming. _render() then routes
     * it through img.php so WebP thumbnails become JPEG for social previews.
     */
    private function _course_thumb_rel($id, $lastmod): string
    {
        $theme = function_exists('get_frontend_settings') ? (get_frontend_settings('theme') ?: 'default-new') : 'default-new';
        $base  = 'course_thumbnail_' . $theme . '_' . $id . ($lastmod ?? '');
        $cands = [
            'uploads/thumbnails/course_thumbnails/optimized/' . $base . '.webp',
            'uploads/thumbnails/course_thumbnails/optimized/' . $base . '.jpg',
            'uploads/thumbnails/course_thumbnails/' . $base . '.jpg',
            'uploads/thumbnails/course_thumbnails/' . $base . '.png',
        ];
        foreach ($cands as $rel) {
            if (is_file(FCPATH . $rel)) return $rel;
        }
        return '';
    }

    /**
     * Read real dimensions + mime of an og:image so we can emit og:image:width/
     * height/type. WhatsApp needs explicit dimensions to render the large card
     * (Telegram infers them itself). Maps the absolute URL back to a local file.
     */
    private function _og_dims(string $url): array
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';           // /uploads/... or /og-image.jpg
        $candidates = [];
        if (($p = strpos($path, 'uploads/')) !== false) {
            $candidates[] = FCPATH . substr($path, $p);        // api/uploads/...
        }
        $candidates[] = FCPATH . '../' . ltrim($path, '/');    // docroot/<path> (og-image.jpg)
        $candidates[] = FCPATH . ltrim($path, '/');            // api/<path>
        foreach ($candidates as $f) {
            if (is_file($f) && ($sz = @getimagesize($f))) {
                return ['w' => (int) $sz[0], 'h' => (int) $sz[1], 'type' => $sz['mime'] ?? ''];
            }
        }
        return [];
    }

    private function _admin_seo(string $route, array $seo): array
    {
        $row = $this->crud_model->get_seo_by_route($route);
        if (!$row) return $seo;

        $dec = function ($v) { return trim(html_entity_decode((string) $v, ENT_QUOTES, 'UTF-8')); };

        $title = $dec($row->meta_title ?? '');
        if ($title !== '') $seo['title'] = $title;

        $desc = $dec($row->meta_description ?? '');
        if ($desc !== '') $seo['description'] = $desc;

        $kw = $dec($row->meta_keywords ?? '');
        if ($kw !== '') $seo['keywords'] = $kw;

        // Optional social-only overrides — content fields, safe to honor.
        // Fall back to title/description (handled in the layout) when empty.
        $ogTitle = $dec($row->og_title ?? '');
        if ($ogTitle !== '') $seo['og_title'] = $ogTitle;

        $ogDesc = $dec($row->og_description ?? '');
        if ($ogDesc !== '') $seo['og_description'] = $ogDesc;

        $ogImg = trim((string) ($row->og_image ?? ''));
        if ($ogImg !== '') {
            // stored as a bare filename under api/uploads/seo-og-images/ (served at /uploads/…)
            $seo['og_image'] = preg_match('#^https?://#i', $ogImg)
                ? $ogImg
                : $this->root . '/uploads/seo-og-images/' . $ogImg;
        }

        return $seo;
    }

    private function _render(string $partial, array $seo, array $data = []): void
    {
        // 10-min HTTP cache for crawlers — repeated fetches stay cheap
        header('Cache-Control: public, max-age=600');
        header('X-Robots-Tag: index, follow');

        // Resolve og:image so social crawlers (esp. WhatsApp) always get a clean,
        // correctly-typed JPEG with explicit dimensions.
        if (!empty($seo['og_image'])) {
            $d        = $this->_og_dims($seo['og_image']);
            $path     = (string) parse_url($seo['og_image'], PHP_URL_PATH);
            $ext      = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $extMimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
            $extMime  = $extMimes[$ext] ?? '';
            $isUpload = strpos($path, '/uploads/') !== false;

            if (!$d) {
                // Unreadable image → safe branded default.
                $seo['og_image'] = $this->root . '/og-image.jpg';
                $d = $this->_og_dims($seo['og_image']);
            } elseif ($isUpload && (in_array($d['type'], ['image/webp', 'image/gif'], true) || ($extMime && $d['type'] !== $extMime))) {
                // WebP/GIF (WhatsApp can't show these) or a type/extension mismatch
                // (e.g. a JPEG saved as .png): serve via the proxy that re-encodes to JPEG.
                // Clean URL ending in .jpg (no query string, no .webp/.png hint) →
                // .htaccess routes it to img.php which re-encodes to JPEG. Strict
                // crawlers (WhatsApp) reject query strings, non-jpeg extension hints,
                // and chunked responses as og:image.
                $rel      = substr($path, strpos($path, 'uploads/'));
                $relNoExt = preg_replace('#\.[A-Za-z0-9]+$#', '', $rel);
                $seo['og_image'] = $this->root . '/img/' . $relNoExt . '.jpg';
                $d['type'] = 'image/jpeg';
            }

            if ($d) {
                $seo['og_image_w']    = $d['w'];
                $seo['og_image_h']    = $d['h'];
                $seo['og_image_type'] = $d['type'];
            }
        }

        $vars = array_merge($seo, $data, ['partial' => $partial, 'root' => $this->root]);
        $this->load->view('seo/layout', $vars);
    }

    private function _404(): void
    {
        // Force a real 404 at the PHP level so the loopback fetch in seo-router.php
        // propagates it (CI's set_status_header alone wasn't reliable here).
        if (!headers_sent()) { http_response_code(404); }
        $this->output->set_status_header(404);
        $seo = [
            'title'       => 'Page not found — My-Communication Academy',
            'description' => 'The page you requested does not exist.',
            'canonical'   => $this->root . '/',
            'og_image'    => $this->root . '/og-image.jpg',
        ];
        $this->_render('not_found', $seo, []);
    }
}
