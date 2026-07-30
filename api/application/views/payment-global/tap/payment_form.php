<?php
/**
 * Tap Payments Gateway Form
 * Handles payment initialization and redirection to Tap hosted payment page
 */

// Start common code of all payment gateway
if ($payment_details['is_instructor_payout_user_id'] > 0) {
    $instructor_details = $this->user_model->get_all_user($payment_details['is_instructor_payout_user_id'])->row_array();
    $keys = json_decode($instructor_details['payment_keys'], true);
    $keys = $keys[$payment_gateway['identifier']];
} else {
    $keys = json_decode($payment_gateway['keys'], true);
}
$test_mode = $payment_gateway['enabled_test_mode'];
// End common code of all payment gateway
?>

<div id="tapPaymentResponse" class="text-danger mt-2"></div>

<!-- Tap Payment Button -->
<button class="gateway <?php echo $payment_gateway['identifier']; ?>-gateway payment-button float-end" 
        id="tapPayButton" 
        onclick="initiateTapPayment()">
    <?php echo get_phrase("pay_with_tap_payments"); ?>
</button>

<script>
    function initiateTapPayment() {
        var payButton = document.getElementById('tapPayButton');
        var responseContainer = document.getElementById('tapPaymentResponse');
        
        // Disable button and show loading
        payButton.disabled = true;
        payButton.textContent = '<?php echo get_phrase("please_wait"); ?>...';
        responseContainer.innerHTML = '';
        
        // Create charge via AJAX
        fetch("<?php echo site_url('payment/create_tap_charge'); ?>", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                createCharge: 1
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.redirect_url) {
                // Redirect to Tap hosted payment page
                window.location.href = data.redirect_url;
            } else {
                // Show error
                responseContainer.innerHTML = '<p class="text-danger">' + 
                    (data.message || '<?php echo get_phrase("payment_initialization_failed"); ?>') + 
                    '</p>';
                payButton.disabled = false;
                payButton.textContent = '<?php echo get_phrase("pay_with_tap_payments"); ?>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            responseContainer.innerHTML = '<p class="text-danger"><?php echo get_phrase("an_error_occurred"); ?></p>';
            payButton.disabled = false;
            payButton.textContent = '<?php echo get_phrase("pay_with_tap_payments"); ?>';
        });
    }
</script>

<style>
    #tapPayButton {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        color: white;
        padding: 12px 30px;
        font-size: 16px;
        font-weight: 600;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }
    
    #tapPayButton:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
    }
    
    #tapPayButton:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    #tapPaymentResponse {
        min-height: 30px;
    }
</style>
