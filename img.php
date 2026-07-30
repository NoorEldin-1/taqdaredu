<?php
/**
 * img.php — social-preview image proxy.
 *
 * Re-encodes any whitelisted uploads image to a clean JPEG with an explicit
 * Content-Length and image/jpeg type, so strict crawlers (WhatsApp) accept it
 * as an og:image. WhatsApp rejects WebP, MIME/extension mismatches, AND
 * chunked / length-less responses — any of which collapses the whole preview
 * to "domain only".
 *
 * Called via a clean rewrite (see .htaccess):
 *   /img/uploads/blog/banner/<name>.jpg  →  img.php?src=uploads/blog/banner/<name>
 * The caller drops the real extension (.webp/.png/…) in favour of a clean .jpg
 * URL with no extension hint, so we probe common extensions to find the file.
 */

$src = isset($_GET['src']) ? (string) $_GET['src'] : '';

// Whitelist: uploads/<safe path>, no traversal. Extension is optional here.
if (!preg_match('#^uploads/[A-Za-z0-9._/\-]+$#', $src) || strpos($src, '..') !== false) {
    http_response_code(404);
    exit;
}

$baseDir = realpath(__DIR__ . '/api/uploads');
if ($baseDir === false) { http_response_code(404); exit; }

// Resolve the real file. If src has no image extension, probe common ones.
$candidates = [$src];
if (!preg_match('#\.(png|jpe?g|webp|gif)$#i', $src)) {
    foreach (['webp', 'jpg', 'jpeg', 'png', 'gif'] as $ext) {
        $candidates[] = $src . '.' . $ext;
    }
}
$real = false;
foreach ($candidates as $cand) {
    $p = realpath(__DIR__ . '/api/' . $cand);
    if ($p !== false && strpos($p, $baseDir) === 0 && is_file($p)) { $real = $p; break; }
}
if ($real === false) { http_response_code(404); exit; }

header('Cache-Control: public, max-age=604800'); // 7 days

// Preferred path: re-encode to JPEG (handles WebP/PNG/JPEG/GIF uniformly) and
// send buffered with an explicit Content-Length (never chunked).
if (function_exists('imagecreatefromstring')) {
    $data = @file_get_contents($real);
    $im = ($data !== false) ? @imagecreatefromstring($data) : false;
    if ($im !== false) {
        $w = imagesx($im);
        $h = imagesy($im);
        // Flatten any transparency onto white (JPEG has no alpha channel).
        $canvas = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $w, $h, $white);
        imagecopy($canvas, $im, 0, 0, 0, 0, $w, $h);
        ob_start();
        imagejpeg($canvas, null, 85);
        $jpeg = ob_get_clean();
        imagedestroy($im);
        imagedestroy($canvas);
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . strlen($jpeg));
        echo $jpeg;
        exit;
    }
}

// Fallback: stream raw bytes with a detected type + Content-Length.
$info = @getimagesize($real);
$mime = ($info && !empty($info['mime'])) ? $info['mime'] : 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($real));
readfile($real);
