<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Fixer extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        // Security check: This should only be run by admins or in dev environment
        // For this task, we assume the user is running it manually.
        // if (!$this->session->userdata('admin_login')) { exit('Access Denied'); }
    }

    public function scan_and_fix_views()
    {
        echo "<h1>Starting View Fixer...</h1><pre>";

        $views_path = APPPATH . 'views';
        $this->recursive_scan($views_path);

        echo "</pre><h1>Done!</h1>";
    }

    private function recursive_scan($dir)
    {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file == '.' || $file == '..')
                continue;

            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->recursive_scan($path);
            } else {
                if (pathinfo($path, PATHINFO_EXTENSION) == 'php') {
                    $this->process_file($path);
                }
            }
        }
    }

    private function process_file($filepath)
    {
        $content = file_get_contents($filepath);
        $original_content = $content;
        $modified = false;

        // Regex to find <img> tags
        // This is a basic regex, might need adjustment for complex tags
        if (preg_match_all('/<img\s+([^>]+)>/i', $content, $matches)) {
            foreach ($matches[0] as $full_tag) {

                // Skip if already has width and height
                if (stripos($full_tag, 'width=') !== false && stripos($full_tag, 'height=') !== false) {
                    continue;
                }

                // Extract SRC
                if (preg_match('/src=["\']([^"\']+)["\']/i', $full_tag, $src_match)) {
                    $src = $src_match[1];

                    // Resolve file path
                    // Handle PHP tags in src if possible, or static paths
                    // If it contains <?php echo base_url... we can't easily resolve on disk unless we parse it.
                    // STRATEGY: Look for known static folders like 'uploads', 'assets'

                    $fs_path = '';

                    // Remove php tags to see if we can get a clean path part
                    // This is tricky for dynamic paths like <?php echo base_url('uploads/...') 
                    // We try to extract the relative path string

                    if (strpos($src, 'uploads/') !== false) {
                        $parts = explode('uploads/', $src);
                        $rel_path = 'uploads/' . end($parts);
                        // Clean up any trailing PHP code or quotes if regex was loose
                        $rel_path = preg_replace('/[\'"].*/', '', $rel_path);
                        // Remove PHP tags if they are at the end
                        $rel_path = preg_replace('/\s*\?>.*/', '', $rel_path);
                        $fs_path = FCPATH . $rel_path;
                    } elseif (strpos($src, 'assets/') !== false) {
                        $parts = explode('assets/', $src);
                        $rel_path = 'assets/' . end($parts);
                        $rel_path = preg_replace('/[\'"].*/', '', $rel_path);
                        $fs_path = FCPATH . $rel_path;
                    }

                    // Check if file exists and get dimensions
                    // Note: We strip potential PHP tags wrapper characters to find the real file if it's a mix
                    // This is a "best effort" for static images or simple dynamic ones

                    if ($fs_path && file_exists($fs_path) && !is_dir($fs_path)) {
                        list($width, $height) = @getimagesize($fs_path);

                        if ($width && $height) {
                            $new_tag = $full_tag;

                            // Add width/height if missing
                            if (stripos($new_tag, 'width=') === false) {
                                $new_tag = str_replace('<img ', '<img width="' . $width . '" ', $new_tag);
                            }
                            if (stripos($new_tag, 'height=') === false) {
                                $new_tag = str_replace('<img ', '<img height="' . $height . '" ', $new_tag);
                            }

                            // Add loading attribute
                            $classes = '';
                            if (preg_match('/class=["\']([^"\']+)["\']/i', $new_tag, $class_match)) {
                                $classes = $class_match[1];
                            }

                            if (stripos($classes, 'hero') !== false || stripos($classes, 'lcp') !== false) {
                                $new_tag = str_replace('<img ', '<img loading="eager" fetchpriority="high" ', $new_tag);
                            } else {
                                if (stripos($new_tag, 'loading=') === false) {
                                    $new_tag = str_replace('<img ', '<img loading="lazy" ', $new_tag);
                                }
                            }

                            $content = str_replace($full_tag, $new_tag, $content);
                            $modified = true;
                            echo "Fixed: " . basename($filepath) . " -> " . $rel_path . " ($width x $height)\n";
                        }
                    }
                }
            }
        }

        if ($modified) {
            // Write back to file
            if (is_writable($filepath)) {
                file_put_contents($filepath, $content);
                echo "SAVED: $filepath\n";
            } else {
                echo "ERROR: Not writable $filepath\n";
            }
        }
    }
}
