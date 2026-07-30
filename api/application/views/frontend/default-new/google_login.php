<div class="google-login-wrapper" style="margin-top: 15px;">
    <div id="g_id_onload" data-client_id="<?php echo get_settings('google_client_id'); ?>"
        data-callback="handleGoogleLogin" data-auto_prompt="false">
    </div>
    <div class="g_id_signin" data-type="standard" data-size="large" data-theme="outline" data-text="sign_in_with"
        data-shape="rectangular" data-logo_alignment="left" data-width="350">
    </div>
</div>

<script src="https://accounts.google.com/gsi/client" async defer></script>
<script>
    function handleGoogleLogin(response) {
        $.ajax({
            url: '<?php echo site_url('login/google_validate_login'); ?>',
            type: 'POST',
            data: {
                id_token: response.credential
            },
            dataType: 'json',
            success: function (data) {
                if (data.success) {
                    window.location.href = '<?php echo site_url('home'); ?>';
                } else {
                    alert(data.message || 'Google login failed');
                }
            },
            error: function () {
                window.location.reload();
            }
        });
    }
</script>

<style>
    .google-login-wrapper {
        display: flex;
        justify-content: center;
        align-items: center;
    }
</style>