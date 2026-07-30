<?php
// Emergency Image Optimization Script
// Run this ONCE via browser: http://localhost/myco_uk/optimize_now.php

define('BASEPATH', 'dummy'); // Prevent accessing CI core
define('FCPATH', __DIR__ . '/'); // Root path

$source_image = 'assets/frontend/default-new/image/image_2.png';
$destination_image = 'assets/frontend/default-new/image/image_2.webp';

$full_source_path = FCPATH . $source_image;
$full_dest_path = FCPATH . $destination_image;

echo "<h1>Emergency Optimization Tool</h1>";
echo "<p><strong>Source:</strong> $full_source_path</p>";
echo "<p><strong>Destination:</strong> $full_dest_path</p>";

if (!file_exists($full_source_path)) {
    die("<h2 style='color:red;'>ERROR: Source image not found!</h2>");
}

if (file_exists($full_dest_path)) {
    echo "<p style='color:orange;'>WebP image already exists. Overwriting...</p>";
}

// Convert
$info = getimagesize($full_source_path);
if ($info['mime'] == 'image/png') {
    $image = imagecreatefrompng($full_source_path);
    if ($image) {
        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        if (imagewebp($image, $full_dest_path, 80)) {
            echo "<h2 style='color:green;'>SUCCESS: Image converted to WebP!</h2>";
            echo "<p>You can now manually update the view to use <code>image_2.webp</code> if you wish, or keep using the optimized PNG serving logic.</p>";
        } else {
            echo "<h2 style='color:red;'>ERROR: Conversion failed!</h2>";
        }
        imagedestroy($image);
    } else {
        echo "<h2 style='color:red;'>ERROR: Could not create image from PNG.</h2>";
    }
} else {
    die("<h2 style='color:red;'>ERROR: Source is not a PNG! Mime type: " . $info['mime'] . "</h2>");
}
?>