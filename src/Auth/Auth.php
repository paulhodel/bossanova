<?php

namespace bossanova\Auth;

use bossanova\Config\Config;
use bossanova\Render\Render;
use bossanova\Mail\Mail;
use bossanova\Common\Wget;
use bossanova\Common\Post;
use bossanova\Common\Request;
use bossanova\Redis\Redis;
use bossanova\Jwt\Jwt;

class Auth
{
    use Wget, Post, Request;

    public function __construct(Model $users = NULL)
    {
        if ($users) {
            $this->user = $users;
        } else {
            $this->user = new \models\Users;
        }
    }

    /**
     * Login actions (login and password recovery)
     *
     * @return void
     */
    public function login()
    {
        // Login action
        if ($this->getUser()) {
            if (isset(Render::$urlParam[1]) && Render::$urlParam[1] == 'login') {
                $data = [
                    'success' => 1,
                    'message' => "^^[User already logged in]^^",
                    'url' => Render::getLink(Render::$urlParam[0]),
                ];
            }
        } else {
            // Security container rules
            $validation = $this->getValidation();

            // Too many tries in a short period
            if ($validation[0] > 3 && (microtime(true) - $validation[1]) < 2) {
                // Erro 404
                header("HTTP/1.0 404 Not Found");

                $data = [
                    'error' => 1,
                    'message' => "^^[Invalid login request]^^",
                ];
            } else {
                $captcha = $this->getPost('captcha');

                // Receiving post, captcha is in memory for comparison, 5 erros in a row, compare catch with what was posted
                if (isset($_POST['captcha']) && $validation[2] &&  $validation[0] > 5 && $validation[2] != $captcha) {
                    $data = [
                        'error' => 1,
                        'message' => "^^[Invalid captcha, please try again]^^",
                    ];
                } else {
                    // GET actions
                    if ($this->getRequest('h')) {
                        // First access or recovery link
                        $data = $this->loginHash($this->getRequest('h'));
                    } else {
                        // POST actions
                        if ($this->getPost('username')) {
                            // Actions with the login involved
                            if ($this->getPost('recovery')) {
                                // The user requested a new password
                                $data = $this->loginRecovery();
                            } else {
                                // Perform normal login
                                $data = $this->loginRegister();
                            }
                        } else if ($this->getPost('h')) {
                            // Actions with the hash involved
                            if (! $this->getPost('password')) {
                                // A recovery code has been sent without the password
                                $data = $this->loginHash($this->getPost('h'));
                            } else {
                                // Change password step
                                $data = $this->updatePassword($this->getPost('h'));
                            }
                        } else if ($social = $this->getPost('social')) {
                            if ($social == 'google') {
                                // Facebook token to be analysed
                                $data = $this->googleTokenLogin($this->getPost('token'));
                            } else {
                                // Facebook token to be analised
                                $data = $this->facebookTokenLogin($this->getPost('token'));
                            }
                        } else {
                            if (Render::isAjax()) {
                                // Login forbidden
                                $data = $this->loginForbidden();
                            }
                        }
                    }
                }

                // Replace the message
                if (defined('BOSSANOVA_LOGIN_CAPTCHA') && BOSSANOVA_LOGIN_CAPTCHA) {
                    // Too many tries, request catcha
                    if (isset($data['error']) && $validation[0] > 5) {
                        // Captcha data
                        if ($captcha = $this->captcha()) {
                            // Captcha digit
                            $validation[2] = $captcha[0];
                            // Captch image
                            $data['data'] = $captcha[1];
                        }
                    }
                }
            }

            // Reset counter in any success response
            if (isset($data['success']) && $data['success']) {
                // Reset count
                $this->setValidation([ 0, null, null ]);
            } else {
                // Record of the activity
                $validation[0]++;
                $validation[1] = microtime(true);

                // Persist validations
                $this->setValidation($validation);
            }
        }

        return isset($data) ? $data : null;
    }

