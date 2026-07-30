<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Tools extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        // Security check: Only allow localhost
        $allowed_ips = ['127.0.0.1', '::1'];
        if (!in_array($_SERVER['REMOTE_ADDR'], $allowed_ips) && !is_cli()) {
            die('Access Denied: Localhost only tool.');
        }
    }

    // Action 1: Regex-based script to find <img> tags missing dimensions
    public function find_unsized_images()
    {
        echo "<h1>Unsized Image Scanner</h1>";
        $views_path = APPPATH . 'views/';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($views_path));

        $count = 0;
        echo "<pre>";
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());
                // Regex to find <img> tags without width and height
                // This is a basic check; might need refinement for complex attributes
                if (preg_match_all('/<img(?![^>]*\b(width|height)=)[^>]*>/i', $content, $matches)) {
                    foreach ($matches[0] as $match) {
                        echo "Found in <strong>" . str_replace(APPPATH, '', $file->getPathname()) . "</strong>:\n";
                        echo htmlspecialchars($match) . "\n";
                        // Suggested fix output
                        echo "Suggestion: Add width=\"100\" height=\"100\" or class=\"aspect-ratio-box\"\n\n";
                        $count++;
                    }
                }
            }
        }
        echo "</pre>";
        echo "<h3>Total Potential Unsized Images Found: $count</h3>";
    }

    // Action 2: Convert images to WebP
    public function convert_all_images()
    {
        echo "<h1>Image Optimization Tool</h1>";
        // Target directory from user request
        $target_dir = FCPATH . 'assets/images/';

        if (!is_dir($target_dir)) {
            echo "Directory not found: $target_dir <br>";
            // Fallback to checking typical CI paths or just report error
            // Try assets/frontend/default-new/image/ based on file contents seen
            $target_dir = FCPATH . 'assets/frontend/default-new/image/';
            echo "Trying alternative: $target_dir <br>";
        }

        if (!is_dir($target_dir)) {
            die("Could not locate image directory.");
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target_dir));
        $converted_count = 0;
        $skipped_count = 0;

        foreach ($files as $file) {
            if ($file->isFile()) {
                $ext = strtolower($file->getExtension());
                if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                    $filesize = $file->getSize();
                    // > 100KB
                    if ($filesize > 102400) {
                        $source_path = $file->getPathname();
                        $webp_path = $file->getPath() . '/' . $file->getBasename('.' . $ext) . '.webp';

                        if (!file_exists($webp_path)) {
                            $this->_convert_to_webp($source_path, $webp_path, $ext);
                            echo "Converted: " . $file->getFilename() . " -> " . basename($webp_path) . " (" . round($filesize / 1024) . "KB)<br>";
                            $converted_count++;
                        } else {
                            $skipped_count++;
                        }
                    }
                }
            }
        }

        echo "<h3>Conversion Complete. Converted: $converted_count, Skipped (Already exists): $skipped_count</h3>";
    }

    private function _convert_to_webp($source, $destination, $ext)
    {
        $quality = 80;
        if ($ext == 'png') {
            $image = imagecreatefrompng($source);
            imagepalettetotruecolor($image);
            imagealphablending($image, true);
            imagesavealpha($image, true);
        } else {
            $image = imagecreatefromjpeg($source);
        }

        imagewebp($image, $destination, $quality);
        imagedestroy($image);
    }
}
