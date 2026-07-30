<?php
require APPPATH . '/libraries/TokenHandler.php';
require APPPATH . 'libraries/REST_Controller.php';

/**
 * Payment API Controller
 * 
 * RESTful API for payment processing, invoices, and subscriptions
 * 
 * @package Academy LMS
 * @version 2.0
 */
class Api_payment extends REST_Controller
{
    protected $tokenHandler;

    public function __construct()
    {
        parent::__construct();
        date_default_timezone_set(get_settings('timezone'));
        $this->load->database();
        $this->load->library('session');
        $this->load->model('crud_model');
        $this->tokenHandler = new TokenHandler();

        // CORS Headers — single source of truth in common_helper.php
        apply_api_cors();
    }

    // ========== PAYMENT METHODS ==========

    /**
     * GET /api_payment/methods
     * Get available payment methods
     */
    public function methods_get()
    {
        $methods = [];

        // Check which payment methods are enabled
        // PayPal (stored as JSON in 'paypal' key)
        $paypal = json_decode(get_settings('paypal'), true);
        if (is_array($paypal) && isset($paypal[0]) && $paypal[0]['active'] == '1') {
            $methods[] = [
                'id' => 'paypal',
                'name' => 'PayPal',
                'description' => 'Pay securely with PayPal'
            ];
        }

        // TAP and PayPal are the only gateways checkout_post() actually
        // accepts — razorpay/paytm/flutterwave/paystack/stripe were listed
        // here but rejected at checkout, so they were removed from this list.

        // Tap Payments
        $tap_gateway = $this->db->get_where('payment_gateways', ['identifier' => 'tap', 'status' => 1])->row_array();
        if ($tap_gateway) {
            $methods[] = [
                'id' => 'tap',
                'name' => 'Tap Payments',
                'description' => 'Pay with cards, Apple Pay, mada, and more'
            ];
        }

        // Offline payment
        if (get_settings('offline_payment') == 1) {
            $methods[] = [
                'id' => 'offline',
                'name' => 'Offline Payment',
                'icon' => base_url('assets/frontend/default/img/payment/offline.png'),
                'description' => 'Pay via bank transfer',
                'instructions' => get_settings('offline_payment_instruction')
            ];
        }

        return $this->response([
            'status' => true,
            'data' => $methods
        ], 200);
    }