    /**
     * This method check if the URL has any defined restriction in the global scope
     *
     * @param  array  $route      Route
     * @return string $restricted First restricted route from the most to less significative argument
     */
    final public function isRestricted(array $access_route)
    {
        // Get restriction defined in the config.inc.php
        $restriction = Config::get('restrictions');

        foreach ($restriction AS $k => $v) {
            $restriction[strtolower(str_replace('-', '_', $k))] = $v;
        }

        // Check the access url against the restriction array definition in config.inc.php
        if (count($access_route)) {
            $route = '';

            foreach ($access_route as $k => $v) {
                // Check all route possibilities
                if ($route) {
                    $route .= '/';
                }
                $route .= strtolower(str_replace('-', '_', $v));

                // Restriction exists for this route
                if (isset($restriction[$route])) {
                    $restricted = $route;
                }
            }

            // Always allow login/logout method
            if (count($access_route) < 3) {
                $param = $access_route[count($access_route) - 1];
                if ($param == 'login' || $param == 'logout') {
                    unset($restricted);
                }
            }
        } else {
            if (isset($restriction[''])) {
                $restricted = '';
            }
        }

        return isset($restricted) ? $restricted : false;
    }

    /**
     * Get the registered user_id
     *
     * @return integer
     */
    private function getUser()
    {
        $jwt = new Jwt;

        return isset($jwt->user_id) ? $jwt->user_id : null;
    }

    /**
     * Perform authentication
     *
     * @param array $row
     * @param string $message
     */
    private function authenticate($row)
    {
        // Load permission services
        $permissions = new \models\Permissions();
        $permissions = new \services\Permissions($permissions);

        // Jwt
        $jwt = new Jwt;

        // Signature
        $signature = hash('sha512', uniqid(mt_rand(), true));

        // Cookie data
        $data = [
            'domain' => Render::getDomain(),
            'user_id' => $row['user_id'],
            'user_login' => $row['user_login'],
            'user_name' => $row['user_name'],
            'user_signature' => isset($row['user_signature']) && $row['user_signature'] ? $row['user_signature'] : '',
            'parent_id' => $row['parent_id'],
            'permission_id' => $row['permission_id'],
            'locale' => $row['user_locale'],
            'expiration' => time(),
            'permissions' => $permissions->getPermissionsById($row['permission_id']),
            'country_id' => isset($row['country_id']) && $row['country_id'] ? $row['country_id'] : 0,
            'hash' => $jwt->sign($signature)
        ];

        // User image
        if (isset($row['user_image']) && $row['user_image']) {
            $data['user_image'] = $row['user_image'];
        }

        // Payload
        $token = $jwt->set($data)->save();

        // Access log
        if (defined('BOSSANOVA_LOG_USER_ACCESS') && BOSSANOVA_LOG_USER_ACCESS) {
            $this->accessLog($row);
        }

        // Redis
        if (class_exists('Redis')) {
            if ($redis = Redis::getInstance()) {
                // Save signature
                $redis->set('hash' . $row['user_id'], $signature);
            }
        }

        return $token;
    }

