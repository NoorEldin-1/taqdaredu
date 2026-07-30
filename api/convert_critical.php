<?php
/**
 * CRITICAL IMAGE CONVERTER
 * Converts the huge PNG images to WebP to reduce payload from 4.3MB to ~1.5MB
 * 
 * Run once: http://localhost/myco_uk/convert_critical.php
 * Then delete this file for security
 */

$images_to_convert = [
    'assets/frontend/default-new/image/image_1.png',  // 663KB
    'assets/frontend/default-new/image/image_2.png',  // 923KB
];

echo "<h1>Critical Image Converter</h1>";
echo "<p>Converting huge PNG images to WebP...</p><hr>";

foreach ($images_to_convert as $image_path) {
    $full_path = __DIR__ . '/' . $image_path;

    if (!file_exists($full_path)) {
        echo "❌ <strong style='color:red'>SKIP:</strong> {$image_path} (not found)<br>";
        continue;
    }

    $original_size = filesize($full_path);
    $path_info = pathinfo($full_path);
    $webp_filename = $path_info['filename'] . '.webp';
    $webp_path = $path_info['dirname'] . '/' . $webp_filename;

    // Load image
    $source = null;
    if ($path_info['extension'] == 'png') {
        $source = @imagecreatefrompng($full_path);
    } elseif (in_array($path_info['extension'], ['jpg', 'jpeg'])) {
        $source = @imagecreatefromjpeg($full_path);
    }

    if (!$source) {
        echo "❌ <strong style='color:red'>ERROR:</strong> Could not load {$image_path}<br>";
        continue;
    }

    // Convert to WebP
    $success = imagewebp($source, $webp_path, 85);
    imagedestroy($source);

    if ($success) {
        $webp_size = filesize($webp_path);
        $saved = $original_size - $webp_size;
        $percent = round(($saved / $original_size) * 100);

        echo "✅ <strong style='color:green'>SUCCESS:</strong> {$image_path}<br>";
        echo "&nbsp;&nbsp;&nbsp;&nbsp;Original: " . round($original_size / 1024) . " KB → WebP: " . round($webp_size / 1024) . " KB<br>";
        echo "&nbsp;&nbsp;&nbsp;&nbsp;<strong>Saved: " . round($saved / 1024) . " KB ({$percent}%)</strong><br><br>";
    } else {
        echo "❌ <strong style='color:red'>FAILED:</strong> Could not convert {$image_path}<br>";
    }
}

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>Refresh your page and re-run Lighthouse</li>";
echo "<li>Expected improvement: 4.3MB → ~1.5MB payload</li>";
echo "<li>DELETE THIS FILE (convert_critical.php) for security </li>";
echo "</ol>";

echo "<h3>How to use WebP images in views:</h3>";
echo "<pre>";
echo 'Instead of: &lt;img src="assets/frontend/default-new/image/image_1.png"&gt;
Use: &lt;img src="assets/frontend/default-new/image/image_1.webp"&gt;';
echo "</pre>";
?>