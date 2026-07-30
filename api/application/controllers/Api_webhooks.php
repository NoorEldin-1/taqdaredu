<?php
require APPPATH . '/libraries/TokenHandler.php';
require APPPATH . 'libraries/REST_Controller.php';

/**
 * Webhooks API Controller
 * 
 * RESTful API for managing webhooks
 * 
 * @package Academy LMS
 * @version 2.0
 */
class Api_webhooks extends REST_Controller
{
    protected $tokenHandler;
    
    // Supported webhook events
    const EVENTS = [
        'course.enrolled',
        'course.completed',
        'payment.success',
        'payment.failed',
        'user.registered',
        'user.updated',
        'course.created',
        'course.updated',
        'lesson.completed',
        'certificate.issued'
    ];
    
    public function __construct()
    {
        parent::__construct();
        date_default_timezone_set(get_settings('timezone'));
        $this->load->database();
        $this->load->library('session');
        $this->tokenHandler = new TokenHandler();
        
        // CORS Headers — single source of truth in common_helper.php
        apply_api_cors();
    }

    // ========== WEBHOOKS MANAGEMENT ==========
    
    /**
     * GET /api_webhooks/list
     * Get all webhooks for user
     */
    public function list_get()
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        $webhooks = $this->db->order_by('id', 'DESC')
            ->get_where('webhooks', ['user_id' => $user['user_id']])
            ->result_array();
        
        foreach ($webhooks as &$webhook) {
            $webhook['events'] = json_decode($webhook['events'], true) ?: [];
            $webhook['created_at_formatted'] = date('Y-m-d H:i:s', $webhook['created_at']);
        }
        