    /**
     * GET /api_payment/history
     * Get payment history
     */
    public function history_get()
    {
        $user = $this->authenticate();
        if (!$user) return;

        $page = $this->input->get('page') ?: 1;
        $limit = $this->input->get('limit') ?: 20;
        $offset = ($page - 1) * $limit;

        $this->db->select('p.*, c.title as course_title, c.thumbnail');
        $this->db->from('payment p');
        $this->db->join('course c', 'c.id = p.course_id', 'left');
        $this->db->where('p.user_id', $user['user_id']);

        $total = $this->db->count_all_results('', false);

        $payments = $this->db->order_by('p.date_added', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->result_array();

        foreach ($payments as &$payment) {
            $payment['date_formatted'] = date('Y-m-d H:i', $payment['date_added']);
            $payment['thumbnail_url'] = $this->get_course_thumbnail($payment['course_id']);
        }

        return $this->response([
            'status' => true,
            'data' => $payments,
            'pagination' => [
                'total' => $total,
                'page' => (int)$page,
                'limit' => (int)$limit,
                'total_pages' => ceil($total / $limit)
            ]
        ], 200);
    }

    /**
     * GET /api_payment/invoice/{payment_id}
     * Get payment invoice
     */
    public function invoice_get($payment_id = null)
    {
        $user = $this->authenticate();
        if (!$user) return;

        if (!$payment_id) {
            return $this->response(['status' => false, 'message' => 'Payment ID required'], 400);
        }

        $payment = $this->db->select('p.*, c.title as course_title, c.thumbnail, u.first_name, u.last_name, u.email')
            ->from('payment p')
            ->join('course c', 'c.id = p.course_id', 'left')
            ->join('users u', 'u.id = p.user_id', 'left')
            ->where('p.id', $payment_id)
            ->where('p.user_id', $user['user_id'])
            ->get()
            ->row_array();

        if (!$payment) {
            return $this->response(['status' => false, 'message' => 'Invoice not found'], 404);
        }

        $invoice = [
            'invoice_number' => 'INV-' . str_pad($payment['id'], 8, '0', STR_PAD_LEFT),
            'date' => date('Y-m-d', $payment['date_added']),
            'customer' => [
                'name' => $payment['first_name'] . ' ' . $payment['last_name'],
                'email' => $payment['email']
            ],
            'items' => [
                [
                    'name' => $payment['course_title'],
                    'quantity' => 1,
                    'price' => $payment['amount']
                ]
            ],
            'subtotal' => $payment['amount'],
            'tax' => 0,
            'total' => $payment['amount'],
            'currency' => get_settings('system_currency'),
            'payment_method' => $payment['payment_type'] ?? 'Online',
            'transaction_id' => $payment['transaction_id'] ?? null,
            'company' => [
                'name' => get_settings('system_name'),
                'email' => get_settings('system_email'),
                'address' => get_settings('address')
            ]
        ];

        return $this->response(['status' => true, 'data' => $invoice], 200);
    }

    /**
     * POST /api_payment/apply_coupon
     * Preview a coupon's discount against the user's current cart total (the
     * same total checkout_post() charges, via the shared get_cart_total()).
     */
    public function apply_coupon_post()
    {
        $user = $this->authenticate();
        if (!$user) return;

        $coupon_code = trim((string) $this->input->post('coupon_code'));
        if ($coupon_code === '') {
            return $this->response(['status' => false, 'message' => 'Coupon code required'], 400);
        }

        $cart = $this->get_cart_total($user['user_id']);
        if ($cart['total'] <= 0) {
            return $this->response(['status' => false, 'message' => 'Your cart total is already $0'], 400);
        }

        $result = $this->apply_coupon($coupon_code, $cart['line_items'], $user['user_id']);
        if (!$result['valid']) {
            return $this->response(['status' => false, 'message' => $result['message']], 400);
        }

        return $this->response([
            'status' => true,
            'message' => 'Coupon applied successfully',
            'data' => [
                'original_total' => $cart['total'],
                'discount' => $result['discount'],
                'discount_percentage' => $result['percentage'],
                'final_total' => $result['final_price']
            ]
        ], 200);
    }

    /**
     * POST /api_payment/offline
     * Submit offline payment
     */
    public function offline_post()
    {
        $user = $this->authenticate();
        if (!$user) return;

        $course_id = $this->input->post('course_id');
        $document = $_FILES['document'] ?? null;

        if (empty($course_id)) {
            return $this->response(['status' => false, 'message' => 'Course ID required'], 400);
        }

        // Check course
        $course = $this->db->get_where('course', ['id' => $course_id, 'status' => 'active'])->row_array();
        if (!$course) {
            return $this->response(['status' => false, 'message' => 'Course not found'], 404);
        }

        // Check if already enrolled
        $already = $this->db->get_where('enrol', [
            'user_id' => $user['user_id'],
            'course_id' => $course_id
        ])->row_array();

        if ($already) {
            return $this->response(['status' => false, 'message' => 'Already enrolled'], 409);
        }

        $price = $course['discounted_price'] > 0 ? $course['discounted_price'] : $course['price'];

        // Handle document upload if provided (C-02 fix: strict allowlist + MIME check + random name)
        $document_path = null;
        if ($document && $document['error'] == 0) {
            // 1. Size limit: 5 MB
            $max_size = 5 * 1024 * 1024;
            if ($document['size'] > $max_size) {
                return $this->response(['status' => false, 'message' => 'File too large. Maximum 5 MB allowed.'], 400);
            }

            // 2. Extension allowlist (only images + PDF — no PHP, no script files)
            $ext = strtolower(pathinfo($document['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];
            if (!in_array($ext, $allowed_ext, true)) {
                return $this->response(['status' => false, 'message' => 'Invalid file type. Only JPG, PNG, and PDF are allowed.'], 400);
            }

            // 3. MIME type verification using finfo (belt-and-suspenders)
            $allowed_mime = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($document['tmp_name']);
            if (!in_array($mime, $allowed_mime, true)) {
                return $this->response(['status' => false, 'message' => 'File content does not match the allowed types.'], 400);
            }

            // 4. Random filename — never trust the original name
            $upload_dir = FCPATH . 'uploads/offline_payments/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0750, true);
            }
            $random_name = bin2hex(random_bytes(16)) . '.' . $ext;
            if (move_uploaded_file($document['tmp_name'], $upload_dir . $random_name)) {
                $document_path = 'uploads/offline_payments/' . $random_name;
            }
        }

        // Create pending enrollment
        $this->db->insert('pending_enrol', [
            'user_id' => $user['user_id'],
            'course_id' => $course_id,
            'amount' => $price,
            'payment_type' => 'offline',
            'document' => $document_path,
            'status' => 'pending',
            'created_at' => time()
        ]);

        return $this->response([
            'status' => true,
            'message' => 'Offline payment submitted. Awaiting admin approval.',
            'data' => [
                'pending_id' => $this->db->insert_id()
            ]
        ], 201);
    }

    // ========== CART OPERATIONS ==========

    /**
     * GET /api_payment/cart
     * Get cart items
     */
    public function cart_get()
    {
        $user = $this->authenticate(false);
        if (!$user) {
            $this->response(['status' => true, 'data' => []], 200);
            return;
        }

        $cart_items = $this->db->select('c.*, cr.title, cr.price, cr.discounted_price, cr.discount_flag, cr.is_free_course, cr.thumbnail, cr.user_id as instructor_id')
            ->from('cart c')
            ->join('course cr', 'cr.id = c.course_id')
            ->where('c.user_id', $user['user_id'])
            ->get()
            ->result_array();

        $total = 0;
        foreach ($cart_items as &$item) {
            // Match the price the checkout actually charges: a discount only
            // applies when discount_flag is on AND a discounted price exists.
            $item['effective_price'] = $item['is_free_course'] == 1 ? 0 : (($item['discount_flag'] == 1 && $item['discounted_price'] > 0) ? $item['discounted_price'] : $item['price']);
            $item['thumbnail_url'] = $this->get_course_thumbnail($item['course_id']);
            // The raw cr.thumbnail column is just a filename; the frontend reads
            // `thumbnail` first, so overwrite it with the resolved public path.
            $item['thumbnail'] = $item['thumbnail_url'];
            $item['instructor'] = $this->get_instructor_info($item['instructor_id']);
            $total += $item['effective_price'];
        }

        return $this->response([
            'status' => true,
            'data' => [
                'items' => $cart_items,
                'subtotal' => $total,
                'currency' => get_settings('system_currency')
            ]
        ], 200);
    }

    /**
     * POST /api_payment/cart/add
     * Add item to cart
     */
    public function cart_add_post()
    {
        $user = $this->authenticate(false);
        if (!$user) {
            // 401 (not 200) so the SPA's shared handle401() clears the stale
            // session and redirects to /login instead of leaving the user
            // stuck looking "logged in" while every cart action fails.
            return $this->response(['status' => false, 'message' => 'Please log in first to add courses to your cart.', 'require_login' => true], 401);
        }

        $course_id = $this->input->post('course_id');

        if (empty($course_id)) {
            return $this->response(['status' => false, 'message' => 'Course ID required'], 400);
        }

        // Check course exists
        $course = $this->db->get_where('course', ['id' => $course_id, 'status' => 'active'])->row_array();
        if (!$course) {
            return $this->response(['status' => false, 'message' => 'Course not found'], 404);
        }

        // Check if already enrolled
        $enrolled = $this->db->get_where('enrol', [
            'user_id' => $user['user_id'],
            'course_id' => $course_id
        ])->row_array();

        if ($enrolled) {
            return $this->response(['status' => false, 'message' => 'Already enrolled in this course'], 409);
        }

        // Check if already in cart
        $in_cart = $this->db->get_where('cart', [
            'user_id' => $user['user_id'],
            'course_id' => $course_id
        ])->row_array();

        if ($in_cart) {
            return $this->response(['status' => false, 'message' => 'Course already in cart'], 409);
        }

        // Add to cart
        $this->db->insert('cart', [
            'user_id' => $user['user_id'],
            'course_id' => $course_id,
            'date_added' => time()
        ]);

        return $this->response([
            'status' => true,
            'message' => 'Added to cart',
            'data' => ['cart_id' => $this->db->insert_id()]
        ], 201);
    }

    /**
     * DELETE /api_payment/cart/remove/{course_id}
     * Remove item from cart
     */
    public function cart_remove_delete($course_id = null)
    {
        $user = $this->authenticate(false);
        if (!$user) {
            return $this->response(['status' => true, 'message' => 'Not in cart (Guest)'], 200);
        }

        if (!$course_id) {
            return $this->response(['status' => false, 'message' => 'Course ID required'], 400);
        }

        $this->db->where('user_id', $user['user_id'])
            ->where('course_id', $course_id)
            ->delete('cart');

        return $this->response(['status' => true, 'message' => 'Removed from cart'], 200);
    }

    /**
     * DELETE /api_payment/cart/clear
     * Clear entire cart
     */
    public function cart_clear_delete()
    {
        $user = $this->authenticate(false);
        if (!$user) {
            return $this->response(['status' => true, 'message' => 'Cart cleared (Guest)'], 200);
        }

        $this->db->where('user_id', $user['user_id'])->delete('cart');

        return $this->response(['status' => true, 'message' => 'Cart cleared'], 200);
    }

    /**
     * POST /api_payment/checkout
     * Checkout entire cart — creates a TAP charge or PayPal order
     * (payment_method is mandatory: 'tap' or 'paypal').
     */
    public function checkout_post()
    {
        $user = $this->authenticate();
        if (!$user) return;

        // Payment method is mandatory and must be one of the active gateways
        // (TAP = "Debit or credit cards", PayPal). Stripe is no longer supported.
        $payment_method = $this->input->post('payment_method');
        if (!in_array($payment_method, ['tap', 'paypal'], true)) {
            return $this->response(['status' => false, 'message' => 'Please choose a payment method before checking out.'], 400);
        }

        // Get cart from the `cart` table — the same store the cart page and
        // add-to-cart use (users.cart_items is a dead legacy store).
        $cart = $this->get_cart_total($user['user_id']);
        $course_ids = $cart['course_ids'];
        $line_items = $cart['line_items'];
        $total = $cart['total'];

        if (empty($course_ids)) {
            return $this->response(['status' => false, 'message' => 'Cart is empty'], 400);
        }

        // Apply a coupon code against the cart subtotal, if one was provided —
        // reject the whole checkout on an invalid/expired/already-used code
        // rather than silently charging full price.
        $coupon_code = trim((string) $this->input->post('coupon_code'));
        $applied_coupon = null;
        if ($coupon_code !== '' && $total > 0) {
            $coupon_result = $this->apply_coupon($coupon_code, $line_items, $user['user_id']);
            if (!$coupon_result['valid']) {
                return $this->response(['status' => false, 'message' => $coupon_result['message']], 400);
            }
            $total = $coupon_result['final_price'];
            $applied_coupon = $coupon_code;
        }

        if ($total == 0) {
            // All free (naturally-free courses, or a coupon discounted the cart
            // to zero) — enroll directly, no payment gateway needed.
            foreach ($course_ids as $cid) {
                $this->enroll_user($user['user_id'], $cid, 0, 'free');
            }
            // enroll_user() only logs a `payment` row when amount > 0, so a
            // 100%-off coupon needs its own record here — otherwise
            // apply_coupon()'s per-user reuse check could never see it and the
            // same code could be replayed for unlimited free enrollments.
            if ($applied_coupon) {
                $this->db->insert('payment', [
                    'user_id' => $user['user_id'],
                    'course_id' => $course_ids[0],
                    'course_ids' => json_encode($course_ids),
                    'status' => 'completed',
                    'amount' => 0,
                    'payment_type' => 'coupon',
                    'coupon' => $applied_coupon,
                    'date_added' => time(),
                ]);
            }
            $this->db->where('user_id', $user['user_id'])->delete('cart');
            return $this->response(['status' => true, 'message' => 'Enrolled in all courses', 'data' => ['enrolled' => true]], 200);
        }

        // Store pending checkout
        $checkout_token = bin2hex(random_bytes(16));

        // Stripe checkout removed — only PayPal and TAP are supported below.

        // Handle PayPal checkout
        if ($payment_method == 'paypal') {
            $paypal = json_decode(get_settings('paypal'), true);
            if (!$paypal || !isset($paypal[0])) {
                return $this->response(['status' => false, 'message' => 'PayPal not configured'], 500);
            }
            $is_sandbox = ($paypal[0]['mode'] ?? 'sandbox') == 'sandbox';
            $client_id = $is_sandbox ? $paypal[0]['sandbox_client_id'] : $paypal[0]['production_client_id'];
            $secret = $is_sandbox ? $paypal[0]['sandbox_secret_key'] : $paypal[0]['production_secret_key'];
            $api_url = $is_sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';

            // Get access token
            $ch = curl_init($api_url . '/v1/oauth2/token');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
                CURLOPT_USERPWD => $client_id . ':' . $secret,
            ]);
            $token_result = json_decode(curl_exec($ch), true);
            curl_close($ch);

            if (!isset($token_result['access_token'])) {
                return $this->response(['status' => false, 'message' => 'PayPal authentication failed'], 500);
            }

            $currency = strtoupper(get_settings('paypal_currency') ?: 'USD');

            // Create PayPal order
            $order_data = json_encode([
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'amount' => ['currency_code' => $currency, 'value' => number_format($total, 2, '.', '')],
                    'description' => 'Course purchase - ' . count($course_ids) . ' course(s)',
                ]],
                'application_context' => [
                    'return_url' => site_url('api_frontend/payment_success?token=' . $checkout_token),
                    'cancel_url' => site_url('api_frontend/payment_cancel?token=' . $checkout_token),
                ],
            ]);

            $ch = curl_init($api_url . '/v2/checkout/orders');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $order_data,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $token_result['access_token'],
                ],
            ]);
            $order_result = json_decode(curl_exec($ch), true);
            curl_close($ch);

            $approve_link = null;
            if (isset($order_result['links'])) {
                foreach ($order_result['links'] as $link) {
                    if ($link['rel'] == 'approve') {
                        $approve_link = $link['href'];
                        break;
                    }
                }
            }

            if (!$approve_link) {
                return $this->response(['status' => false, 'message' => 'Failed to create PayPal order', 'error' => $order_result], 500);
            }

            // Save PENDING payment record with the purchased-courses snapshot.
            $this->db->insert('payment', [
                'user_id' => $user['user_id'],
                'payment_type' => 'paypal',
                'course_id' => $course_ids[0],
                'course_ids' => json_encode($course_ids),
                'status' => 'pending',
                'amount' => $total,
                'transaction_id' => $order_result['id'] ?? '',
                'session_id' => $checkout_token,
                'coupon' => $applied_coupon,
                'date_added' => time(),
            ]);

            return $this->response([
                'status' => true,
                'data' => [
                    'redirect_url' => $approve_link,
                    'checkout_token' => $checkout_token,
                    'total' => $total,
                ]
            ], 200);
        }

        // Handle Tap checkout
        if ($payment_method == 'tap') {
            $tap_gateway = $this->db->get_where('payment_gateways', ['identifier' => 'tap', 'status' => 1])->row_array();
            if (!$tap_gateway) {
                return $this->response(['status' => false, 'message' => 'Tap Payments not configured'], 500);
            }

            $keys = json_decode($tap_gateway['keys'], true);
            $api_key = ($tap_gateway['enabled_test_mode'] == 1) ? $keys['test_secret_key'] : $keys['live_secret_key'];
            $currency = $tap_gateway['currency'] ?: 'USD';

            $user_details = $this->db->get_where('users', ['id' => $user['user_id']])->row_array();

            $course_names = array_map(function ($item) {
                return $item['name'];
            }, $line_items);
            $description = 'Purchase: ' . implode(', ', array_slice($course_names, 0, 3));
            if (count($course_names) > 3) {
                $description .= ' +' . (count($course_names) - 3) . ' more';
            }

            $charge_data = [
                'amount' => $total,
                'currency' => $currency,
                'threeDSecure' => true,
                'save_card' => false,
                'description' => $description,
                'metadata' => [
                    'udf1' => 'cart_checkout',
                    'udf2' => (string)$user['user_id'],
                    'udf3' => $checkout_token
                ],
                'reference' => [
                    'transaction' => 'txn_' . time(),
                    'order' => 'ord_' . $checkout_token
                ],
                'receipt' => [
                    'email' => true,
                    'sms' => false
                ],
                'customer' => [
                    'first_name' => $user_details['first_name'] ?: 'Customer',
                    'last_name' => $user_details['last_name'] ?: '',
                    'email' => $user_details['email'],
                    'phone' => [
                        'country_code' => '20',
                        'number' => $user_details['phone'] ?: ''
                    ]
                ],
                'source' => [
                    'id' => 'src_all'
                ],
                'redirect' => [
                    'url' => site_url('api_frontend/tap_success?token=' . $checkout_token)
                ],
                'post' => [
                    'url' => site_url('payment/tap_webhook')
                ]
            ];

            $ch = curl_init('https://api.tap.company/v2/charges');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($charge_data),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $api_key,
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_TIMEOUT => 30,
            ]);
            $result = json_decode(curl_exec($ch), true);
            $curl_err = curl_error($ch);
            curl_close($ch);

            if ($curl_err || !isset($result['id'])) {
                log_message('error', 'Tap charge creation failed: ' . ($curl_err ?: json_encode($result)));
                return $this->response([
                    'status' => false,
                    'message' => 'Failed to create Tap payment',
                    'error' => isset($result['errors']) ? $result['errors'] : $curl_err
                ], 500);
            }

            $redirect_url = isset($result['transaction']['url']) ? $result['transaction']['url'] : null;
            if (!$redirect_url) {
                return $this->response(['status' => false, 'message' => 'No redirect URL from Tap'], 500);
            }

            $this->db->insert('payment', [
                'user_id' => $user['user_id'],
                'payment_type' => 'tap',
                'course_id' => $course_ids[0],
                'course_ids' => json_encode($course_ids),
                'status' => 'pending',
                'amount' => $total,
                'transaction_id' => $result['id'],
                'session_id' => $checkout_token,
                'coupon' => $applied_coupon,
                'date_added' => time(),
            ]);

            return $this->response([
                'status' => true,
                'data' => [
                    'redirect_url' => $redirect_url,
                    'checkout_token' => $checkout_token,
                    'total' => $total,
                ]
            ], 200);
        }

        // Fail closed: a paid cart must go through a gateway above. Reaching here
        // means the gateway produced no redirect — never report success (which
        // would falsely enroll/redirect the user without a real payment).
        return $this->response([
            'status'  => false,
            'message' => 'Could not start the payment. Please try again or contact support.'
        ], 502);
    }

    // ========== HELPER METHODS ==========

    /**
     * Shared cart-subtotal computation for checkout_post() and
     * apply_coupon_post() — one place, so the coupon "preview" total always
     * matches the amount actually charged at checkout.
     */
    private function get_cart_total($user_id)
    {
        $cart_rows = $this->db->select('course_id')->get_where('cart', ['user_id' => $user_id])->result_array();
        $cart_ids = array_map(function ($r) {
            return (int) $r['course_id'];
        }, $cart_rows);

        $total = 0;
        $course_ids = [];
        $line_items = [];
        foreach ($cart_ids as $cid) {
            $course = $this->db->get_where('course', ['id' => $cid])->row_array();
            if (!$course) continue;
            $course_ids[] = $cid;
            if ($course['is_free_course'] != 1) {
                $price = ($course['discount_flag'] == 1 && $course['discounted_price'] > 0) ? (float) $course['discounted_price'] : (float) $course['price'];
                $total += $price;
                $line_items[] = ['course_id' => $cid, 'name' => $course['title'], 'price' => $price];
            }
        }

        return ['total' => $total, 'course_ids' => $course_ids, 'line_items' => $line_items];
    }

    /**
     * Validate a coupon code against the real `coupons` table (columns: id,
     * code, discount_percentage, course_id, created_at, expiry_date — no
     * status/limit column, so the only caps are course_id and the
     * one-redemption-per-user check below) and compute the discounted price
     * against the cart's line items.
     *
     * course_id NULL = store-wide (discount applies to the whole cart total).
     * course_id set  = the coupon is only valid if that course is in the cart,
     * and the discount applies to that course's price only — so a coupon
     * scoped to one course can't be used to discount unrelated courses in the
     * same cart.
     */
    private function apply_coupon($code, $line_items, $user_id = null)
    {
        $coupon = $this->db->get_where('coupons', ['code' => $code])->row_array();
        if (!$coupon) {
            return ['valid' => false, 'message' => 'Invalid coupon code'];
        }

        // Treat NULL/0 expiry as "no expiry"; only a positive past timestamp expires.
        $exp = $coupon['expiry_date'];
        if (!empty($exp) && is_numeric($exp) && (int) $exp > 0 && (int) $exp < time()) {
            return ['valid' => false, 'message' => 'Coupon has expired'];
        }

        // Per-user usage limit — one redemption per coupon per user. Matched
        // against the real `payment.coupon` column (not `coupon_code`, which
        // doesn't exist).
        if ($user_id) {
            $used = $this->db->where('coupon', $code)->where('user_id', $user_id)->count_all_results('payment');
            if ($used > 0) {
                return ['valid' => false, 'message' => 'You have already used this coupon'];
            }
        }

        $total = 0;
        foreach ($line_items as $item) {
            $total += (float) $item['price'];
        }

        $percentage = min(100, max(0, (float) $coupon['discount_percentage']));

        if (!empty($coupon['course_id'])) {
            $target_price = null;
            foreach ($line_items as $item) {
                if ((int) $item['course_id'] === (int) $coupon['course_id']) {
                    $target_price = (float) $item['price'];
                    break;
                }
            }
            if ($target_price === null) {
                return ['valid' => false, 'message' => 'This coupon only applies to a specific course that is not in your cart'];
            }
            $discount = ($target_price * $percentage) / 100;
        } else {
            $discount = ($total * $percentage) / 100;
        }

        $final = max(0, $total - $discount);

        return [
            'valid' => true,
            'discount' => $discount,
            'percentage' => $percentage,
            'final_price' => $final,
        ];
    }

    private function enroll_user($user_id, $course_id, $amount, $payment_type, $transaction_id = null)
    {
        $course = $this->db->get_where('course', ['id' => $course_id])->row_array();
        if (!$course) return;

        // Idempotent enrollment — never create a duplicate enrol row.
        $already = $this->db->get_where('enrol', ['user_id' => $user_id, 'course_id' => $course_id])->num_rows() > 0;
        if (!$already) {
            // Limited-time courses must carry an expiry; empty = lifetime.
            $expiry = null;
            if ((int) ($course['expiry_period'] ?? 0) > 0) {
                $expiry = strtotime('+' . (int) $course['expiry_period'] . ' months');
            }
            $this->db->insert('enrol', [
                'user_id' => $user_id,
                'course_id' => $course_id,
                'date_added' => time(),
                'expiry_date' => $expiry,
            ]);
        }

        // One accounting row per paid course, with the instructor's own rate.
        if ($amount > 0) {
            $instructor_revenue = $this->calculate_instructor_revenue($amount, $course['user_id']);
            $this->db->insert('payment', [
                'user_id' => $user_id,
                'course_id' => $course_id,
                'amount' => $amount,
                'instructor_revenue' => $instructor_revenue,
                'admin_revenue' => $amount - $instructor_revenue,
                'payment_type' => $payment_type,
                'transaction_id' => $transaction_id,
                'status' => 'completed',
                'date_added' => time(),
            ]);
        }
    }

    private function calculate_instructor_revenue($amount, $instructor_id)
    {
        $instructor = $this->db->get_where('users', ['id' => $instructor_id])->row_array();
        $instructor_percentage = $instructor['instructor_revenue_percentage'] ?? get_settings('instructor_revenue') ?? 70;
        return ($amount * $instructor_percentage) / 100;
    }

    private function get_course_thumbnail($course_id)
    {
        // Mirror the resolver used elsewhere (theme + last_modified naming with
        // optimized/webp fallbacks) so the cart shows the real thumbnail.
        $course = $this->db->select('last_modified')->get_where('course', ['id' => $course_id])->row_array();
        if (!$course) return null;
        $theme = function_exists('get_frontend_settings') ? (get_frontend_settings('theme') ?: 'default-new') : 'default-new';
        $base = 'course_thumbnail_' . $theme . '_' . $course_id . ($course['last_modified'] ?? '');
        $opt = 'uploads/thumbnails/course_thumbnails/optimized/';
        $orig = 'uploads/thumbnails/course_thumbnails/';
        if (file_exists(FCPATH . $opt . $base . '.webp')) return '/' . $opt . $base . '.webp';
        if (file_exists(FCPATH . $opt . $base . '.jpg')) return '/' . $opt . $base . '.jpg';
        if (file_exists(FCPATH . $orig . $base . '.jpg')) return '/' . $orig . $base . '.jpg';
        return base_url() . $opt . $base . '.jpg';
    }

    private function get_instructor_info($instructor_id)
    {
        $instructor = $this->db->select('id, first_name, last_name')
            ->get_where('users', ['id' => $instructor_id])
            ->row_array();

        if ($instructor) {
            return [
                'id' => $instructor['id'],
                'name' => $instructor['first_name'] . ' ' . $instructor['last_name']
            ];
        }
        return null;
    }

    private function authenticate($return_401 = true)
    {
        $auth_token = $this->input->get('auth_token') ?: $this->input->post('auth_token');
        if (!$auth_token) {
            $headers = $this->input->request_headers();
            $auth_token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
        }

        if (!$auth_token) {
            if ($return_401) $this->response(['status' => false, 'message' => 'Authentication required'], 401);
            return false;
        }

        try {
            $decoded = $this->tokenHandler->DecodeToken($auth_token);
            return $decoded;
        } catch (Exception $e) {
            if ($return_401) $this->response(['status' => false, 'message' => 'Invalid or expired token'], 401);
            return false;
        }
    }
}
