<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Asset Minifier Library for CodeIgniter
 * 
 * Pure PHP solution to minify and bundle CSS/JS files
 * No need for Node.js, Gulp, or Webpack
 */
class Asset_minifier
{

    protected $CI;
    protected $css_cache_dir = 'assets/cache/css/';
    protected $js_cache_dir = 'assets/cache/js/';

    public function __construct()
    {
        $this->CI =& get_instance();

        // Create cache directories if they don't exist
        $this->ensure_cache_dirs();
    }

    /**
     * Ensure cache directories exist
     */
    private function ensure_cache_dirs()
    {
        $base_path = FCPATH;

        if (!is_dir($base_path . $this->css_cache_dir)) {
            mkdir($base_path . $this->css_cache_dir, 0755, true);
        }

        if (!is_dir($base_path . $this->js_cache_dir)) {
            mkdir($base_path . $this->js_cache_dir, 0755, true);
        }
    }

    /**
     * Minify and bundle CSS files
     * 
     * @param array $css_files Array of CSS file paths (relative to FCPATH)
     * @param string $bundle_name Name for the bundled file
     * @return string URL to the bundled CSS file
     */
    public function bundle_css($css_files, $bundle_name = 'bundle')
    {
        $base_path = FCPATH;
        $bundle_file = $this->css_cache_dir . $bundle_name . '.min.css';
        $bundle_full_path = $base_path . $bundle_file;

        // Check if bundle needs regeneration
        if ($this->needs_regeneration($css_files, $bundle_full_path)) {
            $bundled_content = '';

            foreach ($css_files as $css_file) {
                $file_path = $base_path . ltrim($css_file, '/');

                if (file_exists($file_path)) {
                    $content = file_get_contents($file_path);

                    // Fix relative URLs in CSS
                    $content = $this->fix_css_urls($content, dirname($css_file));

                    // Minify
                    $content = $this->minify_css($content);

                    $bundled_content .= "/* Source: {$css_file} */\n{$content}\n\n";
                }
            }

            // Write bundled file
            file_put_contents($bundle_full_path, $bundled_content);
        }

        return base_url($bundle_file) . '?v=' . filemtime($bundle_full_path);
    }

    /**
     * Minify and bundle JavaScript files
     * 
     * @param array $js_files Array of JS file paths (relative to FCPATH)
     * @param string $bundle_name Name for the bundled file
     * @return string URL to the bundled JS file
     */
    public function bundle_js($js_files, $bundle_name = 'bundle')
    {
        $base_path = FCPATH;
        $bundle_file = $this->js_cache_dir . $bundle_name . '.min.js';
        $bundle_full_path = $base_path . $bundle_file;

        // Check if bundle needs regeneration
        if ($this->needs_regeneration($js_files, $bundle_full_path)) {
            $bundled_content = '';

            foreach ($js_files as $js_file) {
                $file_path = $base_path . ltrim($js_file, '/');

                if (file_exists($file_path)) {
                    $content = file_get_contents($file_path);

                    // Minify (basic minification, won't break already minified files)
                    $content = $this->minify_js($content);

                    $bundled_content .= "/* Source: {$js_file} */\n{$content};\n\n";
                }
            }

            // Write bundled file
            file_put_contents($bundle_full_path, $bundled_content);
        }

        return base_url($bundle_file) . '?v=' . filemtime($bundle_full_path);
    }

    /**
     * Check if bundle needs regeneration
     * 
     * @param array $source_files Source files
     * @param string $bundle_path Bundle file path
     * @return bool True if needs regeneration
     */
    private function needs_regeneration($source_files, $bundle_path)
    {
        if (!file_exists($bundle_path)) {
            return true;
        }

        $bundle_time = filemtime($bundle_path);
        $base_path = FCPATH;

        foreach ($source_files as $file) {
            $file_path = $base_path . ltrim($file, '/');
            if (file_exists($file_path) && filemtime($file_path) > $bundle_time) {
                return true;
            }
        }

        return false;
    }

    /**
     * Minify CSS content
     * 
     * @param string $css CSS content
     * @return string Minified CSS
     */
    private function minify_css($css)
    {
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);

        // Remove whitespace
        $css = str_replace(["\r\n", "\r", "\n", "\t"], '', $css);
        $css = preg_replace('/\s+/', ' ', $css);

        // Remove spaces around characters
        $css = preg_replace('/\s*([{}|:;,])\s*/', '$1', $css);

        // Remove semicolon before closing brace
        $css = str_replace(';}', '}', $css);

        return trim($css);
    }

    /**
     * Minify JavaScript content (basic)
     * 
     * @param string $js JavaScript content
     * @return string Minified JS
     */
    private function minify_js($js)
    {
        // Only do basic minification to avoid breaking code
        // Remove single-line comments (but preserve URLs)
        $js = preg_replace('~//[^\n]*~', '', $js);

        // Remove multi-line comments
        $js = preg_replace('~/\*.*?\*/~s', '', $js);

        // Remove extra whitespace (but preserve necessary spaces)
        $js = preg_replace('/\s+/', ' ', $js);

        return trim($js);
    }

    /**
     * Fix relative URLs in CSS files
     * 
     * @param string $css CSS content
     * @param string $css_dir Directory of the CSS file
     * @return string CSS with fixed URLs
     */
    private function fix_css_urls($css, $css_dir)
    {
        // Fix url() references
        $css = preg_replace_callback(
            '/url\([\'"]?(?!data:)(?!http)([^\)]+)[\'"]?\)/i',
            function ($matches) use ($css_dir) {
                $url = trim($matches[1], '\'" ');
                $fixed_url = $css_dir . '/' . $url;
                return 'url(' . $fixed_url . ')';
            },
            $css
        );

        return $css;
    }

    /**
     * Clear all cached bundles
     */
    public function clear_cache()
    {
        $base_path = FCPATH;

        // Clear CSS cache
        $css_dir = $base_path . $this->css_cache_dir;
        if (is_dir($css_dir)) {
            $files = glob($css_dir . '*.css');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

        // Clear JS cache
        $js_dir = $base_path . $this->js_cache_dir;
        if (is_dir($js_dir)) {
            $files = glob($js_dir . '*.js');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

        return true;
    }
}
