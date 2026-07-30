<?php
require APPPATH . '/libraries/TokenHandler.php';
require APPPATH . 'libraries/REST_Controller.php';

/**
 * Messages API Controller
 * 
 * RESTful API for user messaging system
 * 
 * @package Academy LMS
 * @version 2.0
 */
class Api_messages extends REST_Controller
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

    // ========== MESSAGE THREADS ==========
    
    /**
     * GET /api_messages/threads
     * Get all message threads for user
     */
    public function threads_get()
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        $user_id = $user['user_id'];
        
        // Get unique threads
        $this->db->select('DISTINCT(thread_code)');
        $this->db->from('message');
        $this->db->group_start();
        $this->db->where('sender', $user_id);
        $this->db->or_where('receiver', $user_id);
        $this->db->group_end();
        $this->db->order_by('message_id', 'DESC');
        
        $thread_codes = $this->db->get()->result_array();
        
        $threads = [];
        foreach ($thread_codes as $tc) {
            $thread = $this->get_thread_info($tc['thread_code'], $user_id);
            if ($thread) {
                $threads[] = $thread;
            }
        }
        
        return $this->response(['status' => true, 'data' => $threads], 200);
    }
    
    /**
     * GET /api_messages/thread/{thread_code}
     * Get messages in a thread
     */
    public function thread_get($thread_code = null)
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        if (!$thread_code) {
            return $this->response(['status' => false, 'message' => 'Thread code required'], 400);
        }
        
        $user_id = $user['user_id'];
        
        // Verify user is part of this thread
        $is_participant = $this->db->group_start()
            ->where('sender', $user_id)
            ->or_where('receiver', $user_id)
            ->group_end()
            ->where('thread_code', $thread_code)
            ->count_all_results('message');
        
        if ($is_participant == 0) {
            return $this->response(['status' => false, 'message' => 'Thread not found'], 404);
        }
        
        $page = $this->input->get('page') ?: 1;
        $limit = $this->input->get('limit') ?: 50;
        $offset = ($page - 1) * $limit;
        
        // Get messages
        $this->db->select('m.*, 
            s.first_name as sender_first_name, s.last_name as sender_last_name, s.image as sender_image,
            r.first_name as receiver_first_name, r.last_name as receiver_last_name, r.image as receiver_image');
        $this->db->from('message m');
        $this->db->join('users s', 's.id = m.sender', 'left');
        $this->db->join('users r', 'r.id = m.receiver', 'left');
        $this->db->where('m.thread_code', $thread_code);
        
        $total = $this->db->count_all_results('', false);
        
        $messages = $this->db->order_by('m.message_id', 'ASC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();
        
        foreach ($messages as &$msg) {
            $msg['sender_name'] = $msg['sender_first_name'] . ' ' . $msg['sender_last_name'];
            $msg['receiver_name'] = $msg['receiver_first_name'] . ' ' . $msg['receiver_last_name'];
            $msg['is_mine'] = ($msg['sender'] == $user_id);
            $msg['timestamp_formatted'] = date('Y-m-d H:i:s', $msg['timestamp']);
            $msg['time_ago'] = $this->time_ago($msg['timestamp']);
        }
        
        // Mark messages as read
        $this->db->where('thread_code', $thread_code)
            ->where('receiver', $user_id)
            ->where('read_status', 0)
            ->update('message', ['read_status' => 1]);
        
        return $this->response([
            'status' => true,
            'data' => $messages,
            'pagination' => [
                'total' => $total,
                'page' => (int)$page,
                'limit' => (int)$limit,
                'total_pages' => ceil($total / $limit)
            ]
        ], 200);
    }
    
    /**
     * POST /api_messages/send
     * Send a message
     */
    public function send_post()
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        $receiver_id = $this->input->post('receiver_id');
        $message_text = $this->input->post('message');
        $thread_code = $this->input->post('thread_code');
        
        if (empty($receiver_id) || empty($message_text)) {
            return $this->response(['status' => false, 'message' => 'Receiver and message are required'], 400);
        }
        
        // Verify receiver exists
        $receiver = $this->db->get_where('users', ['id' => $receiver_id, 'status' => 1])->row_array();
        if (!$receiver) {
            return $this->response(['status' => false, 'message' => 'Receiver not found'], 404);
        }
        
        // Generate thread code if not provided
        if (empty($thread_code)) {
            // Check for existing thread between users
            $existing = $this->db->select('thread_code')
                ->group_start()
                ->group_start()
                ->where('sender', $user['user_id'])
                ->where('receiver', $receiver_id)
                ->group_end()
                ->or_group_start()
                ->where('sender', $receiver_id)
                ->where('receiver', $user['user_id'])
                ->group_end()
                ->group_end()
                ->get('message')
                ->row_array();
            
            $thread_code = $existing ? $existing['thread_code'] : $this->generate_thread_code();
        }
        
        // Insert message
        $message_data = [
            'thread_code' => $thread_code,
            'sender' => $user['user_id'],
            'receiver' => $receiver_id,
            'message' => $message_text,
            'timestamp' => time(),
            'read_status' => 0
        ];
        
        $this->db->insert('message', $message_data);
        $message_id = $this->db->insert_id();
        
        // Create notification for receiver
        $this->create_message_notification($receiver_id, $user['user_id'], $message_text);
        
        return $this->response([
            'status' => true,
            'message' => 'Message sent',
            'data' => [
                'message_id' => $message_id,
                'thread_code' => $thread_code
            ]
        ], 201);
    }
    
    /**
     * DELETE /api_messages/delete/{message_id}
     * Delete a message
     */
    public function delete_delete($message_id = null)
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        if (!$message_id) {
            return $this->response(['status' => false, 'message' => 'Message ID required'], 400);
        }
        
        // Verify ownership
        $message = $this->db->get_where('message', ['message_id' => $message_id, 'sender' => $user['user_id']])->row_array();
        if (!$message) {
            return $this->response(['status' => false, 'message' => 'Message not found or not yours'], 404);
        }
        
        $this->db->where('message_id', $message_id)->delete('message');
        
        return $this->response(['status' => true, 'message' => 'Message deleted'], 200);
    }
    
    /**
     * DELETE /api_messages/thread/{thread_code}
     * Delete entire thread
     */
    public function thread_delete($thread_code = null)
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        if (!$thread_code) {
            return $this->response(['status' => false, 'message' => 'Thread code required'], 400);
        }
        
        // Verify user is part of thread
        $is_participant = $this->db->group_start()
            ->where('sender', $user['user_id'])
            ->or_where('receiver', $user['user_id'])
            ->group_end()
            ->where('thread_code', $thread_code)
            ->count_all_results('message');
        
        if ($is_participant == 0) {
            return $this->response(['status' => false, 'message' => 'Thread not found'], 404);
        }
        
        // Delete all messages in thread for this user
        $this->db->where('thread_code', $thread_code)
            ->where('sender', $user['user_id'])
            ->delete('message');
        
        return $this->response(['status' => true, 'message' => 'Thread messages deleted'], 200);
    }
    
    /**
     * GET /api_messages/unread_count
     * Get unread messages count
     */
    public function unread_count_get()
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        $count = $this->db->where('receiver', $user['user_id'])
            ->where('read_status', 0)
            ->count_all_results('message');
        
        return $this->response(['status' => true, 'count' => $count], 200);
    }
    
    /**
     * POST /api_messages/start_thread
     * Start a new thread with a user
     */
    public function start_thread_post()
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        $receiver_id = $this->input->post('receiver_id');
        
        if (empty($receiver_id)) {
            return $this->response(['status' => false, 'message' => 'Receiver ID required'], 400);
        }
        
        // Verify receiver exists
        $receiver = $this->db->get_where('users', ['id' => $receiver_id, 'status' => 1])->row_array();
        if (!$receiver) {
            return $this->response(['status' => false, 'message' => 'User not found'], 404);
        }
        
        // Check for existing thread
        $existing = $this->db->select('thread_code')
            ->group_start()
            ->group_start()
            ->where('sender', $user['user_id'])
            ->where('receiver', $receiver_id)
            ->group_end()
            ->or_group_start()
            ->where('sender', $receiver_id)
            ->where('receiver', $user['user_id'])
            ->group_end()
            ->group_end()
            ->get('message')
            ->row_array();
        
        if ($existing) {
            return $this->response([
                'status' => true,
                'message' => 'Existing thread found',
                'data' => [
                    'thread_code' => $existing['thread_code'],
                    'is_new' => false
                ]
            ], 200);
        }
        
        // Generate new thread code
        $thread_code = $this->generate_thread_code();
        
        return $this->response([
            'status' => true,
            'message' => 'New thread created',
            'data' => [
                'thread_code' => $thread_code,
                'is_new' => true,
                'receiver' => [
                    'id' => $receiver['id'],
                    'name' => $receiver['first_name'] . ' ' . $receiver['last_name'],
                    'image' => $this->get_user_image($receiver['id'])
                ]
            ]
        ], 201);
    }
    
    /**
     * PUT /api_messages/mark_read
     * Mark messages as read
     */
    public function mark_read_put()
    {
        $user = $this->authenticate();
        if (!$user) return;
        
        $data = $this->put();
        $thread_code = $data['thread_code'] ?? null;
        $message_ids = $data['message_ids'] ?? null;
        
        if ($thread_code) {
            $this->db->where('thread_code', $thread_code)
                ->where('receiver', $user['user_id'])
                ->where('read_status', 0)
                ->update('message', ['read_status' => 1]);
        } elseif ($message_ids && is_array($message_ids)) {
            $this->db->where_in('message_id', $message_ids)
                ->where('receiver', $user['user_id'])
                ->where('read_status', 0)
                ->update('message', ['read_status' => 1]);
        } else {
            return $this->response(['status' => false, 'message' => 'Thread code or message IDs required'], 400);
        }
        
        return $this->response(['status' => true, 'message' => 'Messages marked as read'], 200);
    }

    // ========== HELPER METHODS ==========
    
    private function get_thread_info($thread_code, $user_id)
    {
        // Get last message
        $last_message = $this->db->where('thread_code', $thread_code)
            ->order_by('message_id', 'DESC')
            ->limit(1)
            ->get('message')
            ->row_array();
        
        if (!$last_message) return null;
        
        // Get the other user
        $other_user_id = ($last_message['sender'] == $user_id) ? $last_message['receiver'] : $last_message['sender'];
        $other_user = $this->db->select('id, first_name, last_name, image')
            ->get_where('users', ['id' => $other_user_id])
            ->row_array();
        
        // Count unread
        $unread = $this->db->where('thread_code', $thread_code)
            ->where('receiver', $user_id)
            ->where('read_status', 0)
            ->count_all_results('message');
        
        return [
            'thread_code' => $thread_code,
            'user' => [
                'id' => $other_user['id'],
                'name' => $other_user['first_name'] . ' ' . $other_user['last_name'],
                'image' => $this->get_user_image($other_user['id'])
            ],
            'last_message' => [
                'message' => $last_message['message'],
                'timestamp' => $last_message['timestamp'],
                'timestamp_formatted' => date('Y-m-d H:i', $last_message['timestamp']),
                'time_ago' => $this->time_ago($last_message['timestamp']),
                'is_mine' => ($last_message['sender'] == $user_id)
            ],
            'unread_count' => $unread
        ];
    }
    
    private function generate_thread_code()
    {
        return 'thread_' . uniqid() . '_' . time();
    }
    
    private function get_user_image($user_id)
    {
        $user = $this->db->select('image')->get_where('users', ['id' => $user_id])->row_array();
        if ($user && !empty($user['image']) && file_exists('uploads/user_image/' . $user['image'])) {
            return '/uploads/user_image/' . $user['image'];
        }
        return '/uploads/user_image/default.png';
    }
    
    private function create_message_notification($user_id, $sender_id, $message)
    {
        $sender = $this->db->select('first_name, last_name')->get_where('users', ['id' => $sender_id])->row_array();
        $sender_name = $sender ? $sender['first_name'] . ' ' . $sender['last_name'] : 'Someone';
        
        // Truncate message for notification
        $short_message = strlen($message) > 50 ? substr($message, 0, 50) . '...' : $message;
        
        $this->db->insert('notifications', [
            'user_id' => $user_id,
            'title' => 'New message from ' . $sender_name,
            'message' => $short_message,
            'type' => 'message',
            'reference_id' => $sender_id,
            'is_read' => 0,
            'created_at' => time()
        ]);
    }
    
    private function time_ago($timestamp)
    {
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return 'Just now';
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . 'm ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . 'h ago';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . 'd ago';
        } else {
            return date('M d', $timestamp);
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
