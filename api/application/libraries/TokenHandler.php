<?php
require APPPATH . '/libraries/JWT.php';

class TokenHandler
{
    /**
     * Signing secret. Loaded from the JWT_SECRET environment variable when
     * available; otherwise a per-install random secret (rotated away from the
     * public Academy-LMS template default, which was forgeable by anyone).
     * Rotating this value invalidates existing sessions (users re-login).
     */
    private $key;

    /** Token lifetime in seconds (30 days). */
    private $ttl = 2592000;

    public function __construct()
    {
        // 1. Try getenv (works on Apache mod_php / CLI)
        $env = getenv('JWT_SECRET');
        
        // 2. Try $_SERVER (works on some PHP-FPM setups)
        if (!$env && isset($_SERVER['JWT_SECRET'])) {
            $env = $_SERVER['JWT_SECRET'];
        }
        
        // 3. Fallback to CodeIgniter config
        if (!$env && function_exists('get_instance')) {
            $CI =& get_instance();
            if ($CI) {
                $CI->load->config('config');
                $env = $CI->config->item('jwt_secret');
            }
        }

        if (!$env || strlen($env) < 32) {
            throw new RuntimeException("CRITICAL: JWT_SECRET is missing or too short. Add \$config['jwt_secret'] = 'YOUR_32_CHAR_SECRET'; to application/config/config.php");
        }
        $this->key = $env;
    }

    //////////The function generate token/////////////
    public function GenerateToken($data)
    {
        $data = (array) $data;
        $now = time();
        // Stamp issued-at / expiry so tokens are not valid forever.
        $data['iat'] = $now;
        $data['exp'] = $now + $this->ttl;
        return JWT::encode($data, $this->key);
    }

    //////This function decode the token////////////////////
    // JWT::decode auto-validates exp/nbf/iat and throws on expiry,
    // so callers' existing try/catch will treat expired tokens as guests.
    public function DecodeToken($token)
    {
        $decoded = JWT::decode($token, $this->key, array('HS256'));
        $decodedData = (array) $decoded;
        return $decodedData;
    }
}
