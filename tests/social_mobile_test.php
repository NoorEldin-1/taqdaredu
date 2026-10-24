<?php
/** Offline regression checks for the mobile identity verifier. No DB or provider requests.
 * Run: php tests/social_mobile_test.php
 */
define('BASEPATH', __DIR__);
class CI_Model {}
$settings = array();
function get_settings($key) { global $settings; return $settings[$key] ?? ''; }
require dirname(__DIR__) . '/application/models/Taqdar_social_model.php';
$model = (new ReflectionClass('Taqdar_social_model'))->newInstanceWithoutConstructor();
$checks = 0;
function check($condition, $label) {
    global $checks;
    if (!$condition) throw new RuntimeException($label);
    $checks++;
}
function b64($value) { return rtrim(strtr(base64_encode($value), '+/', '-_'), '='); }
$key = openssl_pkey_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
if (!$key) throw new RuntimeException('OpenSSL RSA key generation failed.');
$rsa = openssl_pkey_get_details($key)['rsa'];
$jwk = array('kty' => 'RSA', 'kid' => 'test-key', 'n' => b64($rsa['n']), 'e' => b64($rsa['e']));
foreach (array('google', 'apple') as $provider) {
    $settings['tq_social_jwks_' . $provider] = json_encode(array('at' => time(), 'keys' => array($jwk)));
}
function jwt(array $claims, $alg = 'RS256') {
    global $key;
    $data = b64(json_encode(array('alg' => $alg, 'kid' => 'test-key'))) . '.' . b64(json_encode($claims));
    openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256);
    return $data . '.' . b64($signature);
}
$claims = array('iss' => 'https://accounts.google.com', 'aud' => 'mobile-web-client',
    'exp' => time() + 3600, 'iat' => time(), 'sub' => 'provider-person',
    'email' => 'Sara@example.com', 'email_verified' => true);
check($model->verify_id_token('google', jwt($claims), null, array('mobile-web-client'))['ok'], 'valid Google token');
foreach (array(
    'issuer' => array('iss' => 'https://attacker.example'),
    'audience' => array('aud' => 'another-app'),
    'expiry' => array('exp' => time() - 3600),
    'future issuance' => array('iat' => time() + 3600),
    'missing subject' => array('sub' => ''),
) as $label => $change) {
    check(!$model->verify_id_token('google', jwt(array_replace($claims, $change)), null,
        array('mobile-web-client'))['ok'], 'reject ' . $label);
}
check(!$model->verify_id_token('google', jwt($claims, 'none'), null, array('mobile-web-client'))['ok'], 'reject alg none');
check(!$model->verify_id_token('google', jwt($claims, 'HS256'), null, array('mobile-web-client'))['ok'], 'reject alg HS256');
$parts = explode('.', jwt($claims));
$parts[1] = b64(json_encode(array_replace($claims, array('sub' => 'different-person'))));
check(!$model->verify_id_token('google', implode('.', $parts), null, array('mobile-web-client'))['ok'], 'reject tampered signature');
check(!$model->verify_id_token('google', 'not.a.token', null, array('mobile-web-client'))['ok'], 'reject malformed JWT');
$apple = array_replace($claims, array('iss' => 'https://appleid.apple.com', 'aud' => 'com.taqdar.taqdar',
    'nonce' => hash('sha256', 'raw-nonce'), 'email' => 'person@privaterelay.appleid.com', 'email_verified' => 'true'));
check($model->verify_id_token('apple', jwt($apple), null, array('com.taqdar.taqdar'))['ok'], 'valid native Apple audience');
check($model->app_nonce_ok($apple, 'raw-nonce'), 'hashed Apple nonce');
check($model->app_nonce_ok(array('nonce' => 'raw-nonce'), 'raw-nonce'), 'raw provider nonce');
check(!$model->app_nonce_ok($apple, 'different'), 'reject mismatched nonce');
check(!$model->app_nonce_ok(array(), 'raw-nonce'), 'reject absent nonce claim');
$identity = $model->identity_from('apple', $apple,
    array('name' => array('firstName' => 'Sara', 'lastName' => 'A')));
check($identity['first'] === 'Sara' && $identity['last'] === 'A', 'Apple one-time name');
check($identity['verified'] === true, 'Apple string email verification');
check($identity['relay'] === true, 'Apple relay email');
$identity = $model->identity_from('google', $claims);
check($identity['email'] === 'sara@example.com', 'canonical email for existing account matching');
$identity = $model->identity_from('google', array_replace($claims, array('email_verified' => false)));
check($identity['verified'] === false, 'unverified email cannot be trusted for linking');
echo 'Passed ' . $checks . " mobile social identity checks.\n";
