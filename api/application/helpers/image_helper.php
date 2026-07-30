<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('optimize_img')) {
    /**
     * Optimize image by converting to WebP if possible.
     * 
     * @param string $path Relative path to the image from the webroot (e.g. 'assets/image.png')
     * @return string Full URL to the optimized image
     */
    function optimize_img($path)
    {
        // Normalize path: remove base_url if present
        $ci =& get_instance();
        $base_url = $ci->config->item('base_url');

        // Remove base URL to get relative path
        $relative_path = str_replace($base_url, '', $path);
        $relative_path = ltrim($relative_path, '/');

        // EMERGENCY FIX: DISABLE ON-THE-FLY OPTIMIZATION
        // Only run if specifically requested via CLI or 'run_optimization' param
        $allow_optimization = false;
        if (is_cli() || (isset($_GET['run_optimization']) && $_GET['run_optimization'] == '1')) {
            $allow_optimization = true;
        }

        if (!$allow_optimization) {
            // Return original URL immediately to prevent 9.5s blocking delay
            if (strpos($path, 'http') === 0) {
                return $path;
            }
            return base_url($relative_path);
        }

        $file_path = FCPATH . $relative_path;

        // If file doesn't exist, return original input (it might be an external URL or invalid path)
        if (!file_exists($file_path)) {
            // If it was passed as a full URL, return it, else append base_url
            if (strpos($path, 'http') === 0) {
                return $path;
            }
            return base_url($relative_path);
        }

        $extension = pathinfo($file_path, PATHINFO_EXTENSION);
        $allowed_extensions = ['jpg', 'jpeg', 'png'];

        if (!in_array(strtolower($extension), $allowed_extensions)) {
            return base_url($relative_path);
        }

        // Define WebP path
        $webp_file_path = preg_replace('/\.' . $extension . '$/i', '.webp', $file_path);
        $webp_relative_path = preg_replace('/\.' . $extension . '$/i', '.webp', $relative_path);

        // Serve WebP if checks pass
        if (file_exists($webp_file_path)) {
            // Optional: Check if original is newer than cache, if so, regenerate? 
            // For performance, assuming once generated it's good.
            return base_url($webp_relative_path);
        }

        // Generate WebP
        try {
            $image = null;
            $ext = strtolower($extension);
            if ($ext === 'png') {
                $image = @imagecreatefrompng($file_path);
                if ($image) {
                    imagepalettetotruecolor($image);
                    imagealphablending($image, true);
                    imagesavealpha($image, true);
                }
            } elseif ($ext === 'jpg' || $ext === 'jpeg') {
                $image = @imagecreatefromjpeg($file_path);
            }

            if ($image) {
                // Quality 85 is usually good balance
                imagewebp($image, $webp_file_path, 80);
                imagedestroy($image);
                return base_url($webp_relative_path);
            }
        } catch (Exception $e) {
            log_message('error', 'WebP conversion failed for ' . $relative_path . ': ' . $e->getMessage());
        }

        return base_url($relative_path);
    }
}
