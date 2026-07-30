<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * ImageOptimizer Helper for CodeIgniter
 * 
 * Automatically converts images to WebP, generates responsive srcset,
 * and adds proper attributes to prevent CLS
 */

if (!function_exists('optimized_image')) {
    /**
     * Generate optimized image tag with WebP support, srcset, and lazy loading
     * 
     * @param string $image_path Path to the original image (relative to base_url)
     * @param string $alt Alt text for the image
     * @param array $sizes Responsive sizes array [width => descriptor] e.g. [400 => '400w', 800 => '800w']
     * @param array $attributes Additional HTML attributes
     * @return string Complete <img> tag with optimizations
     */
    function optimized_image($image_path, $alt = '', $sizes = [], $attributes = [])
    {
        $CI =& get_instance();

        // Get the full server path
        $base_path = FCPATH; // CodeIgniter's front controller path
        $full_path = $base_path . ltrim($image_path, '/');

        // Check if original image exists
        if (!file_exists($full_path)) {
            return "<!-- Image not found: {$image_path} -->";
        }

        // Get image dimensions
        list($width, $height) = @getimagesize($full_path);

        if (!$width || !$height) {
            return "<!-- Unable to read image dimensions: {$image_path} -->";
        }

        // Generate WebP version
        $webp_path = convert_to_webp($image_path);

        // Build srcset if sizes are provided
        $srcset = '';
        if (!empty($sizes)) {
            $srcset_parts = [];
            foreach ($sizes as $size_width => $descriptor) {
                $resized_webp = generate_responsive_image($image_path, $size_width);
                if ($resized_webp) {
                    $srcset_parts[] = base_url($resized_webp) . ' ' . $descriptor;
                }
            }
            $srcset = !empty($srcset_parts) ? 'srcset="' . implode(', ', $srcset_parts) . '"' : '';
        }

        // Build attributes
        $attr_string = '';
        foreach ($attributes as $key => $value) {
            $attr_string .= ' ' . $key . '="' . htmlspecialchars($value) . '"';
        }

        // Build the picture element with WebP support
        $output = '<picture>';

        // WebP source
        if ($webp_path && file_exists($base_path . ltrim($webp_path, '/'))) {
            $output .= '<source type="image/webp" src="' . base_url($webp_path) . '" ' . $srcset . '>';
        }

        // Fallback to original image
        $loading = isset($attributes['loading']) ? $attributes['loading'] : 'lazy';
        $fetchpriority = isset($attributes['fetchpriority']) ? ' fetchpriority="' . $attributes['fetchpriority'] . '"' : '';

        $output .= '<img src="' . base_url($image_path) . '" ';
        $output .= 'alt="' . htmlspecialchars($alt) . '" ';
        $output .= 'width="' . $width . '" ';
        $output .= 'height="' . $height . '" ';
        $output .= 'loading="' . $loading . '"';
        $output .= $fetchpriority;
        $output .= $attr_string . '>';
        $output .= '</picture>';

        return $output;
    }
}

if (!function_exists('convert_to_webp')) {
    /**
     * Convert image to WebP format (creates if doesn't exist)
     * 
     * @param string $image_path Relative path to original image
     * @return string|false WebP path or false on failure
     */
    function convert_to_webp($image_path)
    {
        $base_path = FCPATH;
        $full_path = $base_path . ltrim($image_path, '/');

        // Check if GD supports WebP
        if (!function_exists('imagewebp')) {
            return false;
        }

        // Generate WebP filename
        $path_info = pathinfo($full_path);
        $webp_filename = $path_info['filename'] . '.webp';
        $webp_dir = $path_info['dirname'] . '/webp/';
        $webp_full_path = $webp_dir . $webp_filename;

        // Create webp directory if it doesn't exist
        if (!is_dir($webp_dir)) {
            mkdir($webp_dir, 0755, true);
        }

        // If WebP already exists and is newer than original, use it
        if (file_exists($webp_full_path) && filemtime($webp_full_path) >= filemtime($full_path)) {
            return str_replace($base_path, '', $webp_full_path);
        }

        // Create WebP image
        $extension = strtolower($path_info['extension']);
        $image = null;

        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $image = @imagecreatefromjpeg($full_path);
                break;
            case 'png':
                $image = @imagecreatefrompng($full_path);
                break;
            case 'gif':
                $image = @imagecreatefromgif($full_path);
                break;
            default:
                return false;
        }

        if (!$image) {
            return false;
        }

        // Convert to WebP with 85% quality (good balance)
        $success = imagewebp($image, $webp_full_path, 85);
        imagedestroy($image);

        if ($success) {
            return str_replace($base_path, '', $webp_full_path);
        }

        return false;
    }
}

if (!function_exists('generate_responsive_image')) {
    /**
     * Generate responsive image variant at specific width
     * 
     * @param string $image_path Original image path
     * @param int $target_width Target width in pixels
     * @return string|false Path to resized image or false
     */
    function generate_responsive_image($image_path, $target_width)
    {
        $base_path = FCPATH;
        $full_path = $base_path . ltrim($image_path, '/');

        if (!file_exists($full_path)) {
            return false;
        }

        list($original_width, $original_height) = @getimagesize($full_path);

        // If target width is larger than original, skip
        if ($target_width >= $original_width) {
            return convert_to_webp($image_path);
        }

        $path_info = pathinfo($full_path);
        $resized_dir = $path_info['dirname'] . '/responsive/';
        $resized_filename = $path_info['filename'] . '_' . $target_width . 'w.webp';
        $resized_full_path = $resized_dir . $resized_filename;

        // Create responsive directory if needed
        if (!is_dir($resized_dir)) {
            mkdir($resized_dir, 0755, true);
        }

        // If resized version exists and is newer, use it
        if (file_exists($resized_full_path) && filemtime($resized_full_path) >= filemtime($full_path)) {
            return str_replace($base_path, '', $resized_full_path);
        }

        // Calculate new dimensions
        $ratio = $target_width / $original_width;
        $target_height = (int) ($original_height * $ratio);

        // Load original image
        $extension = strtolower($path_info['extension']);
        $source = null;

        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $source = @imagecreatefromjpeg($full_path);
                break;
            case 'png':
                $source = @imagecreatefrompng($full_path);
                break;
            case 'gif':
                $source = @imagecreatefromgif($full_path);
                break;
            default:
                return false;
        }

        if (!$source) {
            return false;
        }

        // Create resized image
        $resized = imagecreatetruecolor($target_width, $target_height);

        // Preserve transparency for PNG
        if ($extension === 'png') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
        }

        imagecopyresampled($resized, $source, 0, 0, 0, 0, $target_width, $target_height, $original_width, $original_height);

        // Save as WebP
        $success = imagewebp($resized, $resized_full_path, 85);

        imagedestroy($source);
        imagedestroy($resized);

        if ($success) {
            return str_replace($base_path, '', $resized_full_path);
        }

        return false;
    }
}

if (!function_exists('lcp_image')) {
    /**
     * Generate LCP (Largest Contentful Paint) optimized image
     * This should be used for above-the-fold hero images
     * 
     * @param string $image_path Path to image
     * @param string $alt Alt text
     * @param array $attributes Additional attributes
     * @return string Optimized image tag with fetchpriority="high"
     */
    function lcp_image($image_path, $alt = '', $attributes = [])
    {
        $attributes['loading'] = 'eager'; // Don't lazy load LCP images!
        $attributes['fetchpriority'] = 'high';

        return optimized_image($image_path, $alt, [], $attributes);
    }
}
