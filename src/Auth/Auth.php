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
 * Authentication Class
 */
namespace bossanova\Auth;

use bossanova\Config\Config;
use bossanova\Render\Render;
use bossanova\Mail\Mail;
use bossanova\Common\Wget;
use bossanova\Common\Post;
use bossanova\Common\Request;
use bossanova\Common\Ident;
use bossanova\Jwt\Jwt;

class Auth
{
    use Ident, Wget, Post, Request;

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
                if ($captcha && $validation[2] &&  $validation[0] > 5 && $validation[2] != $captcha) {
                    $data = [
                        'error' => 1,
                        'message' => "^^[Invalid captcha, please try again]^^",
                    ];
                } else {
                    if ($this->getPost('username')) {
                        // Recovery flag posted
                        if ($this->getPost('recovery')) {
                            $data = $this->loginRecovery();
                        } else {
                            // Perform normal login
                            $data = $this->loginRegister();
                        }
                    } else if ($this->getRequest('h')) {
                        // Recovery process
                        $data = $this->loginHash($this->getRequest('h'));
                    } else if ($this->getPost('h')) {
                        if ($this->getPost('password')) {
                            // Change password step
                            $data = $this->updatePassword($this->getPost('h'));
                        } else {
                            // Recovery process
                            $data = $this->loginHash($this->getPost('h'));
                        }
                    } else if ($this->getRequest('f')) {
                        // Facebook token to be analised
                        $data = $this->facebookTokenLogin();
                    } else {
                        if (Render::isAjax()) {
                            // Login forbiden
                            $data = $this->loginForbidden();
                        }
                    }
                }

                // Replace the message
                if (defined('BOSSANOVA_LOGIN_CAPTCHA') && BOSSANOVA_LOGIN_CAPTCHA == true) {
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

                // Reset counter in any success response
                if (isset($data['success']) && $data['success']) {
                    // Reset count
                    $this->setValidation([ 0, null, null ]);
                }
            }

            // Record of the activity
            $validation[0]++;
            $validation[1] = microtime(true);

