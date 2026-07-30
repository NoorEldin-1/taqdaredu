<?php
/**
 * /seo-router.php — single entry point for crawler-rewritten requests.
 *
 * Apache/LiteSpeed .htaccess detects bot User-Agents and rewrites the
 * request to /seo-router.php?path=<original_path>. This file makes an
 * internal HTTP fetch against the same host on /api/seo/<route> and
 * streams the response back. The hop is needed because LSWS doesn't
 * reliably preserve PATH_INFO through internal rewrites, but a real
 * .php file is invoked directly with no URI-parsing gymnastics.
 *
 * SEO impact: the bot still sees its original root URL (no redirect),
 * so every backlink and Search Console URL stays canonical.
 */

// --- 1. Sanitize the original path ---
$rawPath = isset($_GET['path']) ? trim((string) $_GET['path'], "/ \t\n\r\0\x0B") : '';
if ($rawPath !== '' && !preg_match('#^[a-z0-9/_\-]+$#i', $rawPath)) {
    http_response_code(400);
    echo "<h1>Invalid path</h1>";
    exit;
}

// --- 2. Map the original URL to an /api/seo/<route> target ---
// Unknown paths fall through to a 404 SEO page (don't silently serve the homepage).
$target = '/api/seo/not_found_page';
if ($rawPath === '' || $rawPath === '/') {
    $target = '/api/seo/home';
} elseif ($rawPath === 'courses') {
    $target = '/api/seo/courses';
} elseif (preg_match('#^courses/(\d+)(?:-[^/]*)?$#', $rawPath, $m)) {
    // Accept SEO-friendly /courses/<id>-<slug>; the leading id drives the lookup.
    $target = '/api/seo/course_detail/' . $m[1];
} elseif ($rawPath === 'instructors') {
    $target = '/api/seo/instructors';
} elseif (preg_match('#^instructors/(\d+)(?:-[^/]*)?$#', $rawPath, $m)) {
    $target = '/api/seo/instructor_detail/' . $m[1];
} elseif ($rawPath === 'about-us' || $rawPath === 'about_us') {
    $target = '/api/seo/about_us';
} elseif ($rawPath === 'become-instructor' || $rawPath === 'become_instructor') {
    $target = '/api/seo/become_instructor';
} elseif ($rawPath === 'contact-us' || $rawPath === 'contact_us') {
    $target = '/api/seo/contact_us';
} elseif ($rawPath === 'blog' || $rawPath === 'blogs') {
    $target = '/api/seo/blog';
} elseif (preg_match('#^blogs?/(\d+)(?:-[^/]*)?$#', $rawPath, $m)) {
    // React route is /blogs/<id> or /blogs/<id>-<slug>; also accept legacy /blog/<id>.
    $target = '/api/seo/blog_detail/' . $m[1];
} elseif ($rawPath === 'faq') {
    $target = '/api/seo/faq';
} elseif (preg_match('#^(privacy-policy|terms|terms-and-conditions|refund-policy|cookie-policy)$#', $rawPath, $m)) {
    $target = '/api/seo/page/' . $m[1];
}

// --- 3. Fetch the real SEO HTML over loopback ---
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'my-communication.uk';
$url    = $scheme . '://' . $host . $target;

$ctx = stream_context_create([
    'http' => [
        'method'        => 'GET',
        'timeout'       => 15,
        'ignore_errors' => true,   // capture 4xx/5xx bodies too
        'header'        => [
            'User-Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'CrawlerProxy/1.0'),
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9',
        ],
    ],
    'ssl' => [
        // Loopback may use cert that doesn't match the public CN; safe to skip
        'verify_peer'      => false,
        'verify_peer_name' => false,
    ],
]);

$body = @file_get_contents($url, false, $ctx);
$status = 200;
$respHeaders = function_exists('http_get_last_response_headers') ? @http_get_last_response_headers() : ($http_response_header ?? []);
if (is_array($respHeaders) && !empty($respHeaders[0]) && preg_match('#HTTP/\S+ (\d+)#', $respHeaders[0], $m)) {
    $status = (int) $m[1];
}

if ($body === false || $status >= 500) {
    http_response_code(502);
    echo "<h1>Upstream error</h1><p>Could not fetch SEO content for: " . htmlspecialchars($rawPath) . "</p>";
    exit;
}

// --- 4. Pass through the response ---
http_response_code($status ?: 200);
header('Content-Type: text/html; charset=UTF-8');
// no-transform tells Cloudflare not to modify this HTML — which also stops it
// injecting its JS-Detections script (/cdn-cgi/challenge-platform/.../jsd/main.js)
// that trips the PageSpeed "deprecated APIs" warning.
header('Cache-Control: public, max-age=600, no-transform');
header('X-Robots-Tag: index, follow');
echo $body;
