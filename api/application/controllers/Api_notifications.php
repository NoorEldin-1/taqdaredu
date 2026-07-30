<?php
require APPPATH . '/libraries/TokenHandler.php';
require APPPATH . 'libraries/REST_Controller.php';

/**
 * Notifications API Controller
 * 
 * RESTful API for managing user notifications
 * 
 * @package Academy LMS
 * @version 2.0
 */
class Api_notifications extends REST_Controller
{
    protected $tokenHandler;
    
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

    // ========== NOTIFICATIONS ==========
    
    /**
     * GET /api_notifications/list
     * Get user notifications
     */
    public function list_get()
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        $page = $this->input->get('page') ?: 1;
        $limit = $this->input->get('limit') ?: 20;
        $offset = ($page - 1) * $limit;
        $unread_only = $this->input->get('unread_only') == 1;
        
        $this->db->select('*');
        $this->db->from('notifications');
        $this->db->where('user_id', $user['user_id']);
        
        if ($unread_only) {
            $this->db->where('is_read', 0);
        }
        
        $total = $this->db->count_all_results('', false);
        
        $notifications = $this->db->order_by('created_at', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();
        
        foreach ($notifications as &$notification) {
            $notification['created_at_formatted'] = date('Y-m-d H:i:s', $notification['created_at']);
            $notification['time_ago'] = $this->time_ago($notification['created_at']);
        }
        
        // Count unread
        $unread_count = $this->db->where('user_id', $user['user_id'])->where('is_read', 0)->count_all_results('notifications');
        
        return $this->response([
            'status' => true,
            'data' => $notifications,
            'unread_count' => $unread_count,
            'pagination' => [
                'total' => $total,
                'page' => (int)$page,
                'limit' => (int)$limit,
                'total_pages' => ceil($total / $limit)
            ]
        ], 200);
    }
    
    /**
     * PUT /api_notifications/read/{id}
     * Mark notification as read
     */
    public function read_put($id = null)
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        if (!$id) {
            return $this->response(['status' => false, 'message' => 'Notification ID required'], 400);
        }
        
        // Verify ownership
        $notification = $this->db->get_where('notifications', ['id' => $id, 'user_id' => $user['user_id']])->row_array();
        if (!$notification) {
            return $this->response(['status' => false, 'message' => 'Notification not found'], 404);
        }
        
        $this->db->where('id', $id)->update('notifications', ['is_read' => 1, 'read_at' => time()]);
        
        return $this->response(['status' => true, 'message' => 'Notification marked as read'], 200);
    }
    
    /**
     * PUT /api_notifications/read_all
     * Mark all notifications as read
     */
    public function read_all_put()
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        $this->db->where('user_id', $user['user_id'])
            ->where('is_read', 0)
            ->update('notifications', ['is_read' => 1, 'read_at' => time()]);
        
        $affected = $this->db->affected_rows();
        
        return $this->response([
            'status' => true,
            'message' => $affected . ' notifications marked as read'
        ], 200);
    }
    
    /**
     * DELETE /api_notifications/delete/{id}
     * Delete notification
     */
    public function delete_delete($id = null)
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        if (!$id) {
            return $this->response(['status' => false, 'message' => 'Notification ID required'], 400);
        }
        
        // Verify ownership
        $notification = $this->db->get_where('notifications', ['id' => $id, 'user_id' => $user['user_id']])->row_array();
        if (!$notification) {
            return $this->response(['status' => false, 'message' => 'Notification not found'], 404);
        }
        
        $this->db->where('id', $id)->delete('notifications');
        
        return $this->response(['status' => true, 'message' => 'Notification deleted'], 200);
    }
    
    /**
     * DELETE /api_notifications/delete_all
     * Delete all notifications
     */
    public function delete_all_delete()
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        $this->db->where('user_id', $user['user_id'])->delete('notifications');
        $affected = $this->db->affected_rows();
        
        return $this->response([
            'status' => true,
            'message' => $affected . ' notifications deleted'
        ], 200);
    }
    
    /**
     * GET /api_notifications/settings
     * Get notification settings
     */
    public function settings_get()
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        $user_data = $this->db->select('notification_preferences')->get_where('users', ['id' => $user['user_id']])->row_array();
        
        $default_settings = [
            'email_notifications' => true,
            'push_notifications' => true,
            'course_updates' => true,
            'promotional' => true,
            'new_courses' => true,
            'course_completion' => true,
            'new_messages' => true
        ];
        
        $settings = $default_settings;
        if (!empty($user_data['notification_preferences'])) {
            $saved = json_decode($user_data['notification_preferences'], true);
            if (is_array($saved)) {
                $settings = array_merge($default_settings, $saved);
            }
        }
        
        return $this->response(['status' => true, 'data' => $settings], 200);
    }
    
    /**
     * PUT /api_notifications/settings
     * Update notification settings
     */
    public function settings_put()
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        $settings = $this->put();
        
        $this->db->where('id', $user['user_id'])->update('users', [
            'notification_preferences' => json_encode($settings)
        ]);
        
        return $this->response(['status' => true, 'message' => 'Settings updated'], 200);
    }
    
    /**
     * POST /api_notifications/push_token
     * Register push notification token (FCM)
     */
    public function push_token_post()
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        $token = $this->input->post('token');
        $device_type = $this->input->post('device_type') ?: 'android'; // android, ios, web
        
        if (empty($token)) {
            return $this->response(['status' => false, 'message' => 'Token required'], 400);
        }
        
        // Check if token exists
        $exists = $this->db->get_where('push_tokens', ['token' => $token])->num_rows();
        
        if ($exists > 0) {
            // Update existing
            $this->db->where('token', $token)->update('push_tokens', [
                'user_id' => $user['user_id'],
                'device_type' => $device_type,
                'updated_at' => time()
            ]);
        } else {
            // Insert new
            $this->db->insert('push_tokens', [
                'user_id' => $user['user_id'],
                'token' => $token,
                'device_type' => $device_type,
                'created_at' => time(),
                'updated_at' => time()
            ]);
        }
        
        return $this->response(['status' => true, 'message' => 'Push token registered'], 200);
    }
    
    /**
     * DELETE /api_notifications/push_token
     * Unregister push notification token
     */
    public function push_token_delete()
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        $token = $this->input->get('token');
        
        if (empty($token)) {
            return $this->response(['status' => false, 'message' => 'Token required'], 400);
        }
        
        $this->db->where('token', $token)->where('user_id', $user['user_id'])->delete('push_tokens');
        
        return $this->response(['status' => true, 'message' => 'Push token removed'], 200);
    }
    
    /**
     * GET /api_notifications/unread_count
     * Get unread notifications count
     */
    public function unread_count_get()
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        $count = $this->db->where('user_id', $user['user_id'])->where('is_read', 0)->count_all_results('notifications');
        
        return $this->response(['status' => true, 'count' => $count], 200);
    }

    // ========== HELPER METHODS ==========
    
    /**
     * Convert timestamp to "time ago" format
     */
    private function time_ago($timestamp)
    {
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return 'Just now';
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 2592000) {
            $weeks = floor($diff / 604800);
            return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
        } else {
            return date('M d, Y', $timestamp);
        }
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
