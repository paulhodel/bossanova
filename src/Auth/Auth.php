<?php
/**
 * (c) 2013 Bossanova PHP Framework 4
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

use bossanova\Render\Render;
use bossanova\Mail\Mail;
use bossanova\Error\Error;
use bossanova\Common\Wget;

class Auth
{
    use Wget;

    /**
     * Login actions (login and password recovery)
     *
     * @return void
     */
    public function login()
    {
        $data = '';

        // Login action
        if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id']) {
            if (isset(Render::$urlParam[1]) && Render::$urlParam[1] == 'login') {
                $data = [
                    'success' => 1,
                    'message' => "^^[User already logged in]^^",
                    'url' => Render::getLink(Render::$urlParam[0]),
                    'token' => $_SESSION['token'],
                ];
            }
        } else {
            // Security container rules
            if (! isset($_SESSION['bossanova_security']) || ! $_SESSION['bossanova_security']) {
                $_SESSION['bossanova_security'] = [ 0, null, null ];
            }

            // Too many tries in a short period
            if ($_SESSION['bossanova_security'][0] > 3 && (microtime(true) - $_SESSION['bossanova_security'][1]) < 2) {
                // Erro 404
                header("HTTP/1.0 404 Not Found");

                $data = [
                    'error' => 1,
                    'message' => "^^[Invalid login request]^^",
                ];
            } else {
                $captcha = isset($_POST['captcha']) ? $_POST['captcha'] : null;

                // Receiving post, captcha is in memory for comparison, 5 erros in a row, compare catch with what was posted
                if (isset($_POST) && count($_POST) && $_SESSION['bossanova_security'][2] &&  $_SESSION['bossanova_security'][0] > 5 && $_SESSION['bossanova_security'][2] != $captcha) {
                    $data = [
                        'error' => 1,
                        'message' => "^^[Invalid captcha, please try again]^^",
                    ];
                } else {
                    if (isset($_POST['username'])) {
                        // Recovery flag posted
                        if (isset($_POST['recovery']) && $_POST['recovery']) {
                            $data = $this->loginRecovery();
                        } else {
                            // Perform normal login
                            $data = $this->loginRegister();
                        }
                    } else if (isset($_REQUEST['h']) && $_REQUEST['h']) {
                        // Recovery process
                        if (isset($_POST['password'])) {
                            // Change password step
                            $data = $this->updatePassword($_REQUEST['h']);
                        } else {
                            // Identify recovery token
                            $data = $this->loginHash($_REQUEST['h']);
                        }
                    } else if (isset($_REQUEST['f']) && $_REQUEST['f']) {
                        // Facebook token to be analised
                        $data = $this->facebookTokenLogin($_REQUEST['f']);
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
                    if (isset($data['error']) && $_SESSION['bossanova_security'][0] > 5) {
                        // Captcha data
                        if ($captcha = $this->captcha()) {
                            // Captcha digit
                            $_SESSION['bossanova_security'][2] = $captcha[0];
                            // Captch image
                            $data['data'] = $captcha[1];
                        }
                    }
                }

                // Reset counter in any success response
                if (isset($data['success']) && $data['success']) {
                    // Reset count
                    $_SESSION['bossanova_security'] = [ 0, null, null ];
                }
            }

            // Record of the activity
            $_SESSION['bossanova_security'][0]++;
            $_SESSION['bossanova_security'][1] = microtime(true);
        }

        return $data;
    }

    /**
     * Execute the logout actions
     *
     * @return void
     */
    public function logout()
    {
        // Force logout
        if ($user_id = $this->getUser()) {
            $user = new \models\Users;
            $user->get($user_id);
            $user->user_hash = '';
            $user->save();
        }

        // Removing session
        $_SESSION = [];

        // Destroy session
        session_destroy();
        session_commit();

        // Removing cookie
        $this->destroySession();

        // Redirect to the main page
        $url = Render::$urlParam[0];

        if ($url != 'login') {
            $url .= '/login';
        }

        // Return
        if (Render::isAjax()) {
            $data = [
                'error' => 1,
                'message' => "^^[The user is now log out]^^",
                'url' => Render::getLink($url),
            ];
        } else {
            header("Location:/$url\r\n");
            exit;
        }

        return $data;
    }

    /**
     * Get the registered user_id
     *
     * @return integer $user_id
     */
    public function getUser()
    {
        return (isset($_SESSION['user_id'])) ? $_SESSION['user_id'] : 0;
    }

    /**
     * Helper to get the identification from the user, if is not identified redirec to the login page
     *
     * @return integer $user_id
     */
    public function getIdent()
    {
        if (! $this->getUser()) {
            // Try to recover session from cookie
            $this->sessionRecovery();
        }

        // After all process check if the user is logged
        if (! $this->getUser()) {
            $param = isset(Render::$urlParam[1]) ? Render::$urlParam[1] : '';

            // Redirect the user to the login page
            if ($param != 'login') {
                // Keep the reference to redirect to this page after the login
                if (! isset($_SESSION['HTTP_REFERER']) || ! $_SESSION['HTTP_REFERER']) {
                    $_SESSION['HTTP_REFERER'] = '/' . implode("/", Render::$urlParam);
                }

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
     * Recover session from cookie
     *
     * @param array $row
     * @param string $message
     */

    public function sessionRecovery()
    {
        $data = [];

        // Check if the cookie for this user is registered and try to recover the userid
        if ($access_token = $this->initSession()) {
            // Cookie information
            $cookie = json_decode(base64_decode($_COOKIE['bossanova']));

            // User stored in the cookies
            $user_id = isset($cookie->user_id) ? $cookie->user_id : 0;

            // User Identification
            if ($user_id) {
                // Load user information
                $user = new \models\Users();
                $row = $user->getUserByHash($access_token);

                if (isset($row['user_id']) && $row['user_id'] == $user_id && $row['user_hash'] == $access_token) {
                    // Authenticate user
                    $this->authenticate($row, 'Recovery session from cookie', true);

                    // Set a new sessionId
                    $user->user_hash = $this->access_token;
                    $user->save();

                    $data = [
                        'success' => 1,
                        'message' => "^^[Session recovered from cookie]^^",
                        'token' => $this->access_token,
                    ];
                }
            }
        }
    }

    /**
     * Perform authentication
     *
     * @param array $row
     * @param string $message
     */
    private function authenticate($row, $message = '', $keepAlive = false)
    {
        // Access token
        $this->access_token = $this->setSession($row['user_id'], $keepAlive);

        // Load permission services
        $permissions = new \services\Permissions();

        // Registering permissions
        $_SESSION['permission'] = $permissions->getPermissionsById($row['permission_id']);

        // Check if the user is a superuser
        $_SESSION['superuser'] = $permissions->isPermissionsSuperUser($row['permission_id']);

        // User session
        $_SESSION['user_id'] = $row['user_id'];

        // Permission
        $_SESSION['permission_id'] = $row['permission_id'];

        // Register parent
        $_SESSION['parent_id'] = $row['parent_id'];

        // keep the logs for that transaction
        $_SESSION['user_access_id'] = $this->accessLog($row['user_id'], $message, 1);

        // Token
        $_SESSION['token'] = $this->access_token;

        // Locale registration
        $this->setLocale($row['user_locale']);
    }

    /**
     * Load the access token from the session stored in the cookies
     *
     * @return string $token
     */
    private function initSession()
    {
        // If the cookie is already defined
        if (isset($_COOKIE['bossanova'])) {
            // Extract the access token from the cookie
            $cookie = json_decode(base64_decode($_COOKIE['bossanova']));

            // Define the access token for this session
            $this->access_token = isset($cookie->id) ? $cookie->id : 0;
        } else {
            // No cookie defined
            $this->access_token = 0;
        }

        return $this->access_token;
    }

    /**
     * Load all permissions for a user_id based on his permission_id
     *
     * @param  integer $userId
     * @param  boolean $keepAlive
     * @return string  $token
     */
    private function setSession($userId, $keepAlive)
    {
        try {
            // Regenerate
            session_regenerate_id();

            // Generate hash
            $this->access_token = session_id();

            if ($keepAlive) {
                // Check headers
                if (headers_sent()) {
                    throw Exception("Http already sent.");
                }

                // Save cookie
                $data = json_encode([
                    'domain' => Render::getDomain(),
                    'user_id' => $userId,
                    'id' => $this->access_token,
                    'date' => time()
                ]);
                $cookie_value = base64_encode($data);

                // Default for 7 days
                $expire = time() + 86400 * 7;
                setcookie('bossanova', $cookie_value, $expire, '/');
            }
        } catch (\Exception $e) {
            if (class_exists("Error")) {
                Error::handler("Http already sent.", $e);
            } else {
                echo "Http already sent.";
            }
        }

        return $this->access_token;
    }

    /**
     * Destroy the session and make sure destroy cookies
     *
     * @return void
     */
    private function destroySession()
    {
        if (isset($_COOKIE['bossanova'])) {
            setcookie('bossanova', '', -1, '/');
        }
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
        $username = $_POST['username'];
        $password = $_POST['password'];

        // Load user information
        $user = new \models\Users();
        $row = $user->getUserByIdent($username);

        if (! isset($row['user_id']) || ! $row['user_id'] || ! $row['user_status']) {
            $data = [
                'error' => 1,
                'message' => "^^[Invalid username or password]^^",
            ];

            // keep the logs for that transaction
            $this->accessLog(null, "^^[Invalid Username]^^: $username", 0);
        } else {
            // Posted password
            $password = hash('sha512', $password . $row['user_salt']);

            // Check to see if password matches
            if ($password == $row['user_password']) {
                // User active
                if ($row['user_status'] == 1) {
                    // Keep session alive by the use of cookies
                    $keepAlive = (isset($_POST['remember'])) ? 1 : 0;

                    // Authenticate
                    $this->authenticate($row, '^^[Successfully logged in]^^', $keepAlive);

                    // Update hash
                    $user->user_hash = $this->access_token;
                    $user->user_recovery = '';
                    $user->user_recovery_date = '';

                    // Mobile token
                    if (isset($_GET['token']) && $_GET['token']) {
                        $user->user_token = $_GET['token'];
                    }

                    $user->save();

                    // Redirection to the referer
                    if (isset($_SESSION['HTTP_REFERER'])) {
                        $url = $_SESSION['HTTP_REFERER'];
                        unset($_SESSION['HTTP_REFERER']);
                    } else {
                        $url = Render::getLink($module);
                    }

                    $data = [
                        'success' => 1,
                        'message' => "^^[Successfully logged in]^^",
                        'token' => $this->access_token,
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
                        'action' => 'resetPassword',
                        'hash' => $user->user_hash,
                        'url' => $url
                    ];
                }
            } else {
                $data = [
                    'error' => 1,
                    'message' => "^^[Invalid username or password]^^",
                ];

                // keep the logs for that transaction
                $this->accessLog($row['user_id'], $data['message'], 0);
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
        $username = $_POST['username'];

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
            if (! $row['user_status']) {
                $data = [
                    'error' => 1,
                    'message' => "^^[User not found]^^", // User disabled
                ];
            } else {
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
                $filename = defined('EMAIL_RECOVERY_FILE') && file_exists(EMAIL_RECOVERY_FILE)
                ? EMAIL_RECOVERY_FILE : 'resources/texts/recover.txt';

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
                            'message' => "^^[The instructions to reset your password was successfully sent]^^",
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

                // Destroy any existing cookie
                $this->destroySession();
            }
        }

        return $data;
    }


    /**
     * This method handle user register confirmation, password recovery or hash login
     */
    private function loginHash($hash)
    {
        // Module
        $module = Render::$urlParam[0];

        // Load user information
        $user = new \models\Users();
        $row = $user->getUserByHash($hash);

        // Hash found
        if (isset($row['user_id'])) {
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
                    // Update hash
                    $user->user_hash = hash('sha512', uniqid(mt_rand(), true));
                    $user->save();
                    // Change password
                    $data = [
                        'success' => 1,
                        'message' => '^^[Please choose a new password]^^',
                        'action' => 'resetPassword',
                        'hash' => $user->user_hash,
                    ];
                } else if ($row['user_recovery'] == 2) {
                    // Special forced authentication by hash
                    $this->authenticate($row, '^^[User authenticated from direct hash]^^', true);

                    // Force login by hash for specific use
                    $user->user_hash = '';
                    $user->user_recovery = '';
                    $user->user_recovery_date = '';
                    $user->user_hash = $this->access_token;
                    $user->save();

                    $data = [
                        'success' => 1,
                        'message' => "^^[User authenticated]^^",
                        'url' => Render::getLink($module),
                        'token' => $this->access_token,
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

    private function updatePassword($hash)
    {
        // Module
        $module = Render::$urlParam[0];

        // Load user information
        $user = new \models\Users();
        $row = $user->getUserByHash($hash);

        // Hash found
        if (isset($row['user_id'])) {
            if (($row['user_status'] == 1 && $row['user_recovery'] > 0) ||
                ($row['user_status'] == 2) ||
                ($row['user_status'] == 3)) {

                if (isset($_POST['password']) && $_POST['password']) {
                    if (strlen($_POST['password']) < 5) {
                        $data = [
                            'error' => 1,
                            'message' => "^^[The chossen password is too short]^^",
                        ];
                    } else {
                        // Current password
                        $password = hash('sha512', $_POST['password'] . $row['user_salt']);

                        // Check if was previouslyl used
                        if ($password != $row['user_password']) {
                            // Password recovery complete
                            $this->authenticate($row, '^^[Password recovery completed]^^', true);

                            // Update user password
                            $salt = hash('sha512', uniqid(mt_rand(1, mt_getrandmax()), true));
                            $pass = hash('sha512', $_POST['password'] . $salt);

                            $user->user_salt = $salt;
                            $user->user_password = $pass;
                            $user->user_hash = '';
                            $user->user_recovery = '';
                            $user->user_recovery_date = '';
                            $user->user_status = 1;
                            $user->save();

                            $data = [
                                'success' => 1,
                                'message' => "^^[Password updated]^^",
                                'url' => Render::getLink($module),
                                'token' => $this->access_token,
                            ];
                        } else {
                            $data = [
                                'error' => 1,
                                'message' => "^^[Please choose a new password that was not used previously]^^",
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
     * Set the user initial locale
     *
     * @param  string $locale Locale file, must be available at resources/locale/[string].csv
     * @return void
     */
    public function setLocale($locale)
    {
        if (file_exists("resources/locales/$locale.csv")) {
            // Update the session language reference
            $_SESSION['locale'] = $locale;

            // Exclude the current dictionary words
            unset($_SESSION['dictionary']);
        }
    }

    /**
     * Captcha
     *
     * @param  string $locale Locale file, must be available at resources/locale/[string].csv
     * @return void
     */
    public function captcha()
    {
        try {
            // Adapted for The Art of Web: www.the-art-of-web.com
            // Please acknowledge use of this code by including this header.

            // initialise image with dimensions of 120 x 30 pixels
            $image = @imagecreatetruecolor(220, 50) or die("Cannot Initialize new GD image stream");

            // set background to white and allocate drawing colours
            $background = imagecolorallocate($image, 0xFF, 0xFF, 0xFF);
            imagefill($image, 0, 0, $background);
            $linecolor = imagecolorallocate($image, 0xCC, 0xCC, 0xCC);
            $textcolor = imagecolorallocate($image, 0x33, 0x33, 0x33);

            // draw random lines on canvas
            for ($i = 0; $i < 6; $i++) {
                imagesetthickness($image, rand(1,4));
                imageline($image, 0, rand(10,40), 220, rand(10,40), $linecolor);
            }

            // add random digits to canvas
            $digit = '';
            for($x = 30; $x <= 200; $x += 50) {
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
     * @return json
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
                        // Authenticated
                        $this->authenticate($row, '^^[User authenticated from facebook token]^^', true);

                        // Force login by hash for specific use
                        $user->user_hash = '';
                        $user->user_recovery = '';
                        $user->user_recovery_date = '';
                        $user->user_hash = $this->access_token;

                        // Mobile token
                        if (isset($_GET['token']) && $_GET['token']) {
                            $user->user_token = $_GET['token'];
                        }

                        $user->save();

                        $data = [
                            'success' => 1,
                            'message' => "^^[User authenticated]^^",
                            'url' => Render::getLink(Render::$urlParam[0]),
                            'token' => $this->access_token,
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
}
