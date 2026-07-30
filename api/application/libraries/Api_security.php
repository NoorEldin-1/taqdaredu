<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * API Security Library
 * 
 * Handles API security features:
 * - Rate Limiting
 * - API Key validation
 * - Request logging
 * - IP blocking
 * 
 * @package Academy LMS
 * @version 2.0
 */
class Api_security
{
    protected $CI;
    protected $config;
    
    // Default rate limit settings
    protected $rate_limit = 100;        // requests per window
    protected $rate_window = 60;        // window in seconds
    
    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->database();
    }
    
    // ========== RATE LIMITING ==========
    
    /**
     * Check if request is within rate limit
     * 
     * @param string $key Identifier (user_id, API key, or IP)
     * @param string $endpoint Optional endpoint-specific limiting
     * @param int $limit Override default limit
     * @return array ['allowed' => bool, 'remaining' => int, 'reset' => timestamp]
     */
    public function check_rate_limit($key, $endpoint = null, $limit = null)
    {
        $limit = $limit ?: $this->rate_limit;
        $now = time();
        $window_start = $now - $this->rate_window;
        
        // Get current count
        $this->CI->db->select('*');
        $this->CI->db->where('key', $key);
        if ($endpoint) {
            $this->CI->db->where('endpoint', $endpoint);
        } else {
            $this->CI->db->where('endpoint IS NULL', null, false);
        }
        $record = $this->CI->db->get('api_rate_limits')->row_array();
        
        if (!$record || $record['window_start'] < $window_start) {
            // New window or expired - reset counter
            if ($record) {
                $this->CI->db->where('id', $record['id'])->update('api_rate_limits', [
                    'requests' => 1,
                    'window_start' => $now
                ]);
            } else {
                $this->CI->db->insert('api_rate_limits', [
                    'key' => $key,
                    'endpoint' => $endpoint,
                    'requests' => 1,
                    'window_start' => $now
                ]);
            }
            
            return [
                'allowed' => true,
                'remaining' => $limit - 1,
                'reset' => $now + $this->rate_window
            ];
        }
        
        // Within window - check and increment
        if ($record['requests'] >= $limit) {
            return [
                'allowed' => false,
                'remaining' => 0,
                'reset' => $record['window_start'] + $this->rate_window
            ];
        }
        
        // Increment counter
        $this->CI->db->where('id', $record['id'])->update('api_rate_limits', [
            'requests' => $record['requests'] + 1
        ]);
        
        return [
            'allowed' => true,
            'remaining' => $limit - $record['requests'] - 1,
            'reset' => $record['window_start'] + $this->rate_window
        ];
    }
    
    /**
     * Set rate limit headers on response
     */
    public function set_rate_limit_headers($limit_info)
    {
        header('X-RateLimit-Limit: ' . $this->rate_limit);
        header('X-RateLimit-Remaining: ' . $limit_info['remaining']);
        header('X-RateLimit-Reset: ' . $limit_info['reset']);
    }
    
    // ========== API KEY VALIDATION ==========
    
    /**
     * Validate API key
     * 
     * @param string $api_key The API key to validate
     * @return array|false Returns API key data or false if invalid
     */
    public function validate_api_key($api_key)
    {
        if (empty($api_key)) {
            return false;
        }
        
        $key_data = $this->CI->db->get_where('api_keys', [
            'key' => $api_key,
            'is_active' => 1
        ])->row_array();
        
        if (!$key_data) {
            return false;
        }
        
        // Check expiration
        if ($key_data['expires_at'] && $key_data['expires_at'] < time()) {
            return false;
        }
        
        // Update last used
        $this->CI->db->where('id', $key_data['id'])->update('api_keys', [
            'last_used_at' => time()
        ]);
        
        return $key_data;
    }
    
    /**
     * Check if API key has permission for endpoint
     */
    public function check_permission($key_data, $endpoint)
    {
        $permissions = json_decode($key_data['permissions'] ?? '[]', true);
        
        // Wildcard - all permissions
        if (in_array('*', $permissions)) {
            return true;
        }
        
        // Check specific endpoint
        if (in_array($endpoint, $permissions)) {
            return true;
        }
        
        // Check endpoint pattern (e.g., "courses/*").
        // Use '#' as the regex delimiter and preg_quote() so that slashes and
        // other metacharacters in the permission string are treated literally;
        // only the wildcard '*' is expanded to '.*'. (Previously '/'-delimited
        // patterns such as "courses/*" produced an invalid regex and errored.)
        foreach ($permissions as $perm) {
            if (strpos($perm, '*') !== false) {
                $pattern = str_replace('\*', '.*', preg_quote($perm, '#'));
                if (preg_match('#^' . $pattern . '$#', $endpoint)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Generate new API key
     */
    public function generate_api_key($user_id, $name = 'API Key', $permissions = ['*'], $expires_in_days = null)
    {
        $key = 'ak_' . bin2hex(random_bytes(24));
        $secret = bin2hex(random_bytes(32));
        
        $data = [
            'user_id' => $user_id,
            'name' => $name,
            'key' => $key,
            'secret' => password_hash($secret, PASSWORD_DEFAULT),
            'permissions' => json_encode($permissions),
            'rate_limit' => $this->rate_limit,
            'is_active' => 1,
            'created_at' => time()
        ];
        
        if ($expires_in_days) {
            $data['expires_at'] = time() + ($expires_in_days * 86400);
        }
        
        $this->CI->db->insert('api_keys', $data);
        
        return [
            'id' => $this->CI->db->insert_id(),
            'key' => $key,
            'secret' => $secret // Only returned once at creation
        ];
    }
    
    // ========== REQUEST LOGGING ==========
    
    /**
     * Log API request
     */
    public function log_request($data = [])
    {
        $log_data = [
            'api_key_id' => $data['api_key_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'endpoint' => $data['endpoint'] ?? $this->CI->uri->uri_string(),
            'method' => $data['method'] ?? $_SERVER['REQUEST_METHOD'],
            'request_body' => $data['request_body'] ?? null,
            'request_headers' => $data['request_headers'] ?? null,
            'response_code' => $data['response_code'] ?? null,
            'response_time' => $data['response_time'] ?? null,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'created_at' => time()
        ];
        
        $this->CI->db->insert('api_logs', $log_data);
        
        return $this->CI->db->insert_id();
    }
    
    /**
     * Update log with response data
     */
    public function update_log($log_id, $response_code, $response_time)
    {
        $this->CI->db->where('id', $log_id)->update('api_logs', [
            'response_code' => $response_code,
            'response_time' => $response_time
        ]);
    }
    
    // ========== IP BLOCKING ==========
    
    /**
     * Check if IP is blocked
     */
    public function is_ip_blocked($ip = null)
    {
        $ip = $ip ?: $this->get_client_ip();
        
        // Check settings for blocked IPs
        $blocked_ips = get_settings('blocked_ips');
        if (!empty($blocked_ips)) {
            $blocked_list = explode(',', $blocked_ips);
            $blocked_list = array_map('trim', $blocked_list);
            
            if (in_array($ip, $blocked_list)) {
                return true;
            }
        }
        
        // Check for suspicious activity (too many failed requests)
        $one_hour_ago = time() - 3600;
        $failed_requests = $this->CI->db
            ->where('ip_address', $ip)
            ->where('response_code >=', 400)
            ->where('created_at >=', $one_hour_ago)
            ->count_all_results('api_logs');
        
        // Block if more than 100 failed requests in an hour
        if ($failed_requests > 100) {
            return true;
        }
        
        return false;
    }
    
    // ========== SIGNATURE VERIFICATION ==========
    
    /**
     * Verify request signature (for webhooks and secure requests)
     */
    public function verify_signature($payload, $signature, $secret)
    {
        $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, $signature);
    }
    
    /**
     * Generate request signature
     */
    public function generate_signature($payload, $secret)
    {
        return 'sha256=' . hash_hmac('sha256', $payload, $secret);
    }
    
    // ========== HELPER METHODS ==========
    
    /**
     * Get client IP address
     */
    public function get_client_ip()
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_REAL_IP',            // Nginx proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Clean old rate limit records (call periodically)
     */
    public function cleanup_rate_limits()
    {
        $cutoff = time() - $this->rate_window - 3600; // 1 hour buffer
        $this->CI->db->where('window_start <', $cutoff)->delete('api_rate_limits');
    }
    
    /**
     * Clean old API logs (call daily)
     */
    public function cleanup_logs($days = 30)
    {
        $cutoff = time() - ($days * 86400);
        $this->CI->db->where('created_at <', $cutoff)->delete('api_logs');
    }
    
    /**
     * Get API usage statistics
     */
    public function get_usage_stats($user_id = null, $days = 7)
    {
        $cutoff = time() - ($days * 86400);
        
        $this->CI->db->select('DATE(FROM_UNIXTIME(created_at)) as date, COUNT(*) as requests, AVG(response_time) as avg_response_time');
        $this->CI->db->from('api_logs');
        $this->CI->db->where('created_at >=', $cutoff);
        
        if ($user_id) {
            $this->CI->db->where('user_id', $user_id);
        }
        
        $this->CI->db->group_by('DATE(FROM_UNIXTIME(created_at))');
        $this->CI->db->order_by('date', 'ASC');
        
        return $this->CI->db->get()->result_array();
    }
}
