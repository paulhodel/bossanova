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
 * Sendgrid Adapter
 */
namespace bossanova\Mail;

class AdapterSendgrid implements MailService
{
    /**
     * Sendmail instance
     *
     * @var $instance
     */
    public $instance = null;
    public $personalization = null;
    public $debug = false;

    public function login(array $config)
    {
        $this->instance = new \SendGrid(MS_CONFIG_KEY);

        $this->personalization = new \SendGrid\Mail\Mail();
    }

    public function addTo($email, $name = null)
    {
        $this->personalization->addTo($email, $name);
    }

    public function addAddress($email, $name = null)
    {
        $this->personalization->addTo($email, $name);
    }

    public function setFrom($email, $name = null)
    {
        $this->personalization->setFrom($email, $name);
    }

    public function setReplyTo($email, $name = null)
    {
        $this->personalization->setReplyTo($email);
    }

    public function setSubject($subject)
    {
        $this->personalization->setSubject($subject);
    }

    public function setHtml($html)
    {
        $this->personalization->addContent("text/html", $html);
    }

    public function setText($text)
    {
        $this->personalization->addContent("text/plain", $text);
    }

    public function addAttachment($path, $name)
    {
        //$attachment = new \Sendgrid\Attachment();
        //$this->adapter->addAttachment($path, $name);
    }

    public function setDebug($value = false)
    {
        $this->debug = $value;
    }

    public function send()
    {
        $response = $this->instance->send($this->personalization);

        if ($this->debug == true) {
            echo $response->statusCode();
            echo $response->body();
            echo $response->headers();
        }
    }

    public function error()
    {
        // @TODO: return error
    }
}