    /**
     * Register the login
     * @return array
     */
    private function loginRegister()
    {
        // Module
        $module = Render::$urlParam[0];

        // Posted data
        $username = strtolower($this->getPost('username'));
        $password = $this->getPost('password');

        // Load user information
        $row = $this->user->getUserByIdent($username);

        if (! isset($row['user_id']) || ! $row['user_id'] || ! $row['user_status']) {
            // Current message
            $this->message = '^^[Invalid username or password]^^';

            $data = [
                'error' => 1,
                'message' => $this->message,
            ];

            // keep the logs for that transaction
            $this->accessLog(null, "{$this->message}: $username", 0);
        } else {
            // Posted password
            $password = hash('sha512', $password . $row['user_salt']);

            // Check to see if password matches
            if ($password == $row['user_password'] && (strtolower($row['user_login']) == $username || strtolower($row['user_email']) == $username)) {
                // User active
                if ($row['user_status'] == 1) {
                    // Current message
                    $this->message = '^^[Successfully logged in]^^';

                    // Authenticate
                    $token = $this->authenticate($row);

                    // Make sure this is blank
                    $this->user->user_hash = '';
                    $this->user->user_recovery = '';
                    $this->user->user_recovery_date = '';

                    // Mobile device token
                    if (isset($this->getRequest['token']) && $this->getRequest['token']) {
                        $this->user->user_token = $this->getRequest['token'];
                    }

                    // Update user information
                    $this->user->save();

                    // TODO: Implement referer
                    $url = Render::getLink($module);

                    $data = [
                        'success' => 1,
                        'message' => $this->message,
                        'token' => $token,
                        'url' => $url,
                    ];

                    // This is the first access, or your password has expired
                } else if ($row['user_status'] == 2 || $row['user_status'] == 3) {
                    // Update hash
                    $this->user->user_hash = hash('sha512', uniqid(mt_rand(), true));
                    $this->user->save();

                    // Link
                    $url = Render::getLink($module . '/login?h=' . $this->user->user_hash);

                    $data = [
                        'success' => 1,
                        'message' => $row['user_status'] == 2 ? '^^[This is your first access, please select a new password]^^' : '^^[Your password is expired. Please pick a new one]^^',
                        'action' => 'resetPassword',
                        'hash' => $this->user->user_hash,
                    ];
                }
            } else {
                $this->message = "^^[Invalid username or password]^^";

                $data = [
                    'error' => 1,
                    'message' => $this->message,
                ];

                // keep the logs for that transaction
                $this->accessLog($row['user_id'], $this->message, 0);
            }
        }

        return $data;
    }

    /**
     * Send an email with the password recovery instructions
     * @return array $data
     */
    private function loginRecovery()
    {
        // Username
        $username = strtolower($this->getPost('username'));

        // Load user information
        $row = $this->user->getUserByIdent($username);

        if (! isset($row['user_id'])) {
            // Check if the user is found
            $data = [
                'error' => 1,
                'message' => "^^[User not found]^^",
            ];
        } else {
            // Check the user status
            if ($row['user_status'] > 0 && (strtolower($row['user_login']) == $username || strtolower($row['user_email']) == $username)) {
                // Code
                $row['recover_id'] = substr(uniqid(mt_rand(), true), 0, 6);

                // Hash
                $row['user_hash'] = hash('sha512', $row['recover_id']);

                // Full Url
                $row['url'] = Render::getLink(Render::$urlParam[0] . '/login');

                // Save hash in the user table, is is a one time code to access the system
                $this->user->user_hash = $row['user_hash'];
                $this->user->user_recovery = 1;
                $this->user->user_recovery_date = 'NOW()';
                $this->user->save();

                // Send email with instructions
                $filename = defined('EMAIL_RECOVERY_FILE')
                && file_exists(EMAIL_RECOVERY_FILE) ?
                    EMAIL_RECOVERY_FILE : 'resources/texts/recover.txt';

                // Send instructions email to the user
                try {
                    if (! isset($this->mail) || ! $this->mail) {
                        // Get preferable mail adapter
                        $adapter = Config::get('mail');
                        // Create instance
                        $this->mail = new Mail($adapter);
                    }
                    // Prepare the content
                    $content = file_get_contents($filename);
                    $content = $this->mail->replaceMacros($content, $row);
                    $content = $this->mail->translate($content);

                    // Send email to the user
                    $userCommunicationMethodFound = false;

                    // Communication by email
                    if ($row['user_email']) {
                        // Found
                        $userCommunicationMethodFound = true;

                        // From configuration
                        $f = [ MS_CONFIG_FROM, MS_CONFIG_NAME ];

                        // Destination
                        $t = [];
                        $t[] = [ $row['user_email'], $row['user_name'] ];

                        // Send email
                        $this->mail->sendmail($t, EMAIL_RECOVERY_SUBJECT, $content, $f);
                    }

                    // Communication by message
                    if (isset($row['user_token']) && $row['user_token']) {
                        // Found
                        $userCommunicationMethodFound = true;

                        $onesignal = new \bossanova\Message\Onesignal();
                        $onesignal->notify(
                            [ $row['user_token'] ],
                            "Reset your password",
                            "Your recovery code is: {$row['recover_id']}"
                        );
                    }

                    if ($userCommunicationMethodFound) {
                        // Return message
                        $data = [
                            'success' => 1,
                            'message' => "^^[The instructions to reset your password was successfully sent]^^"
                        ];
                    } else {
                        $data = [
                            'error' => 1,
                            'message' => "^^[No communication method found]^^",
                        ];
                    }
                } catch (\Exception $e) {
                    $data = [
                        'error' => 1,
                        'message' => "^^[It was not possible to open the recovery text file]^^ $filename",
                    ];
                }
            } else {
                $data = [
                    'error' => 1,
                    'message' => "^^[User not found]^^", // User disabled
                ];
            }
        }

        return $data;
    }