            // Persist validations
            $this->setValidation($validation);
        }

        return isset($data) ? $data : null;
    }

    /**
     * Execute the logout actions
     *
     * @return void
     */
    public function logout()
    {
        // Reset cookie
        header("Set-Cookie: bossanova=null; path=/; SameSite=Lax; expires=0;");

        // Redirect to the main page
        $url = Render::$urlParam[0];

        if ($url != 'login') {
            $url .= '/login';
        }

        // Return
        if (Render::isAjax()) {
            $data = [
                'success' => 1,
                'message' => "^^[The user is now log out]^^",
                'url' => Render::getLink($url),
            ];
        } else {
            header("Location:/$url\r\n");
            exit;
        }

        // Force logout
        if ($user_id = $this->getUser()) {
            $user = new \models\Users;
            $user->get($user_id);
            $user->user_hash = '';
            $user->save();
        }

        return $data;
    }

    /**
     * Helper to get the identification from the user, if is not identified redirec to the login page
     *
     * @return integer $user_id
     */
    final public function getIdent()
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

        // Payload
        $token = $jwt->set([
            'domain' => Render::getDomain(),
            'user_id' => $row['user_id'],
            'parent_id' => $row['parent_id'],
            'permission_id' => $row['permission_id'],
            'locale' => $row['user_locale'],
            'expiration' => time(),
            'permissions' => $permissions->getPermissionsById($row['permission_id']),
            'country_id' => isset($row['country_id']) && $row['country_id'] ? $row['country_id'] : 0,
        ])->save();

        // Access log
        if (defined('BOSSANOVA_LOG_USER_ACCESS')) {
            $this->accessLog($row);
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
        $user = new \models\Users();
        $row = $user->getUserByIdent($username);

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
                    $user->user_hash = '';
                    $user->user_recovery = '';
                    $user->user_recovery_date = '';

                    // Mobile device token
                    if (isset($this->getRequest['token']) && $this->getRequest['token']) {
                        $user->user_token = $this->getRequest['token'];
                    }

                    // Update user information
                    $user->save();

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
                    $user->user_hash = hash('sha512', uniqid(mt_rand(), true));
                    $user->save();

                    // Link
                    $url = Render::getLink($module . '/login?h=' . $user->user_hash);

                    $data = [
                        'success' => 1,
                        'message' => $row['user_status'] == 2 ? '^^[This is your first access, please select a new password]^^' : '^^[Your password is expired. Please pick a new one]^^',
                        'action' => 'resetPassword',
                        'hash' => $user->user_hash,
                        'url' => $url
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
        $user = new \models\Users();
        $row = $user->getUserByIdent($username);

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
                $user->user_hash = $row['user_hash'];
                $user->user_recovery = 1;
                $user->user_recovery_date = 'NOW()';
                $user->save();

                // Send email with instructions
                $filename = defined('EMAIL_RECOVERY_FILE')
                    && file_exists(EMAIL_RECOVERY_FILE) ?
                        EMAIL_RECOVERY_FILE : 'resources/texts/recover.txt';

                // Send instructions email to the user
                try {
                    if (! isset($this->mail) || ! $this->mail) {
                        $this->mail = new Mail;
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
        $user = new \models\Users();
        $row = $user->getUserByHash($hash);

        // Hash found
        if (isset($row['user_id']) && $row['user_hash'] == $hash) {
            // Action depends on the current user status
            if ($row['user_status'] == 2) {
                // Update hash
                $user->user_hash = hash('sha512', uniqid(mt_rand(), true));
                $user->save();
                // User activation
                $data = [
                    'success' => 1,
                    'message' => '^^[This is your first access and need to choose a new password]^^',
                    'action' => 'resetPassword',
                    'hash' => $user->user_hash,
                ];
            } else if ($row['user_status'] == 3) {
                // Update hash
                $user->user_hash = hash('sha512', uniqid(mt_rand(), true));
                $user->save();
                // User password is expired
                $data = [
                    'success' => 1,
                    'message' => '^^[Your password has expired. For security reasons, please choose a new password]^^',
                    'action' => 'resetPassword',
                    'hash' => $user->user_hash,
                ];
            } else if ($row['user_status'] == 1) {
                // This block handle password recovery
                if ($row['user_recovery'] == 1) {
                    // Change password
                    $data = [
                        'success' => 1,
                        'message' => '^^[Please choose a new password]^^',
                        'action' => 'resetPassword',
                        'hash' => $user->user_hash,
                    ];
                } else if ($row['user_recovery'] == 2) {
                    // Message
                    $this->message = '^^[User authenticated from direct hash]^^';
                    // Special forced authentication by hash
                    $this->authenticate($row);

                    // Force login by hash for specific use
                    $user->user_hash = '';
                    $user->user_recovery = '';
                    $user->user_recovery_date = '';
                    $user->user_hash = '';
                    $user->save();

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
        $user = new \models\Users();
        $row = $user->getUserByHash($hash);

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
                            $user->user_salt = $salt;
                            $user->user_password = $pass;
                            $user->user_hash = '';
                            $user->user_recovery = '';
                            $user->user_recovery_date = '';
                            $user->user_status = 1;
                            $user->save();

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
                'message' => "^^[Please try to reset your password again]^^",
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

        $user = new \models\Users();
        $accessId = $user->setLog($column);

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
                    $user = new \models\Users();
                    $row = $user->getUserByFacebookId($result['id']);

                    // User not found by facebook id
                    if (! isset($row['user_id'])) {
                        // Check if this user exists in the database by email
                        if (isset($result['email']) && $result['email']) {
                            // Try to find the user by email
                            $row = $user->getUserByEmail($result['email']);

                            if (isset($row['user_id'])) {
                                // The account is linked now
                                $user->facebook_id = $result['id'];
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

                            if ($row['user_id'] = $user->column($row)->insert()) {
                                // Load user data as object
                                $user->get($row['user_id']);
                            }
                        }
                    }

                    if (isset($row['user_id'])) {
                        // Message
                        $this->message = '^^[User authenticated from facebook token]^^';

                        // Authenticated
                        $this->authenticate($row);

                        // Force login by hash for specific use
                        $user->user_hash = '';
                        $user->user_recovery = '';
                        $user->user_recovery_date = '';
                        $user->user_hash = $this->access_token;

                        // Mobile device token
                        if ($this->getRequest('token')) {
                            $user->user_token = $this->getRequest('token');
                        }

                        // Update user information
                        $user->save();

                        $data = [
                            'success' => 1,
                            'message' => $this->message,
                            'url' => Render::getLink(Render::$urlParam[0]),
                        ];
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
     * Set the user initial locale
     *
     * @param  string $locale Locale file, must be available at resources/locale/[string].csv
     * @return void
     */
    private function setLocale($locale)
    {
        if (file_exists("resources/locales/$locale.csv")) {
            return $locale;
        }
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
