<?php

namespace bossanova\Auth;

use \bossanova\Common\Wget;
use \bossanova\Common\Post;
use \bossanova\Common\Request;
use \bossanova\Render\Render;

trait Social
{
    use Wget, Request, Post;

    /**
     * Facebook integration
     * @param string $token
     * @return string $json
     */
    public function googleTokenLogin($token)
    {
        $data = [];

        if (defined('BOSSANOVA_LOGIN_VIA_GOOGLE') && BOSSANOVA_LOGIN_VIA_GOOGLE == true) {

            // Token URL verification
            $url = "https://oauth2.googleapis.com/tokeninfo?id_token=$token";
            // Validate token
            $result = $this->wget($url);

            // Valid token
            if (isset($result['aud']) && $result['aud'] && $result['aud'] === GOOGLE_API_CLIENT_ID) {
                // User id found
                if (isset($result['sub']) && $result['sub']) {
                    // Locate user
                    $row = $this->user->getUserByGoogleId($result['sub']);

                    // User not found by google id
                    if (! isset($row['user_id'])) {
                        // Check if this user exists in the database by email
                        if (isset($result['email']) && $result['email']) {
                            // Try to find the user by email
                            $row = $this->user->getUserByEmail($result['email']);

                            if (isset($row['user_id']) && $row['user_id']) {
                                if (isset($row['google_id']) && $row['google_id']) {
                                    // An user with
                                    return [
                                        'error' => 1,
                                        'message' => '^^[This account already exists bound to another account.]^^',
                                    ];
                                } else {
                                    if ($password = $this->getPost('password')) {
                                        // Posted password
                                        $password = hash('sha512', $password . $row['user_salt']);
                                        // Check to see if password matches
                                        if ($password == $row['user_password'] && strtolower($row['user_email']) == strtolower($result['email']) && $row['user_status'] == 1) {
                                            $this->user->google_id = $result['sub'];
                                        } else {
                                            // There are one account with this email. Ask the user what he wants to do.
                                            return [
                                                'error' => 1,
                                                'message' => '^^[Invalid password]^^',
                                            ];
                                        }
                                    } else {
                                        // There are one account with this email. Ask the user what he wants to do.
                                        return [
                                            'success' => 1,
                                            'message' => '^^[There are an account with your email. Would you like to bound both accounts? Please enter your account password.]^^',
                                            'action' => 'bindSocialAccount',
                                        ];
                                    }
                                }
                            }
                        }
                    }

                    // Create a new user
                    if (defined('BOSSANOVA_NEWUSER_VIA_GOOGLE') && BOSSANOVA_NEWUSER_VIA_GOOGLE == true) {
                        if (! isset($row['user_id'])) {
                            $salt = hash('sha512', uniqid(mt_rand(1, mt_getrandmax()), true));
                            $pass = substr(str_shuffle(str_repeat("0123456789abcdefghijklmnopqrstuvwxyz", 6)), 0, 6);
                            $pass = hash('sha512', hash('sha512', $pass) . $salt);

                            $row = [
                                'permission_id' => 0,
                                'parent_id' => 0,
                                'google_id' => $result['sub'],
                                'user_name' => $result['given_name'],
                                'user_login' => isset($result['email']) ? $result['email'] : '',
                                'user_email' => isset($result['email']) ? $result['email'] : '',
                                'user_salt' => $salt,
                                'user_password' => $pass,
                                'user_locale' => DEFAULT_LOCALE,
                                'user_status' => 1,
                            ];

                            if ($id = $this->user->column($row)->insert()) {
                                // Load user data as object
                                $this->user->get($id);
                                // User ID
                                $row['user_id'] = (int)$id;
                            }
                        }
                    }

                    if (isset($row['user_id']) && $row['user_id']) {
                        // Message
                        $this->message = '^^[User authenticated from google token]^^';

                        // Authenticated
                        $this->authenticate($row);

                        // Force login by hash for specific use
                        $this->user->user_hash = '';
                        $this->user->user_recovery = '';
                        $this->user->user_recovery_date = '';
                        $this->user->user_hash = '';

                        // Mobile device token
                        if ($this->getRequest('token')) {
                            $this->user->user_token = $this->getRequest('token');
                        }

                        // Update user information
                        $this->user->save();

                        $data = [
                            'success' => 1,
                            'message' => $this->message,
                            'url' => Render::getLink(Render::$urlParam[0]),
                        ];

                        if (isset($id) && $id) {
                            $data['id'] = $id;
                        }
                    } else {
                        $data = [
                            'error' => 1,
                            'message' => "^^[User not authenticated]^^",
                            'url' => Render::getLink(Render::$urlParam[0] . '/login'),
                        ];
                    }
                }
            } else {
                $data = [
                    'error' => 1,
                    'message' => "^^[Invalid google token]^^",
                    'url' => Render::getLink(Render::$urlParam[0] . '/login'),
                ];
            }
        } else {
            $data = [
                'error' => 1,
                'message' => "^^[Action not allowed]^^",
                'url' => Render::getLink(Render::$urlParam[0] . '/login'),
            ];
        }

        return $data;
    }

