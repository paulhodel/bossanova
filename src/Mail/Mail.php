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
 * Mail Library
 */
namespace bossanova\Mail;

use bossanova\Translate\Translate;
use bossanova\Error\Error;

class Mail
{
    /**
     * Sendmail adapter
     *
     * @var $adapter
     */
    private $adapter = null;

    /**
     * Receive adapter or create a instance of the default adapter
     *
     * @param  object $mailService
     * @return $this
     */
    public function __construct(MailService $mailService = null)
    {
        if (isset($mailService)) {
            $this->adapter = $mailService;
        } else {
            // Default adapter
            $adapter = ucfirst(defined('MS_CONFIG_TYPE') && MS_CONFIG_TYPE ? MS_CONFIG_TYPE : 'phpmailer');

            // Connect to the default adapter
            try {
                $component = 'bossanova\\Mail\\Adapter' . $adapter;
                $this->adapter = new $component;
            } catch (\Exception $e) {
                Error::handler("^^[It was not possible to find the mail adapter]^^" . $component, $e);
                exit;
            }
        }
    }

    /**
     * Send messages by email
     *
     * @return void
     */
    public function sendmail($to, $subject, $html, $from, $files = null)
    {
        // Configuration
        $config = array();

        // General configuration
        $config['MS_CONFIG_HOST'] = defined('MS_CONFIG_HOST') ? MS_CONFIG_HOST : 'localhost';
        $config['MS_CONFIG_PORT'] = defined('MS_CONFIG_PORT') ? MS_CONFIG_PORT : '';
        $config['MS_CONFIG_USER'] = defined('MS_CONFIG_USER') ? MS_CONFIG_USER : '';
        $config['MS_CONFIG_PASS'] = defined('MS_CONFIG_PASS') ? MS_CONFIG_PASS : '';
        $config['MS_CONFIG_AUTH'] = defined('MS_CONFIG_AUTH') ? MS_CONFIG_AUTH : '';

        // Sendgrid configuration
        $config['MS_CONFIG_KEY'] = defined('MS_CONFIG_KEY') ? MS_CONFIG_KEY : '';
        $config['MS_CONFIG_USR'] = defined('MS_CONFIG_USR') ? MS_CONFIG_USR : '';

        // SMTP or API login information
        $this->adapter->login($config);

        if (isset($this->adapter)) {
            if (is_array($to)) {
                foreach ($to as $k => $v) {
                    // Set who the message is to be sent to
                    if (is_array($v)) {
                        $this->adapter->addTo($v[0], $v[1]);
                    } else {
                        $this->adapter->addTo($v);
                    }
                }
            } else {
                $this->adapter->addTo($to);
            }

            if (is_array($from)) {
                $this->adapter->setFrom($from[0], $from[1]);
            } else {
                $this->adapter->setFrom($from);
            }

            $this->adapter->setSubject($subject);
            $this->adapter->setText(strip_tags($html));
            $this->adapter->setHtml($html);

            if (isset($files)) {
                foreach ($files as $k => $v) {
                    if (isset($v['path']) && file_exists($v['path'])) {
                        $this->adapter->setAttachments($v['path'], $v['name']);
                    }
                }
            }

            $this->adapter->send();

            return $this->adapter;
        }
    }

    /**
     * Email translation helper
     *
     * @param  string $content
     * @param  array  $language
     * @return string
     */
    public function translate($content, $locale = null)
    {
        $translate = new Translate;
        $content = $translate->run($content, $locale);

        return $content;
    }

    /**
     * This method replace macros in one array in the text given
     *
     * @param  string $txt    Original string
     * @param  array  $macros Array of macros
     * @return string
     */
    public function replaceMacros($txt, array $macros)
    {
        foreach ($macros as $k => $v) {
            $txt = str_replace("[$k]", "$v", $txt);
        }

        return $txt;
    }
}
