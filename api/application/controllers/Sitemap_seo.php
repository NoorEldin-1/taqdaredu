<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Dynamic XML sitemap — lists every public, indexable URL for crawlers.
 * Served at /sitemap.xml via a route in config/routes.php.
 * URLs MUST match the React SPA routes exactly (e.g. /blogs/<id>, /instructors/<id>).
 */
class Sitemap_seo extends CI_Controller
{
    private $root;

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->root = rtrim(get_settings('system_url') ?: 'https://my-communication.uk', '/');
    }

    private function lastmod($val)
    {
        if (empty($val)) return null;
        return date('Y-m-d', is_numeric($val) ? (int)$val : strtotime($val));
    }

    public function index()
    {
        $urls = [];

        // ── Static pages ──
        $urls[] = ['loc' => $this->root . '/',                  'priority' => '1.0', 'freq' => 'daily'];
        $urls[] = ['loc' => $this->root . '/courses',          'priority' => '0.9', 'freq' => 'daily'];
        $urls[] = ['loc' => $this->root . '/blogs',            'priority' => '0.8', 'freq' => 'daily'];
        $urls[] = ['loc' => $this->root . '/instructors',      'priority' => '0.7', 'freq' => 'weekly'];
        $urls[] = ['loc' => $this->root . '/about-us',         'priority' => '0.7', 'freq' => 'monthly'];
        $urls[] = ['loc' => $this->root . '/become-instructor','priority' => '0.7', 'freq' => 'monthly'];
        $urls[] = ['loc' => $this->root . '/contact-us',       'priority' => '0.6', 'freq' => 'monthly'];
        // Legal / static content pages
        $urls[] = ['loc' => $this->root . '/privacy-policy',   'priority' => '0.3', 'freq' => 'yearly'];
        $urls[] = ['loc' => $this->root . '/terms',            'priority' => '0.3', 'freq' => 'yearly'];
        $urls[] = ['loc' => $this->root . '/refund-policy',    'priority' => '0.3', 'freq' => 'yearly'];
        $urls[] = ['loc' => $this->root . '/faq',              'priority' => '0.5', 'freq' => 'monthly'];

        // ── Category filter pages ──
        $cats = $this->db->select('id')->where('parent >', 0)->get('category')->result_array();
        foreach ($cats as $cat) {
            $urls[] = ['loc' => $this->root . '/courses?category=' . $cat['id'], 'priority' => '0.6', 'freq' => 'weekly'];
        }

        // ── Courses (active + upcoming) — SEO-friendly /courses/<id>-<slug> ──
        $courses = $this->db->select('id, title, last_modified')
            ->where_in('status', ['active', 'upcoming'])
            ->get('course')->result_array();
        foreach ($courses as $c) {
            $urls[] = [
                'loc'      => $this->root . '/courses/' . $c['id'] . '-' . slugify($c['title'] ?? ''),
                'priority' => '0.8',
                'freq'     => 'weekly',
                'lastmod'  => $this->lastmod($c['last_modified'] ?? null),
            ];
        }

        // ── Blogs (published) — React route is /blogs/<id>-<slug> ──
        $blogs = $this->db->select('blog_id, title, updated_date, added_date')->where('status', '1')->get('blogs')->result_array();
        foreach ($blogs as $b) {
            $urls[] = [
                'loc'      => $this->root . '/blogs/' . $b['blog_id'] . '-' . slugify($b['title'] ?? ''),
                'priority' => '0.7',
                'freq'     => 'weekly',
                'lastmod'  => $this->lastmod($b['updated_date'] ?? ($b['added_date'] ?? null)),
            ];
        }

        // ── Instructors (active) ──
        $instructors = $this->db->select('id, last_modified')->where('is_instructor', 1)->where('status', 1)->get('users')->result_array();
        foreach ($instructors as $i) {
            $urls[] = [
                'loc'      => $this->root . '/instructors/' . $i['id'],
                'priority' => '0.6',
                'freq'     => 'monthly',
                'lastmod'  => $this->lastmod($i['last_modified'] ?? null),
            ];
        }

        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.w3.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            echo "  <url>\n";
            echo "    <loc>" . htmlspecialchars($u['loc']) . "</loc>\n";
            if (!empty($u['lastmod'])) echo "    <lastmod>" . $u['lastmod'] . "</lastmod>\n";
            echo "    <changefreq>" . $u['freq'] . "</changefreq>\n";
            echo "    <priority>" . $u['priority'] . "</priority>\n";
            echo "  </url>\n";
        }
        echo '</urlset>' . "\n";
    }
}
