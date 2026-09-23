<?php /* Public identity notice only; no account or payment logic. */ ?>
<style>
:root{--tq-notice-h:44px}
.site-header.site-header--plain ~ main,.site-header.site-header--solid ~ main{padding-block-start:calc(var(--header-h) + var(--tq-notice-h))}
.tq-identity{display:flex;align-items:center;gap:4px;height:44px;padding-inline:clamp(6px,2vw,28px);background:var(--petrol,#003d38);color:#fff;border-bottom:1px solid var(--gold,#c9a55f);font-size:13px;line-height:1.6;direction:rtl}
.tq-identity[hidden]{display:none}
.tq-identity__viewport{overflow:hidden;flex:1;min-width:0;direction:rtl}
.tq-identity__track{display:flex;width:max-content;animation:tq-identity-scroll 40s linear infinite}
.tq-identity__copy{flex:none;white-space:nowrap;padding-inline:24px;direction:rtl}
.tq-identity__copy b{color:#f0d7a0;font-weight:600}
.tq-identity button{flex:none;display:grid;place-items:center;width:44px;height:42px;padding:0;border:0;border-radius:6px;background:transparent;color:#fff;font:inherit;font-size:20px;cursor:pointer}
.tq-identity button:hover{background:rgba(255,255,255,.12)}
.tq-identity button:focus-visible{outline:2px solid #f0d7a0;outline-offset:-3px}
.tq-identity:hover .tq-identity__track,.tq-identity:focus-within .tq-identity__track,.tq-identity[data-paused="true"] .tq-identity__track{animation-play-state:paused}
.tq-identity__sr{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap}
@keyframes tq-identity-scroll{from{transform:translateX(0)}to{transform:translateX(50%)}}
@media(prefers-reduced-motion:reduce){.tq-identity__track{animation:none;width:auto}.tq-identity__copy{white-space:normal;padding-inline:8px}.tq-identity__copy+ .tq-identity__copy{display:none}.tq-identity{height:auto;min-height:44px;padding-block:5px}.tq-identity__pause{display:none!important}}
</style>
<aside class="tq-identity" id="tq-identity" aria-label="تنبيه الموقع الرسمي">
  <p class="tq-identity__sr">أنت على الموقع الرسمي الوحيد لمنصة تقدر التعليمية في المملكة العربية السعودية: taqdaredu.com. ليس لنا مواقع أخرى، وتشابه الأسماء لا يعني الارتباط بنا. احرص على التحقق من الرابط قبل التسجيل أو الدفع، وتجنب المواقع غير الموثوقة.</p>
  <div class="tq-identity__viewport" aria-hidden="true">
    <div class="tq-identity__track">
      <span class="tq-identity__copy">الموقع الرسمي الوحيد لمنصة تقدر التعليمية في المملكة العربية السعودية: <b dir="ltr">taqdaredu.com</b> · ليس لنا مواقع أخرى، وتشابه الأسماء لا يعني الارتباط بنا · تحقّق من الرابط قبل التسجيل أو الدفع، وتجنّب المواقع غير الموثوقة.</span>
      <span class="tq-identity__copy">الموقع الرسمي الوحيد لمنصة تقدر التعليمية في المملكة العربية السعودية: <b dir="ltr">taqdaredu.com</b> · ليس لنا مواقع أخرى، وتشابه الأسماء لا يعني الارتباط بنا · تحقّق من الرابط قبل التسجيل أو الدفع، وتجنّب المواقع غير الموثوقة.</span>
    </div>
  </div>
  <button type="button" class="tq-identity__pause" aria-label="إيقاف حركة التنبيه" aria-pressed="false">Ⅱ</button>
  <button type="button" class="tq-identity__close" aria-label="إغلاق تنبيه الموقع الرسمي">×</button>
</aside>
<script>
(function(){
  var bar=document.getElementById('tq-identity');
  var key='tq-identity-dismissed-v1';
  function size(){document.documentElement.style.setProperty('--tq-notice-h',bar.hidden?'0px':bar.offsetHeight+'px');}
  try{bar.hidden=sessionStorage.getItem(key)==='1';}catch(e){}
  size();
  if(window.ResizeObserver){new ResizeObserver(size).observe(bar);}else{window.addEventListener('resize',size);}
  bar.querySelector('.tq-identity__close').addEventListener('click',function(){
    bar.hidden=true;size();try{sessionStorage.setItem(key,'1');}catch(e){}
    var logo=document.querySelector('#header .brand');if(logo){logo.focus();}
  });
  bar.querySelector('.tq-identity__pause').addEventListener('click',function(){
    var paused=bar.getAttribute('data-paused')!=='true';
    bar.setAttribute('data-paused',String(paused));this.setAttribute('aria-pressed',String(paused));
    this.setAttribute('aria-label',paused?'تشغيل حركة التنبيه':'إيقاف حركة التنبيه');this.textContent=paused?'▶':'Ⅱ';
  });
})();
</script>
