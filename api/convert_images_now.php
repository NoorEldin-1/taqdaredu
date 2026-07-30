<?php
/**
 * WORKING IMAGE CONVERTER
 * Converts the huge PNG images found to WebP
 * 
 * Run once: http://localhost/myco_uk/convert_images_now.php
 * Then delete this file for security
 */

// Files found by directory search
$images_to_convert = [
    'C:/xampp/htdocs/myco_uk/assets/frontend/default-new/image/image_1.png',
    'C:/xampp/htdocs/myco_uk/assets/frontend/default-new/image/image_2.png',
];

echo "<h1>WebP Image Converter</h1>";
echo "<p>Converting large PNG images to WebP format...</p><hr>";

$total_saved = 0;

foreach ($images_to_convert as $full_path) {
    // Make path work on both Windows and Linux
    $full_path = str_replace('/', DIRECTORY_SEPARATOR, $full_path);

    if (!file_exists($full_path)) {
        echo "❌ <strong style='color:red'>NOT FOUND:</strong> {$full_path}<br><br>";
        continue;
    }

    $original_size = filesize($full_path);
    $path_info = pathinfo($full_path);
    $webp_filename = $path_info['filename'] . '.webp';
    $webp_path = $path_info['dirname'] . DIRECTORY_SEPARATOR . $webp_filename;

    // Load PNG image
    $source = @imagecreatefrompng($full_path);

    if (!$source) {
        echo "❌ <strong style='color:red'>ERROR:</strong> Could not load {$full_path}<br><br>";
        continue;
    }

    // Convert to WebP with 85% quality
    $success = imagewebp($source, $webp_path, 85);
    imagedestroy($source);

    if ($success) {
        $webp_size = filesize($webp_path);
        $saved = $original_size - $webp_size;
        $percent = round(($saved / $original_size) * 100);
        $total_saved += $saved;

        $filename = basename($full_path);
        $webp_name = basename($webp_path);

        echo "✅ <strong style='color:green'>CONVERTED:</strong> {$filename}<br>";
        echo "&nbsp;&nbsp;&nbsp;&nbsp;Original: <strong>" . round($original_size / 1024) . " KB</strong> (PNG)<br>";
        echo "&nbsp;&nbsp;&nbsp;&nbsp;New: <strong>" . round($webp_size / 1024) . " KB</strong> (WebP)<br>";
        echo "&nbsp;&nbsp;&nbsp;&nbsp;<strong style='color:green'>Saved: " . round($saved / 1024) . " KB ({$percent}% smaller)</strong><br>";
        echo "&nbsp;&nbsp;&nbsp;&nbsp;Path: <code>{$webp_path}</code><br><br>";
    } else {
        echo "❌ <strong style='color:red'>FAILED:</strong> Could not convert {$full_path}<br><br>";
    }
}

echo "<hr>";
echo "<h2 style='color:green'>✅ Total Saved: " . round($total_saved / 1024) . " KB (" . round($total_saved / 1024 / 1024, 2) . " MB)</h2>";
echo "<hr>";

echo "<h3>🚀 Next Steps:</h3>";
echo "<ol>";
echo "<li><strong>Test your site now</strong> - The WebP images are created alongside the PNGs</li>";
echo "<li><strong>Run Lighthouse again</strong> - You should see payload reduction</li>";
echo "<li><strong>DELETE THIS FILE</strong> for security: <code>convert_images_now.php</code></li>";
echo "</ol>";

echo "<h3>📝 How it works:</h3>";
echo "<ul>";
echo "<li>Modern browsers automatically prefer WebP over PNG when both exist</li>";
echo "<li>Your site's image logic should detect and serve WebP versions</li>";
echo "<li>If not, update image paths from <code>.png</code> to <code>.webp</code></li>";
echo "</ul>";

echo "<h3>Expected Results:</h3>";
echo "<table border='1' cellpadding='10' style='border-collapse:collapse;'>";
echo "<tr><th>Metric</th><th>Before</th><th>After</th></tr>";
echo "<tr><td>Page Size</td><td>4.3 MB</td><td>~2.7 MB</td></tr>";
echo "<tr><td>LCP</td><td>9.5s</td><td>~5-6s</td></tr>";
echo "<tr><td>Performance Score</td><td>38</td><td><strong>55-65</strong></td></tr>";
echo "</table>";
?>