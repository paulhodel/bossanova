<?php
/**
 * (c) 2013 Bossanova PHP Framework 5
 * https://bossanova.uk/php-framework
 *
 * @category PHP
 * @package  Bossanova
 * @author   Paul Hodel <paul.hodel@gmail.com>
 * @license  The MIT License (MIT)
 * @link     https://bossanova.uk/php-framework
 *
 * Jwt
 */
namespace bossanova\Jwt;

use bossanova\Render\Render;

class Jwt extends \stdClass
{
    public $key = 'bossanova';

    /**
     * Authentication controls
     */
    public function __construct($jwtKey = null)
    {
        // Set custom key
        if (isset($jwtKey) && $jwtKey) {
            $this->key = $jwtKey;
        }

        // Must be defined in your config.php
        if (defined('BOSSANOVA_JWT_SECRET') && BOSSANOVA_JWT_SECRET) {
            if ($data = $this->getToken()) {
                // Set token as object properties
                foreach ($data as $k => $v) {
                    $this->{$k} = $v;
                }
            }
        } else {
            // Error message
            $msg = "JWT bossanova key must be defined on your config.php file";

            // User message
            if (Render::isAjax()) {
                print_r(json_encode([
                    'error'=>'1',
                    'message' => "^^[$msg]^^",
                ]));
            } else {
                header("HTTP/1.1 403 Forbidden");
                echo "^^[$msg]^^";
            }

            exit;
        }

        return $this;
    }

    public function set($data)
    {
        foreach ($data as $k => $v) {
            $this->{$k} = $v;
        }

        return $this;
    }

    public function save()
    {
        // Expires
        $expires = time() + 86400 * 3;

        // Create token
        $token = $this->setToken($this);

        // Default for 3 days
        header("Set-Cookie: {$this->key}={$token}; path=/; SameSite=Lax; expires={$expires};");

        return $token;
    }

    private function setToken($data)
    {
        // Header
        $header = [
            'alg' => 'HS512',
            'typ' => 'JWT',
        ];
        $header = $this->base64_encode(json_encode($header));

        // Payload
        $data = $this->base64_encode(json_encode($data));

        // Signature
        $signature = $this->base64_encode(hash_hmac(
            'sha512', $header . '.' . $data, BOSSANOVA_JWT_SECRET, true)
        );

        // Token
        return ($header . '.' . $data . '.' .  $signature);
    }

    private function getToken()
    {
        // Verify
        if ($this->isValid()) {
             // Token
            $webToken = $this->getPostedToken();
            $webToken = explode('.', $webToken);

            return json_decode($this->base64_decode($webToken[1]));
        }

        return false;
    }

    private function isValid()
    {
        $webToken = $this->getPostedToken();

        if ($webToken) {
            // Token
            $webToken = explode('.', $webToken);
            // Header, payload and signature
            if (count($webToken) == 3) {
                // Body
                $body = $webToken[0] . '.' . $webToken[1];
                // Signature
                $signature = $this->base64_encode(hash_hmac(
                    'sha512', $body, BOSSANOVA_JWT_SECRET, true));

                // Verify
                if ($signature === $webToken[2]) {
                    // Valid token
                    return true;
                }
            }
        }

        return false;
    }

    private function base64_encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64_decode($data)
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'),
            strlen($data) % 4, '=', STR_PAD_RIGHT));
    }

    private function getPostedToken() {
        if (isset($_COOKIE[$this->key]) && strlen($_COOKIE[$this->key]) > 64) {
            $webToken = $_COOKIE[$this->key];
        } else if (isset($_SERVER['HTTP_AUTHORIZATION']) && $_SERVER['HTTP_AUTHORIZATION']) {
            $bearer = explode(' ', $_SERVER['HTTP_AUTHORIZATION']);
            $webToken = $bearer[1];
        } else {
            $webToken = false;
        }

        return $webToken;
    }
}
