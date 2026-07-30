<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Tap Payments Integration Model
 * Handles Tap payment gateway integration for Academy LMS
 * API Documentation: https://developers.tap.company/
 */
class Tap_payment_model extends CI_Model
{
    private $api_base_url_test = 'https://api.tap.company/v2/';
    private $api_base_url_live = 'https://api.tap.company/v2/';

    function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->library('session');
    }

    /**
     * Create a Tap charge session
     * @param string $identifier - Payment gateway identifier
     * @param string $type - Payment type (course or instructor_payout)
     * @return array|bool - Charge response with redirect URL or false on failure
     */
    public function create_tap_charge($identifier = 'tap', $type = 'course')
    {
        $payment_details = $this->session->userdata('payment_details');
        
        // Get gateway configuration
        if ($payment_details['is_instructor_payout_user_id'] > 0) {
            $instructor_details = $this->user_model->get_all_user($payment_details['is_instructor_payout_user_id'])->row_array();
            $keys = json_decode($instructor_details['payment_keys'], true);
            $keys = $keys[$identifier];
            $test_mode = 0; // Instructor payouts use live mode
        } else {
            $payment_gateway = $this->db->get_where('payment_gateways', ['identifier' => $identifier])->row_array();
            $keys = json_decode($payment_gateway['keys'], true);
            $test_mode = $payment_gateway['enabled_test_mode'];
        }

        // Get customer details
        $user_details = $this->user_model->get_all_user($this->session->userdata('user_id'))->row_array();
        
        // Determine API key and URL based on mode
        if ($test_mode == 1) {
            $api_key = $keys['test_secret_key'];
            $api_url = $this->api_base_url_test;
        } else {
            $api_key = $keys['live_secret_key'];
            $api_url = $this->api_base_url_live;
        }

        // Get payment gateway currency
        $payment_gateway = $this->db->get_where('payment_gateways', ['identifier' => $identifier])->row_array();
        $currency = $payment_gateway['currency'];

        // Build charge request payload
        $charge_data = [
            'amount' => $payment_details['total_payable_amount'],
            'currency' => $currency,
            'threeDSecure' => true,
            'save_card' => false,
            'description' => $payment_details['payment_title'],
            'metadata' => [
                'udf1' => $type,
                'udf2' => $this->session->userdata('user_id'),
                'udf3' => date('Y-m-d H:i:s')
            ],
            'reference' => [
                'transaction' => 'txn_' . time(),
                'order' => 'ord_' . uniqid()
            ],
            'receipt' => [
                'email' => true,
                'sms' => false
            ],
            'customer' => [
                'first_name' => $user_details['first_name'],
                'last_name' => $user_details['last_name'],
                'email' => $user_details['email'],
                'phone' => [
                    'country_code' => '20',
                    'number' => $user_details['phone'] ?: ''
                ]
            ],
            'source' => [
                'id' => 'src_all' // Accept all payment sources
            ],
            'redirect' => [
                'url' => $payment_details['success_url'] . '/' . $identifier
            ],
            'post' => [
                'url' => site_url('payment/tap_webhook')
            ]
        ];

        // Make API request
        $response = $this->make_tap_api_request($api_url . 'charges', $api_key, $charge_data);

        if ($response && isset($response['id'])) {
            // Store charge ID in session for verification
            $this->session->set_userdata('tap_charge_id', $response['id']);
            return $response;
        }

        return false;
    }

    /**
     * Verify Tap payment status
     * @param string $identifier - Payment gateway identifier
     * @param string $type - Payment type (course or instructor_payout)
     * @return boolean - Payment verification result
     */
    public function check_tap_payment($identifier = 'tap', $type = 'course')
    {
        $payment_details = $this->session->userdata('payment_details');
        
        // Get charge ID from URL parameter
        $charge_id = $this->input->get('tap_id');
        
        if (!$charge_id) {
            // Try to get from session
            $charge_id = $this->session->userdata('tap_charge_id');
        }

        if (!$charge_id) {
            return false;
        }

        // Get gateway configuration
        if ($payment_details['is_instructor_payout_user_id'] > 0) {
            $instructor_details = $this->user_model->get_all_user($payment_details['is_instructor_payout_user_id'])->row_array();
            $keys = json_decode($instructor_details['payment_keys'], true);
            $keys = $keys[$identifier];
            $test_mode = 0;
        } else {
            $payment_gateway = $this->db->get_where('payment_gateways', ['identifier' => $identifier])->row_array();
            $keys = json_decode($payment_gateway['keys'], true);
            $test_mode = $payment_gateway['enabled_test_mode'];
        }

        // Determine API key and URL
        if ($test_mode == 1) {
            $api_key = $keys['test_secret_key'];
            $api_url = $this->api_base_url_test;
        } else {
            $api_key = $keys['live_secret_key'];
            $api_url = $this->api_base_url_live;
        }

        // Retrieve charge details from Tap API
        $charge = $this->retrieve_tap_charge($charge_id, $api_key, $api_url);

        if ($charge && isset($charge['status'])) {
            // Check if payment is captured/authorized successfully
            if ($charge['status'] == 'CAPTURED' || $charge['status'] == 'AUTHORIZED') {
                // Verify amount matches
                if ($charge['amount'] == $payment_details['total_payable_amount']) {
                    // Clear charge ID from session
                    $this->session->unset_userdata('tap_charge_id');
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Retrieve charge details from Tap API
     * @param string $charge_id - Tap charge ID
     * @param string $api_key - Tap API secret key
     * @param string $api_url - Tap API base URL
     * @return array|false - Charge details or false
     */
    private function retrieve_tap_charge($charge_id, $api_key, $api_url)
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $api_url . 'charges/' . $charge_id,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $api_key,
                'Accept: application/json'
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            log_message('error', 'Tap API Error: ' . $err);
            return false;
        }

        return json_decode($response, true);
    }

    /**
     * Public wrapper for retrieving a Tap charge.
     * Used by Api_frontend controller which cannot call private methods.
     *
     * @param string $charge_id - Tap charge ID (chg_xxx)
     * @param string $api_key   - Tap secret key
     * @return array|false
     */
    public function retrieve_tap_charge_public($charge_id, $api_key)
    {
        return $this->retrieve_tap_charge($charge_id, $api_key, $this->api_base_url_live);
    }

    /**
     * Make API request to Tap
     * @param string $url - API endpoint URL
     * @param string $api_key - API secret key
     * @param array $data - Request payload
     * @return array|false - API response or false
     */
    private function make_tap_api_request($url, $api_key, $data)
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $api_key,
                'Content-Type: application/json',
                'Accept: application/json'
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            log_message('error', 'Tap API Error: ' . $err);
            return false;
        }

        return json_decode($response, true);
    }

    /**
     * Validate webhook hashstring for security
     * @param array $charge_data - Charge/webhook data
     * @param string $posted_hashstring - Hashstring from webhook header
     * @param string $api_key - Secret API key
     * @return boolean - Validation result
     */
    public function validate_webhook_hashstring($charge_data, $posted_hashstring, $api_key)
    {
        // Extract values for hashstring calculation
        $id = $charge_data['id'];
        $amount = number_format($charge_data['amount'], 2, '.', '');
        $currency = $charge_data['currency'];
        $gateway_reference = isset($charge_data['reference']['gateway']) ? $charge_data['reference']['gateway'] : '';
        $payment_reference = isset($charge_data['reference']['payment']) ? $charge_data['reference']['payment'] : '';
        $status = $charge_data['status'];
        $created = $charge_data['transaction']['created'];

        // Construct hashstring
        $to_be_hashed = 'x_id' . $id . 
                       'x_amount' . $amount . 
                       'x_currency' . $currency . 
                       'x_gateway_reference' . $gateway_reference . 
                       'x_payment_reference' . $payment_reference . 
                       'x_status' . $status . 
                       'x_created' . $created;

        // Calculate hash
        $calculated_hash = hash_hmac('sha256', $to_be_hashed, $api_key);

        // Compare hashes
        return hash_equals($calculated_hash, $posted_hashstring);
    }

    /**
     * Process webhook notification from Tap.
     * Acts as a backup enrollment path if the redirect handler fails.
     *
     * @param array $webhook_data - Webhook payload from Tap
     * @return boolean
     */
    public function process_tap_webhook($webhook_data)
    {
        if (!isset($webhook_data['id']) || !isset($webhook_data['status'])) {
            log_message('error', 'Invalid Tap webhook data');
            return false;
        }

        // Log identifiers only — never the full payload (it carries customer PII).
        log_message('info', 'Tap Webhook Received: id=' . $webhook_data['id'] . ' status=' . $webhook_data['status']);

        $hashstring = isset($_SERVER['HTTP_HASHSTRING']) ? $_SERVER['HTTP_HASHSTRING'] : '';
        $payment_gateway = $this->db->get_where('payment_gateways', ['identifier' => 'tap'])->row_array();
        $keys = json_decode($payment_gateway['keys'], true);
        $test_mode = $payment_gateway['enabled_test_mode'];
        $api_key = ($test_mode == 1) ? $keys['test_secret_key'] : $keys['live_secret_key'];

        // Fail-closed: a request with no (or an invalid) HASHSTRING header is
        // rejected outright — it used to be silently trusted when the header
        // was simply absent.
        if (!$hashstring || !$this->validate_webhook_hashstring($webhook_data, $hashstring, $api_key)) {
            log_message('error', 'Tap webhook rejected: missing/invalid hashstring for charge ' . $webhook_data['id']);
            return false;
        }

        // Never trust the posted body for charge state — re-fetch from Tap's
        // API, the same way the tap_success redirect handler does.
        $charge_id = $webhook_data['id'];
        $charge = $this->retrieve_tap_charge($charge_id, $api_key, $this->api_base_url_live);
        if (!$charge || !isset($charge['status'])) {
            log_message('error', 'Tap webhook: could not verify charge ' . $charge_id . ' with Tap API');
            return false;
        }

        if (!in_array($charge['status'], ['CAPTURED', 'AUTHORIZED'])) {
            log_message('info', 'Tap webhook non-success status: ' . $charge['status'] . ' for charge ' . $charge_id);
            return true;
        }

        // Find payment record by checkout token (metadata.udf3) or charge ID
        $checkout_token = isset($charge['metadata']['udf3']) ? $charge['metadata']['udf3'] : null;

        $payment = null;
        if ($checkout_token) {
            $payment = $this->db->get_where('payment', [
                'session_id' => $checkout_token,
                'payment_type' => 'tap'
            ])->row_array();
        }
        if (!$payment) {
            $payment = $this->db->get_where('payment', [
                'transaction_id' => $charge_id,
                'payment_type' => 'tap'
            ])->row_array();
        }

        if (!$payment) {
            log_message('error', 'Tap webhook: no payment record for charge ' . $charge_id);
            return false;
        }

        // Idempotent: the redirect handler may have already finalized this.
        if (($payment['status'] ?? '') === 'completed') {
            log_message('info', 'Tap webhook: payment already completed for charge ' . $charge_id);
            return true;
        }

        // Same guards as tap_success_get: amount + currency must match, and
        // this charge must not already be bound to a different session.
        $expected_amount = (float) $payment['amount'];
        $received_amount = (float) ($charge['amount'] ?? 0);
        $expected_currency = strtoupper(get_settings('system_currency') ?: 'USD');
        $received_currency = strtoupper($charge['currency'] ?? '');

        if (abs($received_amount - $expected_amount) > 0.01) {
            log_message('error', 'Tap webhook amount mismatch: expected=' . $expected_amount . ' got=' . $received_amount);
            return false;
        }
        if ($received_currency && $received_currency !== $expected_currency) {
            log_message('error', 'Tap webhook currency mismatch: expected=' . $expected_currency . ' got=' . $received_currency);
            return false;
        }

        $existing_tap = $this->db
            ->where('transaction_id', $charge_id)
            ->where('status', 'completed')
            ->where('session_id !=', $payment['session_id'])
            ->count_all_results('payment');
        if ($existing_tap > 0) {
            log_message('error', 'Tap webhook replay detected: charge=' . $charge_id);
            return false;
        }

        // Activate from the real cart-checkout snapshot (payment.course_ids),
        // not the dead users.cart_items store — this is what makes the
        // webhook an actual working fallback instead of a no-op.
        $this->finalize_purchase_from_snapshot($payment);
        log_message('info', 'Tap webhook: finalized purchase for payment #' . $payment['id']);
        return true;
    }

    /**
     * Fallback activation path (backup to the tap_success redirect handler).
     * Mirrors Api_frontend::finalize_purchase() so both entry points enroll
     * from the exact same payment.course_ids snapshot and can't drift apart.
     *
     * @param array $payment - row from the `payment` table
     */
    public function finalize_purchase_from_snapshot($payment)
    {
        if (($payment['status'] ?? '') === 'completed') return;
        $user_id = $payment['user_id'];

        $course_ids = json_decode($payment['course_ids'] ?? '[]', true);
        if (!is_array($course_ids) || empty($course_ids)) {
            $rows = $this->db->select('course_id')->get_where('cart', ['user_id' => $user_id])->result_array();
            $course_ids = array_map(function ($r) {
                return (int) $r['course_id'];
            }, $rows);
        }

        $default_pct = (float) (get_settings('instructor_revenue') ?: 70);
        $reused = false;
        foreach ($course_ids as $cid) {
            $course = $this->db->get_where('course', ['id' => $cid])->row_array();
            if (!$course) continue;

            if ($this->db->get_where('enrol', ['user_id' => $user_id, 'course_id' => $cid])->num_rows() == 0) {
                $expiry = ((int) ($course['expiry_period'] ?? 0) > 0)
                    ? strtotime('+' . (int) $course['expiry_period'] . ' months') : null;
                $this->db->insert('enrol', ['user_id' => $user_id, 'course_id' => $cid, 'date_added' => time(), 'expiry_date' => $expiry]);
            }

            $price = ($course['is_free_course'] == 1) ? 0
                : (($course['discount_flag'] == 1 && $course['discounted_price'] > 0) ? (float) $course['discounted_price'] : (float) $course['price']);
            $inst = $this->db->get_where('users', ['id' => $course['user_id']])->row_array();
            $pct = (isset($inst['instructor_revenue_percentage']) && $inst['instructor_revenue_percentage'] !== '')
                ? (float) $inst['instructor_revenue_percentage'] : $default_pct;
            $inst_rev = $price * $pct / 100;

            if (!$reused) {
                $this->db->where('id', $payment['id'])->update('payment', [
                    'course_id' => $cid,
                    'amount' => $price,
                    'status' => 'completed',
                    'instructor_revenue' => $inst_rev,
                    'admin_revenue' => $price - $inst_rev,
                ]);
                $reused = true;
            } elseif ($price > 0) {
                $this->db->insert('payment', [
                    'user_id' => $user_id,
                    'course_id' => $cid,
                    'amount' => $price,
                    'payment_type' => $payment['payment_type'],
                    'transaction_id' => $payment['transaction_id'],
                    'session_id' => $payment['session_id'],
                    'status' => 'completed',
                    'instructor_revenue' => $inst_rev,
                    'admin_revenue' => $price - $inst_rev,
                    'date_added' => time(),
                ]);
            }
        }
        if (!$reused) {
            $this->db->where('id', $payment['id'])->update('payment', ['status' => 'completed']);
        }
        if (!empty($course_ids)) {
            $this->db->where('user_id', $user_id)->where_in('course_id', $course_ids)->delete('cart');
        }
    }
}