    /**
     * Facebook integration
     * @param string $token
     * @return string $json
     */
    public function facebookTokenLogin($token)
    {
        $data = [];

        if (defined('BOSSANOVA_LOGIN_VIA_FACEBOOK') && BOSSANOVA_LOGIN_VIA_FACEBOOK == true) {
            // Token URL verification
            $url = "https://graph.facebook.com/oauth/access_token_info?client_id=" . FACEBOOK_APPID. "&access_token=$token";
            // Validate token
            $result = $this->wget($url);

            // Valid token
            if (isset($result['access_token']) && $result['access_token'] && $result['access_token'] == $token) {
                // Graph api query
                $url = "https://graph.facebook.com/me?fields=id,name,email&access_token=$token";
                // Get user data
                $result = $this->wget($url);

                // User id found
                if ($result['id']) {
                    // Locate user
                    $row = $this->user->getUserByFacebookId($result['id']);

                    // User not found by google id
                    if (! isset($row['user_id'])) {
                        // Check if this user exists in the database by email
                        if (isset($result['email']) && $result['email']) {
                            // Try to find the user by email
                            $row = $this->user->getUserByEmail($result['email']);

                            if (isset($row['user_id']) && $row['user_id']) {
                                if (isset($row['facebook_id']) && $row['facebook_id']) {
                                    // An user with
                                    return [
                                        'error' => 1,
                                        'message' => '^^[This account already exists bound to another account.]^^',
                                    ];
                                } else {
                                    if ($password = $this->getPost('password')) {
                                        // Posted password
                                        $password = hash('sha512', $password . $row['user_salt']);
                                        // Check to see if password matches
                                        if ($password == $row['user_password'] && strtolower($row['user_email']) == strtolower($result['email']) && $row['user_status'] == 1) {
                                            $this->user->facebook_id = $result['id'];
                                        } else {
                                            // There are one account with this email. Ask the user what he wants to do.
                                            return [
                                                'error' => 1,
                                                'message' => '^^[Invalid password]^^',
                                            ];
                                        }
                                    } else {
                                        // There are one account with this email. Ask the user what he wants to do.
                                        return [
                                            'success' => 1,
                                            'message' => '^^[There are an account with your email. Would you like to bound both accounts? Please enter your account password.]^^',
                                            'action' => 'bindSocialAccount',
                                        ];
                                    }
                                }
                            }
                        }
                    }

                    // Create a new user
                    if (defined('BOSSANOVA_NEWUSER_VIA_FACEBOOK') && BOSSANOVA_NEWUSER_VIA_FACEBOOK == true) {
                        if (! isset($row['user_id'])) {
                            $salt = hash('sha512', uniqid(mt_rand(1, mt_getrandmax()), true));
                            $pass = substr(str_shuffle(str_repeat("0123456789abcdefghijklmnopqrstuvwxyz", 6)), 0, 6);
                            $pass = hash('sha512', hash('sha512', $pass) . $salt);

                            $row = [
                                'permission_id' => 0,
                                'parent_id' => 0,
                                'facebook_id' => $result['id'],
                                'user_name' => $result['name'],
                                'user_login' => isset($result['email']) ? $result['email'] : '',
                                'user_email' => isset($result['email']) ? $result['email'] : '',
                                'user_salt' => $salt,
                                'user_password' => $pass,
                                'user_locale' => DEFAULT_LOCALE,
                                'user_status' => 1,
                            ];

                            if ($id = $this->user->column($row)->insert()) {
                                // Load user data as object
                                $this->user->get($id);
                                // User ID
                                $row['user_id'] = (int)$id;
                            }
                        }
                    }

                    if (isset($row['user_id']) && $row['user_id']) {
                        // Message
                        $this->message = '^^[User authenticated from facebook token]^^';

                        // Authenticated
                        $this->authenticate($row);

                        // Force login by hash for specific use
                        $this->user->user_hash = '';
                        $this->user->user_recovery = '';
                        $this->user->user_recovery_date = '';
                        $this->user->user_hash = '';

                        // Mobile device token
                        if ($this->getRequest('token')) {
                            $this->user->user_token = $this->getRequest('token');
                        }

                        // Update user information
                        $this->user->save();

                        $data = [
                            'success' => 1,
                            'message' => $this->message,
                            'url' => Render::getLink(Render::$urlParam[0]),
                        ];

                        if (isset($id) && $id) {
                            $data['id'] = $id;
                        }
                    } else {
                        $data = [
                            'error' => 1,
                            'message' => "^^[User not authenticated]^^",
                            'url' => Render::getLink(Render::$urlParam[0] . '/login'),
                        ];
                    }
                }
            } else {
                $data = [
                    'error' => 1,
                    'message' => "^^[Invalid facebook token]^^",
                    'url' => Render::getLink(Render::$urlParam[0] . '/login'),
                ];
            }
        } else {
            $data = [
                'error' => 1,
                'message' => "^^[Action not allowed]^^",
                'url' => Render::getLink(Render::$urlParam[0] . '/login'),
            ];
        }

        return $data;
    }
}