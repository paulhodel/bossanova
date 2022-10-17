<?php

namespace bossanova\Config;

class Config
{
    /**
     * Get config
     *
     * @param  string $configName
     * @return array
     */
    public static function get(String $configName)
    {
        $config = [];

        $fileName = './config/' . $configName . '.php';
        if (file_exists($fileName)) {
            $config = include $fileName;
        }

        return $config;
    }
}