    /**
     * This method handle user register confirmation, password recovery or hash login
     */
    private function loginHash($hash)
    {
        $hash = preg_replace("/[^a-zA-Z0-9]/", "", $hash);

        // Module
        $module = Render::$urlParam[0];

        // Load user information
        $row = $this->user->getUserByHash($hash);

        // Hash found
        if (isset($row['user_id']) && $row['user_hash'] == $hash) {
            // Action depends on the current user status
            if ($row['user_status'] == 2) {
                // User activation
                $data = [
                    'success' => 1,
                    'message' => '^^[This is your first access and need to choose a new password]^^',
                    'action' => 'resetPassword',
                    'hash' => $this->user->user_hash,
                ];
            } else if ($row['user_status'] == 3) {
                // User password is expired
                $data = [
                    'success' => 1,
                    'message' => '^^[Your password has expired. For security reasons, please choose a new password]^^',
                    'action' => 'resetPassword',
                    'hash' => $this->user->user_hash,
                ];
            } else if ($row['user_status'] == 1) {
                // This block handle password recovery
                if ($row['user_recovery'] == 1) {
                    // Change password
                    $data = [
                        'success' => 1,
                        'message' => '^^[Please choose a new password]^^',
                        'action' => 'resetPassword',
                        'hash' => $this->user->user_hash,
                    ];
                } else if ($row['user_recovery'] == 2) {
                    // Message
                    $this->message = '^^[User authenticated from direct hash]^^';
                    // Special forced authentication by hash
                    $this->authenticate($row);

                    // Force login by hash for specific use
                    $this->user->user_hash = '';
                    $this->user->user_recovery = '';
                    $this->user->user_recovery_date = '';
                    $this->user->user_hash = '';
                    $this->user->save();

                    $data = [
                        'success' => 1,
                        'message' => $this->message,
                        'url' => Render::getLink($module),
                    ];
                } else {
                    // No recovery process on going
                    $data = [
                        'error' => 1,
                        'url' => Render::getLink($module . '/login'),
                    ];
                }
            } else {
                // No user active found
                $data = [
                    'error' => 1,
                    'url' => Render::getLink($module . '/login'),
                ];
            }
        } else {
            // No user found
            if (Render::isAjax()) {
                $data = [
                    'error' => 1,
                    'message' => '^^[Invalid code]^^',
                ];
            } else {
                $data = [
                    'error' => 1,
                    'message' => '^^[This code is not valid or has been expired]^^',
                    'url' => Render::getLink($module . '/login'),
                ];
            }
        }

        return $data;
    }

