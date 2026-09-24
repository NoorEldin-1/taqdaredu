<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<style>
/* Scoped to plan checkout: retain the existing form and all payment controls. */
.co-compact-hero{padding-block:24px 12px!important}
.co-compact-hero h1{font-size:clamp(25px,3vw,36px);margin-block:8px}
.co-compact-hero .page-hero__lead{margin-block:6px;font-size:15px}
.co-checkout-layout{grid-template-columns:minmax(0,1.05fr) minmax(0,1fr);grid-template-rows:min-content 1fr;grid-template-areas:"account review" "confirm review";row-gap:16px}
.co-checkout-layout>.co-main{grid-area:account}
.co-checkout-layout>.co-review{grid-area:review;display:grid;gap:12px;min-width:0}
.co-checkout-layout>.co-side{grid-area:confirm;min-width:0;align-self:start}
.co-checkout-layout .icard{padding:20px;border-radius:22px}
.co-checkout-layout .icard h2{font-size:20px;margin-block:0 10px}
.co-checkout-layout .icard--sticky{position:static}
.co-checkout-layout .co-item{gap:10px;margin-block-start:8px;padding-block:10px}
.co-checkout-layout .co-item__img{width:80px;height:60px}
.co-checkout-layout .co-item__b{flex-basis:160px}
.co-checkout-layout .co-item__b h3{font-size:14px}
.co-checkout-layout .co-item__b>.tq-caption{display:none}
.co-checkout-layout .co-item__price{font-size:19px}
.co-checkout-layout .co-opens{font-size:12px;margin-block:8px}
.co-checkout-layout .co-total>div{padding-block:7px}
.co-checkout-layout .co-cycle{padding:8px 10px;margin-block-start:8px;font-size:12px}
.co-checkout-layout .co-cyc__opt{padding:10px 12px}
.co-checkout-layout .co-cyc__price{font-size:20px}
.co-checkout-layout .co-cyc__foot{font-size:12px;margin-block:8px 0}
.co-checkout-layout .co-coupon__row{margin-block-start:8px}
.co-checkout-layout .co-coupon__note{font-size:12px}
.co-checkout-layout .co-opt__box{padding:10px;gap:10px}
.co-checkout-layout .co-opt__ico{width:44px;height:44px;flex:0 0 44px;overflow:hidden}
.co-checkout-layout .co-opt__ico img{width:44px;height:44px;object-fit:cover;border-radius:10px}
.co-checkout-layout .co-opt__t b{font-size:14px}
.co-checkout-layout .co-opt__t .tq-caption{font-size:12px;line-height:1.7}
.co-checkout-layout .qa{padding:0;overflow:hidden}
.co-checkout-layout .qa__pane{padding:20px}
.co-checkout-layout .qa__band{padding:14px 20px}
.co-checkout-layout .qa__what{display:none}
.co-checkout-layout [data-qa-pane="new"] .qa__fields{grid-template-columns:repeat(2,minmax(0,1fr))}
.co-checkout-layout [data-qa-pane="new"] .qa__cell:last-child{grid-column:1/-1}
.co-checkout-layout .qa__fields{gap:14px}
.co-checkout-layout .qa__fields .form-field{margin:0}
.co-checkout-layout .qa__h{margin:0}
.co-checkout-layout .qa__hint{margin:6px 0 0}
.co-checkout-layout .co-total{margin-block-start:8px;gap:4px}
.co-checkout-layout .qa__lede{margin-block:6px 16px;font-size:14px}
.co-checkout-layout .qa__assure{margin-block:14px 8px;padding:10px;font-size:12px}
.co-checkout-layout .qa__go{display:none}
.co-checkout-layout .qa__switch{margin-block:12px 0}
.co-checkout-layout .co-side .co-steps,.co-checkout-layout .co-side__plan{display:none}
.co-checkout-layout .co-side__h{margin-block:0 8px;font-size:18px}
.co-checkout-layout .co-side__total{margin-block:8px;padding-block:10px}
.co-checkout-layout .co-side__cycle{margin-block:8px;font-size:12px}
.co-checkout-layout .co-side__note{font-size:12px;line-height:1.8;margin-block:12px 0}
.co-checkout-layout [data-tq-submit]{width:100%;min-height:52px;justify-content:center}
.co-checkout-layout .co-side__back{font-size:12px;margin-block-start:10px}
@media(max-width:980px){
 .co-checkout-layout{grid-template-columns:minmax(0,1fr);grid-template-rows:auto;grid-template-areas:"summary" "cycles" "coupon" "account" "payment" "confirm";gap:12px}
 .co-checkout-layout>.co-review{display:contents}
 .co-checkout-layout .co-summary{grid-area:summary}
 .co-checkout-layout .co-cycles{grid-area:cycles}
 .co-checkout-layout .co-coupon{grid-area:coupon}
 .co-checkout-layout .co-payment-panel{grid-area:payment}
 .co-checkout-layout .icard{padding:16px;border-radius:18px}
 .co-checkout-layout .qa{padding:0}
 .co-checkout-layout .qa__pane{padding:16px}
 .co-checkout-layout [data-qa-pane="new"] .qa__fields{grid-template-columns:minmax(0,1fr)}
 .co-checkout-layout .co-cyc{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
 .co-checkout-layout .co-cyc__opt{min-width:0;padding:9px}
 .co-checkout-layout .co-cyc__price{font-size:18px}
 .co-checkout-layout .qa__band{padding:12px 16px}
 .co-checkout-layout .co-review{gap:10px}
 .co-checkout-layout input{font-size:16px}
 .co-checkout-layout .co-item__b{flex-basis:130px}
 .co-compact-hero{padding-block:18px 8px!important}
}
</style>
