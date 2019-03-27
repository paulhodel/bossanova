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
 * Onesignal notification
 */
namespace bossanova\Message;

class Onesignal
{
    public $appId = ONESIGNAL_APPID;
    public $appKey = ONESIGNAL_APPKEY;

    public function __construct($appId = null, $appkey = null)
    {
        if (isset($appId) && $appId) {
            $this->appId = $appId;
        }

        if (isset($appKey) && $appKey) {
            $this->appKey = $appKey;
        }
    }

    public function notify($recipients, $title, $message)
    {
        if (! $this->appId) {
            $data = [
                'error' => 1,
                'message' => 'No appId defined',
            ];
        } else {
            // Notification
            $fields = array(
                'app_id' => $this->appId,
                'headings' => [ "en" => $title ],
                'contents' => [ "en" => $message ],
                'include_player_ids' => $recipients
            );

            $fields = json_encode($fields);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Postman-token: ' . $this->appId,
                'Cache-control: no-cache',
                'Content-type: application/json',
                'Authorization: Basic ' . $this->appKey
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

            $response = curl_exec($ch);
            curl_close($ch);
        }
    }
}