    /**
     * Update user password from recovery mode
     * @param string $hash
     */
    private function updatePassword($hash)
    {
        // Get hash
        $hash = preg_replace("/[^a-zA-Z0-9]/", "", $hash);

        // Load user information
        $row = $this->user->getUserByHash($hash);

        // Hash found
        if (isset($row['user_id']) && $row['user_hash'] == $hash) {
            if (($row['user_status'] == 1 && $row['user_recovery'] > 0) ||
                ($row['user_status'] == 2) ||
                ($row['user_status'] == 3)) {

                if ($password = $this->getPost('password')) {
                    if (strlen($password) < 5) {
                        $data = [
                            'error' => 1,
                            'message' => "^^[The choosen password is too short]^^",
                        ];
                    } else {
                        // Current password
                        $previousPassword = hash('sha512', $password . $row['user_salt']);

                        // Check if was previouslyl used
                        if ($previousPassword == $row['user_password']) {
                            $data = [
                                'error' => 1,
                                'message' => "^^[Please choose a new password that was not used previously]^^",
                            ];
                        } else {
                            // Set message
                            $this->message = '^^[Password recovery completed]^^';
                            // Password recovery complete
                            $this->authenticate($row);
                            // Update user password
                            $salt = hash('sha512', uniqid(mt_rand(1, mt_getrandmax()), true));
                            $pass = hash('sha512', $password . $salt);
                            // Update user information
                            $this->user->user_salt = $salt;
                            $this->user->user_password = $pass;
                            $this->user->user_hash = '';
                            $this->user->user_recovery = '';
                            $this->user->user_recovery_date = '';
                            $this->user->user_status = 1;
                            $this->user->save();

                            // Feedback
                            $data = [
                                'success' => 1,
                                'message' => "^^[Password updated]^^",
                                'url' => Render::getLink(Render::$urlParam[0]),
                            ];
                        }
                    }
                }
            }
        } else {
            $data = [
                'error' => 1,
                'message' => "^^[Invalid code. If you don't have a valid code, please try to reset your password again]^^",
            ];
        }

        return $data;
    }

    /**
     * Save the user access log
     *
     * @param  integer $user_id
     * @param  string  $message
     * @param  integer $status
     * @return integer $id
     */
    private function accessLog($user_id, $message, $status)
    {
        $column = [
            "user_id" => $user_id,
            "access_message" => $message,
            "access_browser" => $_SERVER['HTTP_USER_AGENT'],
            "access_json" => json_encode($_SERVER),
            "access_status" => $status
        ];

        $accessId = $this->user->setLog($column);

        return $accessId;
    }

    private function loginForbidden()
    {
        $data = [];

        // Forbidden
        header("HTTP/1.0 403 Forbidden");

        if (Render::isAjax()) {
            // Check status of the login page
            $data = [
                'error' => 1,
                'message' => "^^[User not authenticated]^^"
            ];
        }

        return $data;
    }

    /**
     * Captcha
     *
     * @param  string $locale Locale file, must be available at resources/locale/[string].csv
     * @return void
     */
    private function captcha()
    {
        try {
            // Adapted for The Art of Web: www.the-art-of-web.com
            // Please acknowledge use of this code by including this header.

            // initialise image with dimensions of 120 x 30 pixels
            $image = @imagecreatetruecolor(280, 60) or die("Cannot Initialize new GD image stream");

            // set background to white and allocate drawing colours
            $background = imagecolorallocate($image, 0xFF, 0xFF, 0xFF);
            imagefill($image, 0, 0, $background);
            $linecolor = imagecolorallocate($image, 0xCC, 0xCC, 0xCC);
            $textcolor = imagecolorallocate($image, 0x33, 0x33, 0x33);

            // draw random lines on canvas
            for ($i = 0; $i < 6; $i++) {
                imagesetthickness($image, rand(1,4));
                imageline($image, 0, rand(10,40), 280, rand(10,40), $linecolor);
            }

            // add random digits to canvas
            $digit = '';
            for($x = 30; $x <= 280; $x += 70) {
                $digit .= ($num = rand(0, 9));
                imagechar($image, 6, $x, rand(2, 30), $num, $textcolor);
            }

            // display image and clean up
            ob_start();
            imagepng($image);
            imagedestroy($image);
            $image = base64_encode(ob_get_contents());
            ob_end_clean();

            return [$digit, $image];
        } catch (\Exception $e) {
        }

        return false;
    }

    /**
     * Facebook integration
     * @param string $token
     * @return string $json
     */
    private function googleTokenLogin($token)
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
    private function facebookTokenLogin($token)
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

    /**
     * Validations - TODO: implement redis as alternative to sessions
     */
    private function getValidation()
    {
        $validation = isset($_SESSION['bossanovaValidation']) ? $_SESSION['bossanovaValidation'] : [ 0, null, null ];

        return $validation;
    }

    /**
     * Validations - TODO: implement redis as alternative to sessions
     */
    private function setValidation($validation)
    {
        $_SESSION['bossanovaValidation'] = $validation;

        return true;
    }
}