        return $this->response(['status' => true, 'data' => $webhooks], 200);
    }
    
    /**
     * GET /api_webhooks/webhook/{id}
     * Get single webhook
     */
    public function webhook_get($id = null)
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        if (!$id) {
            return $this->response(['status' => false, 'message' => 'Webhook ID required'], 400);
        }
        
        $webhook = $this->db->get_where('webhooks', ['id' => $id, 'user_id' => $user['user_id']])->row_array();
        if (!$webhook) {
            return $this->response(['status' => false, 'message' => 'Webhook not found'], 404);
        }
        
        $webhook['events'] = json_decode($webhook['events'], true) ?: [];
        $webhook['created_at_formatted'] = date('Y-m-d H:i:s', $webhook['created_at']);
        
        // Get recent deliveries
        $webhook['recent_deliveries'] = $this->db->order_by('id', 'DESC')
            ->limit(10)
            ->get_where('webhook_deliveries', ['webhook_id' => $id])
            ->result_array();
        
        return $this->response(['status' => true, 'data' => $webhook], 200);
    }
    
    /**
     * POST /api_webhooks/create
     * Create a webhook
     */
    public function create_post()
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        $url = $this->input->post('url');
        $events = $this->input->post('events');
        $name = $this->input->post('name') ?: 'Webhook';
        
        if (empty($url)) {
            return $this->response(['status' => false, 'message' => 'URL is required'], 400);
        }
        
        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->response(['status' => false, 'message' => 'Invalid URL format'], 400);
        }

        // Require HTTPS for security
        if (strpos($url, 'https://') !== 0 && ENVIRONMENT !== 'development') {
            return $this->response(['status' => false, 'message' => 'HTTPS URL required'], 400);
        }

        // SSRF guard: reject URLs that resolve to a private/internal network.
        if (ENVIRONMENT !== 'development' && !$this->resolve_safe_webhook_host($url)) {
            return $this->response(['status' => false, 'message' => 'URL is not allowed (points to a private/internal address)'], 400);
        }

        // Validate events
        if (empty($events) || !is_array($events)) {
            $events = self::EVENTS; // Subscribe to all events by default
        } else {
            $events = array_intersect($events, self::EVENTS);
            if (empty($events)) {
                return $this->response(['status' => false, 'message' => 'No valid events provided'], 400);
            }
        }
        
        // Generate secret
        $secret = bin2hex(random_bytes(32));
        
        $webhook_data = [
            'user_id' => $user['user_id'],
            'name' => $name,
            'url' => $url,
            'events' => json_encode($events),
            'secret' => $secret,
            'is_active' => 1,
            'created_at' => time()
        ];
        
        $this->db->insert('webhooks', $webhook_data);
        $webhook_id = $this->db->insert_id();
        
        return $this->response([
            'status' => true,
            'message' => 'Webhook created',
            'data' => [
                'id' => $webhook_id,
                'secret' => $secret,
                'events' => $events
            ]
        ], 201);
    }
    
    /**
     * PUT /api_webhooks/update/{id}
     * Update a webhook
     */
    public function update_put($id = null)
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        if (!$id) {
            return $this->response(['status' => false, 'message' => 'Webhook ID required'], 400);
        }
        
        // Verify ownership
        $webhook = $this->db->get_where('webhooks', ['id' => $id, 'user_id' => $user['user_id']])->row_array();
        if (!$webhook) {
            return $this->response(['status' => false, 'message' => 'Webhook not found'], 404);
        }
        
        $data = $this->put();
        $update_data = [];
        
        if (isset($data['url'])) {
            if (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
                return $this->response(['status' => false, 'message' => 'Invalid URL format'], 400);
            }
            if (strpos($data['url'], 'https://') !== 0 && ENVIRONMENT !== 'development') {
                return $this->response(['status' => false, 'message' => 'HTTPS URL required'], 400);
            }
            if (ENVIRONMENT !== 'development' && !$this->resolve_safe_webhook_host($data['url'])) {
                return $this->response(['status' => false, 'message' => 'URL is not allowed (points to a private/internal address)'], 400);
            }
            $update_data['url'] = $data['url'];
        }
        
        if (isset($data['name'])) {
            $update_data['name'] = $data['name'];
        }
        
        if (isset($data['events']) && is_array($data['events'])) {
            $events = array_intersect($data['events'], self::EVENTS);
            if (!empty($events)) {
                $update_data['events'] = json_encode($events);
            }
        }
        
        if (isset($data['is_active'])) {
            $update_data['is_active'] = $data['is_active'] ? 1 : 0;
        }
        
        if (empty($update_data)) {
            return $this->response(['status' => false, 'message' => 'No valid fields to update'], 400);
        }
        
        $this->db->where('id', $id)->update('webhooks', $update_data);
        
        return $this->response(['status' => true, 'message' => 'Webhook updated'], 200);
    }
    
    /**
     * DELETE /api_webhooks/delete/{id}
     * Delete a webhook
     */
    public function delete_delete($id = null)
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        if (!$id) {
            return $this->response(['status' => false, 'message' => 'Webhook ID required'], 400);
        }
        
        // Verify ownership
        $webhook = $this->db->get_where('webhooks', ['id' => $id, 'user_id' => $user['user_id']])->row_array();
        if (!$webhook) {
            return $this->response(['status' => false, 'message' => 'Webhook not found'], 404);
        }
        
        $this->db->where('id', $id)->delete('webhooks');
        $this->db->where('webhook_id', $id)->delete('webhook_deliveries');
        
        return $this->response(['status' => true, 'message' => 'Webhook deleted'], 200);
    }
    
    /**
     * POST /api_webhooks/test/{id}
     * Test a webhook
     */
    public function test_post($id = null)
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        if (!$id) {
            return $this->response(['status' => false, 'message' => 'Webhook ID required'], 400);
        }
        
        // Verify ownership
        $webhook = $this->db->get_where('webhooks', ['id' => $id, 'user_id' => $user['user_id']])->row_array();
        if (!$webhook) {
            return $this->response(['status' => false, 'message' => 'Webhook not found'], 404);
        }
        
        // Send test payload
        $test_payload = [
            'event' => 'test',
            'timestamp' => time(),
            'data' => [
                'message' => 'This is a test webhook delivery',
                'webhook_id' => $id
            ]
        ];
        
        $result = $this->send_webhook($webhook, $test_payload);
        
        return $this->response([
            'status' => $result['success'],
            'message' => $result['success'] ? 'Test webhook sent successfully' : 'Test webhook failed',
            'data' => [
                'status_code' => $result['status_code'],
                'response' => $result['response']
            ]
        ], $result['success'] ? 200 : 400);
    }
    
    /**
     * POST /api_webhooks/regenerate_secret/{id}
     * Regenerate webhook secret
     */
    public function regenerate_secret_post($id = null)
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        if (!$id) {
            return $this->response(['status' => false, 'message' => 'Webhook ID required'], 400);
        }
        
        // Verify ownership
        $webhook = $this->db->get_where('webhooks', ['id' => $id, 'user_id' => $user['user_id']])->row_array();
        if (!$webhook) {
            return $this->response(['status' => false, 'message' => 'Webhook not found'], 404);
        }
        
        $new_secret = bin2hex(random_bytes(32));
        $this->db->where('id', $id)->update('webhooks', ['secret' => $new_secret]);
        
        return $this->response([
            'status' => true,
            'message' => 'Secret regenerated',
            'data' => ['secret' => $new_secret]
        ], 200);
    }
    
    /**
     * GET /api_webhooks/events
     * Get available webhook events
     */
    public function events_get()
    {
        $events = [];
        foreach (self::EVENTS as $event) {
            $parts = explode('.', $event);
            $events[] = [
                'name' => $event,
                'category' => $parts[0],
                'action' => $parts[1],
                'description' => $this->get_event_description($event)
            ];
        }
        
        return $this->response(['status' => true, 'data' => $events], 200);
    }
    
    /**
     * GET /api_webhooks/deliveries/{webhook_id}
     * Get webhook delivery history
     */
    public function deliveries_get($webhook_id = null)
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        if (!$webhook_id) {
            return $this->response(['status' => false, 'message' => 'Webhook ID required'], 400);
        }
        
        // Verify ownership
        $webhook = $this->db->get_where('webhooks', ['id' => $webhook_id, 'user_id' => $user['user_id']])->row_array();
        if (!$webhook) {
            return $this->response(['status' => false, 'message' => 'Webhook not found'], 404);
        }
        
        $page = $this->input->get('page') ?: 1;
        $limit = $this->input->get('limit') ?: 20;
        $offset = ($page - 1) * $limit;
        
        $total = $this->db->where('webhook_id', $webhook_id)->count_all_results('webhook_deliveries', false);
        
        $deliveries = $this->db->order_by('id', 'DESC')
            ->limit($limit, $offset)
            ->get_where('webhook_deliveries', ['webhook_id' => $webhook_id])
            ->result_array();
        
        foreach ($deliveries as &$delivery) {
            $delivery['request_body'] = json_decode($delivery['request_body'], true);
            $delivery['created_at_formatted'] = date('Y-m-d H:i:s', $delivery['created_at']);
        }
        
        return $this->response([
            'status' => true,
            'data' => $deliveries,
            'pagination' => [
                'total' => $total,
                'page' => (int)$page,
                'limit' => (int)$limit,
                'total_pages' => ceil($total / $limit)
            ]
        ], 200);
    }
    
    /**
     * POST /api_webhooks/retry/{delivery_id}
     * Retry a failed webhook delivery
     */
    public function retry_post($delivery_id = null)
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        if (!$delivery_id) {
            return $this->response(['status' => false, 'message' => 'Delivery ID required'], 400);
        }
        
        $delivery = $this->db->get_where('webhook_deliveries', ['id' => $delivery_id])->row_array();
        if (!$delivery) {
            return $this->response(['status' => false, 'message' => 'Delivery not found'], 404);
        }
        
        // Verify ownership
        $webhook = $this->db->get_where('webhooks', ['id' => $delivery['webhook_id'], 'user_id' => $user['user_id']])->row_array();
        if (!$webhook) {
            return $this->response(['status' => false, 'message' => 'Webhook not found'], 404);
        }
        
        $payload = json_decode($delivery['request_body'], true);
        $result = $this->send_webhook($webhook, $payload);
        
        return $this->response([
            'status' => $result['success'],
            'message' => $result['success'] ? 'Retry successful' : 'Retry failed',
            'data' => [
                'status_code' => $result['status_code']
            ]
        ], $result['success'] ? 200 : 400);
    }

    // ========== HELPER METHODS ==========
    
    /**
     * Send webhook to URL
     */
    private function send_webhook($webhook, $payload)
    {
        $json_payload = json_encode($payload);

        // Generate signature
        $signature = hash_hmac('sha256', $json_payload, $webhook['secret']);

        // SSRF guard: re-validate at send time (the URL may have been stored
        // before this check existed, or DNS may have changed since creation)
        // and pin the TCP connection to the exact IP we validated so a
        // DNS-rebind between validation and connect can't slip through.
        $safe_host = (ENVIRONMENT !== 'development') ? $this->resolve_safe_webhook_host($webhook['url']) : ['host' => parse_url($webhook['url'], PHP_URL_HOST), 'ip' => null];
        if (!$safe_host) {
            $error = 'Blocked: webhook URL resolves to a disallowed network';
            $this->db->insert('webhook_deliveries', [
                'webhook_id' => $webhook['id'],
                'event' => $payload['event'] ?? 'unknown',
                'request_body' => $json_payload,
                'response_code' => 0,
                'response_body' => '',
                'is_success' => 0,
                'error' => $error,
                'created_at' => time()
            ]);
            return ['success' => false, 'status_code' => 0, 'response' => '', 'error' => $error];
        }

        $curl_opts = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json_payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Webhook-Signature: sha256=' . $signature,
                'X-Webhook-Event: ' . ($payload['event'] ?? 'unknown'),
                'X-Webhook-Timestamp: ' . time(),
                'User-Agent: AcademyLMS-Webhook/2.0'
            ]
        ];
        if ($safe_host['ip']) {
            $curl_opts[CURLOPT_RESOLVE] = [$safe_host['host'] . ':443:' . $safe_host['ip']];
        }

        $ch = curl_init($webhook['url']);
        curl_setopt_array($ch, $curl_opts);

        $response = curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Log delivery
        $this->db->insert('webhook_deliveries', [
            'webhook_id' => $webhook['id'],
            'event' => $payload['event'] ?? 'unknown',
            'request_body' => $json_payload,
            'response_code' => $status_code,
            'response_body' => substr($response, 0, 1000),
            'is_success' => ($status_code >= 200 && $status_code < 300) ? 1 : 0,
            'error' => $error,
            'created_at' => time()
        ]);

        return [
            'success' => ($status_code >= 200 && $status_code < 300),
            'status_code' => $status_code,
            'response' => substr((string) $response, 0, 500),
            'error' => $error
        ];
    }

    /**
     * SSRF guard: resolves the webhook URL's host and rejects it if the host
     * itself, or any address it resolves to, is a private/loopback/
     * link-local/reserved address (127.0.0.1, ::1, 169.254.169.254 cloud
     * metadata, 10.x/172.16.x/192.168.x, ...). Also restricts to port 443.
     *
     * @return array{host: string, ip: string}|false Pinned host/IP on success.
     */
    private function resolve_safe_webhook_host($url)
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['host']) || empty($parts['scheme'])) {
            return false;
        }
        if (strtolower($parts['scheme']) !== 'https') {
            return false;
        }
        if (!empty($parts['port']) && (int) $parts['port'] !== 443) {
            return false;
        }

        $host = $parts['host'];

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        } else {
            $ips = [];
            $records = @dns_get_record($host, DNS_A + DNS_AAAA);
            if ($records) {
                foreach ($records as $r) {
                    if (!empty($r['ip'])) $ips[] = $r['ip'];
                    if (!empty($r['ipv6'])) $ips[] = $r['ipv6'];
                }
            }
            if (empty($ips)) {
                $resolved = @gethostbyname($host);
                if ($resolved && $resolved !== $host) {
                    $ips[] = $resolved;
                }
            }
        }

        if (empty($ips)) {
            return false; // could not resolve — fail closed
        }

        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return ['host' => $host, 'ip' => $ips[0]];
    }

    private function get_event_description($event)
    {
        $descriptions = [
            'course.enrolled' => 'Triggered when a user enrolls in a course',
            'course.completed' => 'Triggered when a user completes a course',
            'payment.success' => 'Triggered when a payment is successfully processed',
            'payment.failed' => 'Triggered when a payment fails',
            'user.registered' => 'Triggered when a new user registers',
            'user.updated' => 'Triggered when a user profile is updated',
            'course.created' => 'Triggered when a new course is created',
            'course.updated' => 'Triggered when a course is updated',
            'lesson.completed' => 'Triggered when a user completes a lesson',
            'certificate.issued' => 'Triggered when a certificate is issued'
        ];
        
        return $descriptions[$event] ?? 'No description available';
    }
    
    /**
     * Authenticate user
     */
    private function authenticate()
    {
        $auth_token = $this->input->get('auth_token') ?: $this->input->post('auth_token');
        if (!$auth_token) {
            $headers = $this->input->request_headers();
            $auth_token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
        }
        
        if (!$auth_token) {
            $this->response(['status' => false, 'message' => 'Authentication required'], 401);
            return false;
        }
        
        try {
            $decoded = $this->tokenHandler->DecodeToken($auth_token);
            return $decoded;
        } catch (Exception $e) {
            $this->response(['status' => false, 'message' => 'Invalid or expired token'], 401);
            return false;
        }
    }
}
