<?php

namespace bossanova\Jwt;

use bossanova\Render\Render;

class Jwt extends \stdClass
{
    /**
     * @var string $key - This defines the key of the cookie with the JWT if is used.
     */
    private $key = 'bossanova';

    /**
     * @var string $signature - This is the signature string for your JWT's
     */
    private $signature = BOSSANOVA_JWT_SECRET;

    /**
     * Authentication controls
     */
    final public function __construct($jwtKey = null, $signature = null)
    {
        // Set custom key
        if (isset($jwtKey) && $jwtKey) {
            $this->key = $jwtKey;
        }
        // Signature
        if (isset($signature) && $signature) {
            $this->signature = $signature;
        }

        // Must be defined in your config.php
        if ($this->signature) {
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

    public function setSignature($signature)
    {
        $this->signature = $signature;
    }

    public function setToken($data)
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
        $signature = $this->sign($header . '.' . $data);

        // Token
        return ($header . '.' . $data . '.' .  $signature);
    }
    
    public function createToken($data)
    {
        return $this->setToken($data);
    }

    public function getToken($asString=null)
    {
        if ($asString) {
            return $this->getPostedToken();
        } else {
            // Verify
            if ($this->isValid()) {
                // Token
                $webToken = $this->getPostedToken();
                $webToken = explode('.', $webToken);

                return json_decode($this->base64_decode($webToken[1]));
            }
        }

        return false;
    }

    public function extractToken($webToken)
    {
        // Verify
        if ($this->isValid($webToken)) {
            // Token
            $webToken = explode('.', $webToken);

            return json_decode($this->base64_decode($webToken[1]));
        }

        return false;
    }


    final public function set($data)
    {
        foreach ($data as $k => $v) {
            $this->{$k} = $v;
        }

        return $this;
    }

    final public function save()
    {
        // Expires
        $expires = time() + 86400 * 3;

        // Create token
        $token = $this->setToken($this);

        // Default for 3 days
        header("Set-Cookie: {$this->key}={$token}; path=/; SameSite=Lax; expires={$expires};");

        return $token;
    }

    final public function destroy()
    {
        header("Set-Cookie: {$this->key}=null; path=/; SameSite=Lax; expires=0;");
    }

    final public function sign($str)
    {
        return $this->base64_encode(hash_hmac('sha512', $str, $this->signature, true));
    }

    private function isValid($str = null)
    {
        if ($str) {
            $webToken = $str;
        } else {
            $webToken = $this->getPostedToken();
        }

        if ($webToken) {
            // Token
            $webToken = explode('.', $webToken);
            // Header, payload and signature
            if (count($webToken) == 3) {
                // Body
                $body = $webToken[0] . '.' . $webToken[1];
                // Signature
                $signature = $this->sign($body);
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

    private function getPostedToken()
    {
        if (isset($_SERVER['HTTP_AUTHORIZATION']) && $_SERVER['HTTP_AUTHORIZATION']) {
            $bearer = explode(' ', $_SERVER['HTTP_AUTHORIZATION']);
            $webToken = $bearer[1];
        } else if (isset($_COOKIE[$this->key]) && strlen($_COOKIE[$this->key]) > 64) {
            $webToken = $_COOKIE[$this->key];
        } else {
            $webToken = false;
        }

        return $webToken;
    }
}
