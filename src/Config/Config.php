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
 * Config Library
 */
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
