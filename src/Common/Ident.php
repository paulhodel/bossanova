<?php

namespace bossanova\Common;

use bossanova\Jwt\Jwt;
use bossanova\Redis\Redis;
use bossanova\Render\Render;

Trait Ident
{
    /**
     * Get the registered user_id
     *
     * @return integer $user_id
     */
    public function getIdent()
    {
        // After all process check if the user is logged
        if (! $this->getUser()) {
            $param = isset(Render::$urlParam[1]) ? Render::$urlParam[1] : '';

            // Redirect the user to the login page
            if ($param != 'login') {
                // TODO: referer

                // Redirect
                $data = [
                    'error' => '1',
                    'message' => '^^[User not authenticated]^^',
                    'url' => Render::getLink(Render::$urlParam[0] . '/login'),
                ];

                if (Render::isAjax()) {
                    echo json_encode($data);
                } else {
                    header("Location: {$data['url']}\r\n");
                }

                exit;
            }
        }

        return $this->getUser();
    }

    /**
     * Get the registered user_id
     *
     * @return integer
     */
    public function getUser()
    {
        $jwt = $this->jwt();

        return isset($jwt->user_id) ? $jwt->user_id : null;
    }

    /**
     * Get the registered parent_id
     *
     * @return integer
     */
    public function getParentUser()
    {
        $jwt = $this->jwt();

        return isset($jwt->parent_id) ? $jwt->parent_id : null;
    }

    /**
     * Get the registered permission_id
     *
     * @return integer
     */
    public function getGroup()
    {
        $jwt = $this->jwt();

        return isset($jwt->permission_id) ? $jwt->permission_id : null;
    }

    /**
     * Get the registered scope
     *
     * @return array
     */
    public function getPermissions()
    {
        $jwt = $this->jwt();

        return isset($jwt->scope) ? $jwt->scope : null;
    }

    /**
     * Check if is a user is authorized based on the route
     */
    public function isAuthorized($route)
    {
        $jwt = $this->jwt();

        if (isset($jwt->scope) && $jwt->scope) {
            return property_exists($jwt->scope, $route) ? true : false;
        } else {
            return false;
        }
    }

    /**
     * Alias for isAuthorized
     */
    public function getPermission($route)
    {
        return $this->isAuthorized($route);
    }

    /**
     * Get the registered locale
     *
     * @return integer
     */
    public function getLocale()
    {
        $jwt = $this->jwt();

        return isset($jwt->locale) ? $jwt->locale : null;
    }

    /**
     * Get the registered locale
     *
     * @return integer
     */
    public function setLocale($locale)
    {
        $jwt = $this->jwt();

        if (isset($jwt->user_id) && $jwt->user_id) {
            $jwt->locale = $locale;
            $jwt->save();

            // Save the information in the database
            $user->get($jwt->user_id);
            $user->user_locale = $locale;
            $user->save();
        }
    }

    /**
     * Logout actions
     *
     * @return void
     */
    public function logout()
    {
        $jwt = new Jwt();

        // Redirect to the main page
        $url = Render::$urlParam[0];

        if ($url != 'login') {
            $url .= '/login';
        }

        // Remove hash
        if (class_exists('Redis')) {
            if ($redis = Redis::getInstance()) {
                // Save signature
                $redis->set('hash' . $this->getUser(), '');
            }
        }

        if (isset($_SESSION)) {
            // Removing session
            $_SESSION = [];
            // Destroy session
            session_destroy();
            session_commit();
        }

        // Destroy cookie
        $jwt->destroy();

        // Return
        if (Render::isAjax()) {
            $data = [
                'success' => 1,
                'message' => "^^[The user is now log out]^^",
                'url' => Render::getLink($url),
            ];
        } else {
            header("Location:/$url\r\n");
        }

        return $data;
    }

    /**
     * Get Jwt and validate
     * @return $jwt
     */
    private function jwt()
    {
        // Get JWT
        $jwt = new Jwt();

        // Redis
        if (isset($jwt->hash) && $jwt->hash) {
            if (class_exists('Redis')) {
                if ($redis = Redis::getInstance()) {
                    // Get signature
                    $hash = $jwt->sign($redis->get('hash' . $jwt->user_id));

                    if ($hash !== $jwt->hash) {
                        return null;
                    }
                }
            }
        }

        return $jwt;
    }
}
