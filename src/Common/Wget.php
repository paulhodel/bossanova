<?php

namespace bossanova\Common;

trait Wget
{
    /**
     * Wget
     *
     * @param  string $url
     * @param  string $asArray  As array
     * @return array
     */
    public function wget($url, $asArray = true)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        $result = curl_exec($ch);
        curl_close($ch);

        return json_decode($result, $asArray);
    }
}
