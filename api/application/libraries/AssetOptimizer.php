<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class AssetOptimizer
{

    protected $CI;
    protected $quality = 80;
    protected $min_size = 51200; // 50KB

    public function __construct()
    {
        $this->CI =& get_instance();
    }

    public function run()
    {
        echo "<h1>Starting Asset Optimizer...</h1><pre>";

        $directories = [
            FCPATH . 'uploads/system',
            FCPATH . 'assets/frontend/default-new/image',
            FCPATH . 'assets' // Scan root assets too just in case
        ];

        $replacements = [];

        foreach ($directories as $dir) {
            if (!is_dir($dir))
                continue;
            echo "Scanning: $dir\n";
            $this->scan_dir($dir, $replacements);
        }

        echo "\n\n=== SEARCH & REPLACE LIST (Run these updates in your DB or Code) ===\n";
        foreach ($replacements as $original => $webp) {
            echo "Original: $original\nREPLACE WITH: $webp\n---------------------------------\n";
        }

        echo "</pre><h1>Done!</h1>";
    }

    private function scan_dir($dir, &$replacements)
    {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file == '.' || $file == '..')
                continue;

            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                // $this->scan_dir($path, $replacements); // Recursive if needed, but let's stick to flat for specified dirs to avoid mess
                if (basename($path) == 'system' || basename($path) == 'image') {
                    $this->scan_dir($path, $replacements);
                }
            } else {
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                    if (filesize($path) > $this->min_size) {
                        $webp_path = $this->convert_to_webp($path);
                        if ($webp_path) {
                            $replacements[basename($path)] = basename($webp_path);
                            // Also full relative path for view replacement
                            $rel_orig = str_replace(FCPATH, '', $path);
                            $rel_webp = str_replace(FCPATH, '', $webp_path);
                            // Normalize slashes
                            $rel_orig = str_replace('\\', '/', $rel_orig);
                            $rel_webp = str_replace('\\', '/', $rel_webp);
                            $replacements[$rel_orig] = $rel_webp;
                        }
                    }
                }
            }
        }
    }

    private function convert_to_webp($source_path)
    {
        $info = getimagesize($source_path);
        if (!$info)
            return false;

        $mime = $info['mime'];
        $image = null;

        switch ($mime) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($source_path);
                break;
            case 'image/png':
                $image = imagecreatefrompng($source_path);
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
                break;
        }

        if ($image) {
            $webp_path = pathinfo($source_path, PATHINFO_DIRNAME) . '/' . pathinfo($source_path, PATHINFO_FILENAME) . '_optimized.webp';
            // Only convert if webp doesn't exist or is older
            if (!file_exists($webp_path) || filemtime($source_path) > filemtime($webp_path)) {
                imagewebp($image, $webp_path, $this->quality);
                echo "Converted: " . basename($source_path) . " -> " . basename($webp_path) . "\n";
            } else {
                echo "Skipped (Already Optimized): " . basename($source_path) . "\n";
            }
            imagedestroy($image);
            return $webp_path;
        }

        return false;
    }
}
