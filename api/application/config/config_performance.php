<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * CodeIgniter Performance Configuration
 * 
 * Add this to your main config.php or include it:
 * include(APPPATH . 'config/config_performance.php');
 */

// ====================================
// OUTPUT CACHING
// ====================================

/*
| Enable/Disable Output Caching
|
| For static pages (landing pages, about, contact), enable output caching
| to dramatically reduce server response time
|
| Usage in controllers:
| $this->output->cache(60); // Cache for 60 minutes
*/

// Cache directory (ensure it's writable)
// Default: application/cache/

// ====================================
// COMPRESSION
// ====================================

/*
| Enable Output Compression
|
| If your server doesn't support mod_deflate, enable this
| Note: .htaccess compression is preferred for better performance
*/
$config['compress_output'] = FALSE; // Set to TRUE if mod_deflate is not available

// ====================================
// AUTOLOAD OPTIMIZATION
// ====================================

/*
| In application/config/autoload.php, only autoload what you need:
| 
| - Remove unnecessary helpers from autoload
| - Load libraries only when needed
| - Use lazy loading for models
|
| Example of optimized autoload.php:
| 
| $autoload['helper'] = ['url', 'file']; // Only essential helpers
| $autoload['libraries'] = []; // Load on-demand instead
*/

// ====================================
// DATABASE OPTIMIZATION
// ====================================

/*
| Database Caching
|
| Enable query caching in database.php:
| $db['default']['cache_on'] = TRUE;
| $db['default']['cachedir'] = APPPATH . 'cache/db/';
|
| Usage in models:
| $this->db->cache_on(); // Enable for specific query
| $query = $this->db->get('table_name');
| $this->db->cache_off(); // Disable after
*/

// ====================================
// SESSION OPTIMIZATION
// ====================================

/*
| Use database or redis for sessions (not files)
| In config.php:
| 
| $config['sess_driver'] = 'database';
| $config['sess_save_path'] = 'ci_sessions';
|
| Or for production (if available):
| $config['sess_driver'] = 'redis';
| $config['sess_save_path'] = 'tcp://localhost:6379';
*/

// ====================================
// REMOVE index.php from URLs
// ====================================

/*
| In config.php:
| $config['index_page'] = ''; // Remove index.php
|
| Use .htaccess rewrite rules (already included in .htaccess)
*/

// ====================================
// LOG THRESHOLD
// ====================================

/*
| In production, set log threshold to 0 or 1
| In config.php:
| 
| $config['log_threshold'] = 0; // Disable logging in production
*/

// ====================================
// USAGE EXAMPLES
// ====================================

/*
| Example Controller with Output Caching:
|
| class Home extends CI_Controller {
|     public function index() {
|         // Cache this page for 1 hour (static content)
|         $this->output->cache(60);
|         
|         $this->load->view('home');
|     }
| }
|
| Example Model with Query Caching:
|
| class Product_model extends CI_Model {
|     public function get_featured_products() {
|         $this->db->cache_on();
|         $query = $this->db->get('products');
|         $this->db->cache_off();
|         
|         return $query->result();
|     }
| }
*/

// ====================================
// CLEAR CACHE CONTROLLER
// ====================================

/*
| Create a controller to clear all caches:
|
| class Clear_cache extends CI_Controller {
|     public function index() {
|         // Clear output cache
|         $this->output->delete_cache();
|         
|         // Clear asset minifier cache
|         $this->load->library('asset_minifier');
|         $this->asset_minifier->clear_cache();
|         
|         // Clear database cache
|         $this->db->cache_delete_all();
|         
|         echo "All caches cleared!";
|     }
| }
|
| Access via: http://localhost/myco_uk/clear_cache
*/
