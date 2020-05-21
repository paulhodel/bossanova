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

interface MailService
{
    public function login(array $config);

    public function addTo($email, $name = null);

    public function addBCC($email, $name = null);

    public function addAddress($email, $name = null);

    public function setFrom($email, $name);

    public function setReplyTo($replyTo);

    public function setSubject($subject);

    public function setHtml($html);

    public function setText($text);

    public function addAttachment($path, $name);

    public function setDebug($value = false);

    public function send();

    public function error();
}
