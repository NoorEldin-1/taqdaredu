<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Cache Clear Controller
 * 
 * Use this to clear all performance caches
 * Access: http://localhost/myco_uk/clear_cache
 */
class Clear_cache extends CI_Controller
{

    public function index()
    {
        echo "<h1>Cache Clearing Utility</h1>";
        echo "<hr>";

        // 1. Clear CodeIgniter Output Cache
        echo "<h2>1. Clearing Output Cache...</h2>";
        $this->output->delete_cache();
        $this->clear_directory(APPPATH . 'cache/');
        echo "✓ Output cache cleared<br><br>";

        // 2. Clear Asset Minifier Cache
        echo "<h2>2. Clearing Asset Cache...</h2>";
        $this->load->library('asset_minifier');
        $this->asset_minifier->clear_cache();
        echo "✓ Asset bundles cleared<br><br>";

        // 3. Clear Database Cache
        echo "<h2>3. Clearing Database Cache...</h2>";
        $this->db->cache_delete_all();
        echo "✓ Database query cache cleared<br><br>";

        // 4. Optional: Clear generated WebP images (use with caution)
        if (isset($_GET['clear_images']) && $_GET['clear_images'] == 'yes') {
            echo "<h2>4. Clearing Generated Images...</h2>";
            $this->clear_webp_images();
            echo "✓ WebP and responsive images cleared<br><br>";
        } else {
            echo "<h2>4. Generated Images</h2>";
            echo "WebP images NOT cleared (to clear, visit: <a href='?clear_images=yes'>?clear_images=yes</a>)<br><br>";
        }

        echo "<hr>";
        echo "<h2 style='color: green;'>✓ All caches cleared successfully!</h2>";
        echo "<p><a href='" . base_url() . "'>← Return to Home</a></p>";
    }

    /**
     * Clear directory recursively
     */
    private function clear_directory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '*', GLOB_MARK);
        foreach ($files as $file) {
            if (is_dir($file)) {
                $this->clear_directory($file);
            } else {
                // Don't delete .htaccess or index.html
                if (basename($file) !== '.htaccess' && basename($file) !== 'index.html') {
                    @unlink($file);
                }
            }
        }
    }

    /**
     * Clear generated WebP and responsive images
     */
    private function clear_webp_images()
    {
        $base_path = FCPATH . 'assets/';

        // Find all webp/ and responsive/ directories
        $this->clear_directory($base_path . 'image/webp/');
        $this->clear_directory($base_path . 'image/responsive/');
        $this->clear_directory($base_path . 'system/webp/');
        $this->clear_directory($base_path . 'system/responsive/');

        echo "Cleared WebP and responsive image variants<br>";
    }
}
