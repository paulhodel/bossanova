<?php

namespace bossanova\Translate;

use bossanova\Redis\Redis;

class Translate
{
    // Default locale
    public static $locale = 'en_GB';

    /**
     * Start the output buffering with the callback function
     *
     * @param string  $logDirectory File path to the logging directory
     * @param integer $severity One of the pre-defined severity constants
     * @return void
     */
    public static function start($locale = null)
    {
        if (isset($locale) && $locale) {
            self::$locale = $locale;
        }

        // Callback for the translation
        ob_start(function($b) {
            // Skip translations in case of binary files
            $matches = headers_list();
            $matches = array_values(preg_grep('/^Content-type: (\w+)/i', headers_list()));
            if (isset($matches[0])) {
                preg_match('/^Content-type: (\w+)/i', $matches[0], $result);
            }

            // Translate only the text content
            if (isset($result[1]) && $result[1] != 'text') {
                return $b;
            } else {
                return self::run($b, self::$locale);
            }
        });
    }

    /**
     * Callback function
     *
     * @param string $buffer Output buffer
     * @return string $result Return buffer with all translations
     */
    public static function run($buffer, $locale = null, $clearCache = false)
    {
        if ($locale) {
            // Load file
            $dictionary = self::loadfile($locale, $clearCache);
        } else {
            $dictionary = [];
        }

        // Processing buffer
        $result = '';
        $index  = '';
        $key    = '';

        $index_found = 0;

        for ($i = 0; $i < strlen($buffer); $i++) {
            if (strlen($buffer) > $i+2) {
                // Find one possible end word mark
                if ($index_found && substr($buffer, $i, 3) == ']^^') {
                    $i = $i + 3;
                    $index_found = 0;
                }

                // Find one possible word mark
                if (substr($buffer, $i, 3) == '^^[') {
                    $i = $i + 3;
                    $index_found = 1;
                }
            }

            // Check the
            if ($index_found == 0) {
                // Check if there any word to be processed
                if ($index) {
                    // Find the hash based on index
                    $key = md5($index);

                    // Translate word
                    if (isset($dictionary[$key])) {
                        $result .= $dictionary[$key];
                    } else {
                        $result .= $index;
                    }

                    $index = '';
                }

                // Append to the final result
                if (substr($buffer, $i, 1) !== '') {
                    $result .= substr($buffer, $i, 1);
                }
            } else {
                if (substr($buffer, $i, 1) !== '') {
                    $index .= substr($buffer, $i, 1);
                }
            }
        }

        // Non finished translation tag
        if ($index) {
            $result .= $index;
        }

        return $result;
    }

    /**
     * Load dictionary information, index and cache usign APC.
     *
     * @param string $locale dicionary name.
     * @return void
     */
    public static function loadfile($locale, $clearCache = false)
    {
        $dictionary = [];

        if (class_exists('Redis') && ! $clearCache) {
            if ($redis = Redis::getInstance()) {
                if ($data = $redis->get('dictionary')) {
                    $data = json_decode($data, true);
                    $currentLocale = $data[0];
                    $dictionary = $data[1];
                }
            }
        }
        $dictionary = [];

        // Open the file and load all words in memory
        if ((! isset($dictionary) || ! $dictionary) || ($locale != $currentLocale)) {
            if (file_exists("resources/locales/{$locale}.csv")) {
                $dic = fopen("resources/locales/{$locale}.csv", "r");

                while (!feof($dic)) {
                    // Open word index and translate word
                    $buffer = fgets($dic);
                    $buffer = explode("|", $buffer);

                    if ($buffer[0]) {
                        // Make sure to remove all white spaces and create a index based on the hash
                        $val = isset($buffer[1]) && trim($buffer[1]) ? $buffer[1] : $buffer[0];
                        $dictionary[md5(trim($buffer[0]))] = trim($val);
                    }
                }

                fclose($dic);
            } else {
                return false;
            }

            if (class_exists('Redis')) {
                if ($redis) {
                    $redis->set('dictionary', json_encode([ $locale, $dictionary ]));
                }
            }
        }

        return $dictionary;
    }
}
