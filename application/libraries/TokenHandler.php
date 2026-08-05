<?php
require APPPATH . '/libraries/JWT.php';
class TokenHandler
{
   //////////The function generate token/////////////
   /* السرّ من ملفّ خارج الشيفرة لا ثابتًا فيها: القيمة المشحونة مع
      السكربت معروفة علنًا، ومن يعرفها يوقّع توكنًا لأي مستخدم. */
   private $key;

   public function __construct()
   {
       $path = APPPATH . 'config/taqdar_secret.php';
       if (!is_file($path)) {
           show_error('مفتاح توقيع التوكنات غير مضبوط.', 500, 'إعداد ناقص');
       }
       $this->key = include $path;
   }
   public function GenerateToken($data)
   {
       $jwt = JWT::encode($data, $this->key);
       return $jwt;
   }

  //////This function decode the token////////////////////
   public function DecodeToken($token)
   {
       $decoded = JWT::decode($token, $this->key, array('HS256'));
       $decodedData = (array) $decoded;
       return $decodedData;
   }
}
?>
