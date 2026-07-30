<link rel="stylesheet" href="<?php echo site_url(); ?>assets/frontend/eu-cookie/purecookie.css" media="print"
  onload="this.media='all'">
<noscript>
  <link rel="stylesheet" href="<?php echo site_url(); ?>assets/frontend/eu-cookie/purecookie.css">
</noscript>

<div class="cookieConsentContainer" id="cookieConsentContainer" style="opacity: .9; display: block; display: none;">
  <!-- <div class="cookieTitle">
    <a>Cookies.</a>
  </div> -->
  <div class="cookieDesc">
    <p>
      <?php echo get_frontend_settings('cookie_note'); ?>
      <a class="link-cookie-policy"
        href="<?php echo site_url('home/cookie_policy'); ?>"><?php echo site_phrase('cookie_policy'); ?></a>
    </p>
  </div>
  <div class="cookieButton">
    <button onclick="cookieAccept();"><?php echo site_phrase('accept'); ?></button>
  </div>
</div>
<script>
  $(document).ready(function () {
    if (localStorage.getItem("accept_cookie_academy")) {
      //localStorage.removeItem("accept_cookie_academy");
    } else {
      $('#cookieConsentContainer').fadeIn(1000);
    }
  });

  function cookieAccept() {
    if (typeof (Storage) !== "undefined") {
      localStorage.setItem("accept_cookie_academy", true);
      localStorage.setItem("accept_cookie_time", "<?php echo date('m/d/Y'); ?>");
      $('#cookieConsentContainer').fadeOut(1200);
    }
  }
</script>