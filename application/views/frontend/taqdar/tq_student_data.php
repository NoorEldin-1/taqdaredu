<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * شاشات الطالب — طبقة البيانات. **انتقلت.**
 *
 * موضعها الآن [taqdar_student_helper.php](../../../helpers/taqdar_student_helper.php)
 * وهي تحمل تلقائيا من `autoload.php`، فهذا الملف لا يعرف شيئا ويبقى
 * لأن ست شاشات تضمه بـ`include` نسبي — وحذفه يرد «Unable to load the
 * requested file» على كل واحدة منها.
 *
 * وسبب النقل أن **طبقة البيانات كانت في مجلد العرض**: `Api_v1` تسأل
 * الأسئلة نفسها ولا تجد ما تناديه إلا ملف عرض، وأخوه
 * `tq_student_styles.php` يطبع كتلة `<style>` عند ضمه — فأول نداء منها
 * يكتب CSS فوق JSON. والنسخة الثانية من الدوال هنا كانت تفترق عن
 * أختها عند أول تعديل.
 */
