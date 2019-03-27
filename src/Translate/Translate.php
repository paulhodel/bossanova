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
 * Translate Library
 */
namespace bossanova\Translate;

use bossanova\Error\Error;

class Translate
{
    // Keep the same instance all over the PHP scripting
    public static $instance = null;

    /**
     * Start the output buffering with the callback function
     *
     * @param string  $logDirectory File path to the logging directory
     * @param integer $severity One of the pre-defined severity constants
     * @return void
     */
    public function __construct()
    {
        if (!self::$instance) {
            self::$instance = $this;
            // Callback for the translation
            ob_start(function($b) {
                return $this->run($b);
            });
        }

        return self::$instance;
    }

    // Cannot be clonned
    private function __clone()
    {
    }

    /**
     * Load dictionary information, index and cache usign APC.
     *
     * @param string $locale dicionary name.
     * @return void
     */
    public function load($locale)
    {
        // Loading dictionary and caches everything

        try {
            // New dictionary to be loaded?
            if (!isset($_SESSION['locale']) || $_SESSION['locale'] != $locale) {
                // Remove any dictionary from the session
                unset($_SESSION['dictionary']);
                unset($_SESSION['locale']);
            }

            // Assembly dictionary
            if (!isset($_SESSION['dictionary']) || !$_SESSION['dictionary'] || !count($_SESSION['dictionary'])) {
                // Check if the language file exists
                if (file_exists("resources/locales/{$locale}.csv")) {
                    // Open the file and load all words in memory
                    $dictionary = $this->loadfile($locale);

                    if (count($dictionary)) {
                        // Keep dictionary in the session
                        $_SESSION['dictionary'] = $dictionary;
                        $_SESSION['locale'] = $locale;
                    }
                } else {
                    if (isset($_SESSION['locale'])) {
                        unset($_SESSION['locale']);
                    }

                    throw new \Exception("Locale file not found " . "resources/locales/{$locale}.csv");
                }
            }
        } catch (\Exception $e) {
            Error::handler("Translation error", $e);
        }
    }

    /**
     * Load dictionary information, index and cache usign APC.
     *
     * @param string $locale dicionary name.
     * @return void
     */
    public function loadfile($locale)
    {
        // Open the file and load all words in memory
        $dictionary = array();

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
        }

        return $dictionary;
    }
    /**
     * Reload dictionary information, index and cache usign APC.
     *
     * @param string $locale dictionary name.
     * @return void
     */
    public function reload($locale)
    {
        // Unset any dictionary from the session
        unset($_SESSION['dictionary']);

        // Unset locale
        unset($_SESSION['locale']);

        // Reload again
        $this->load($locale);
    }

    /**
     * Callback function
     *
     * @param string $buffer Output buffer
     * @return string $result Return buffer with all translations
     */
    public function run($buffer, $locale = null)
    {
        if (! isset($locale)) {
            $dictionary = isset($_SESSION['dictionary']) ? $_SESSION['dictionary'] : [];
        } else {
            $dictionary = $this->loadfile($locale);
        }

        // Processing buffer
        $result = '';
        $index  = '';
        $key    = '';

        $index_finded = 0;

        for ($i = 0; $i < strlen($buffer); $i++) {
            if (strlen($buffer) > $i+2) {
                // Find one possible end word mark
                if ($buffer{$i} == ']') {
                    // Check if this is a start macro, end macro (real macro to be translated)
                    if ($buffer{$i+1} == '^') {
                        // start to counting or keep saving characters till the end of this word
                        if ($buffer{$i+2} == '^') {
                            if ($index_finded) {
                                $i = $i + 3;

                                $index_finded = 0;
                            }
                        }
                    }
                }

                // Find one possible word mark
                if ($buffer{$i} == '^') {
                    // Check if this is a start macro, end macro (real macro to be translated)
                    if ($buffer{$i+1} == '^') {
                        // start to counting or keep saving characters till the end of this word
                        if ($buffer{$i+2} == '[') {
                            $i = $i + 3;

                            $index_finded = 1;
                        }
                    }
                }
            }

            // Check the
            if ($index_finded == 0) {
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
                if (isset($buffer{$i})) {
                    $result .= $buffer{$i};
                }
            } else {
                if (isset($buffer{$i})) {
                    // Capturing a new word
                    $index .= $buffer{$i};
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
     * Mapping all translation references
     *
     */
    public function search()
    {
        $words = $this->searchFolder('models');
        foreach ($words as $k => $v) {
            echo "$v|\n";
        }

        $words = $this->searchFolder('modules');
        foreach ($words as $k => $v) {
            echo "$v|\n";
        }

        $words = $this->searchFolder('services');
        foreach ($words as $k => $v) {
            echo "$v|\n";
        }

        // Search templates
        $words = $this->searchFolder('templates');
        foreach ($words as $k => $v) {
            echo "$v|\n";
        }
    }

    /**
     * Search dir by dir all files looking for texts to be translates
     *
     * @param string $buffer Output buffer
     * @return string $result Return buffer with all translations
     */
    private function searchFolder($folder)
    {
        // Keep all to be translated text references
        $words = array();

        // Search all folders reading all files
        if ($dh = opendir($folder)) {
            while (false !== ($file = readdir($dh))) {
                if (substr($file, 0, 1) != '.') {
                    // Searching in a subfolder
                    if (is_dir($folder . '/' . $file)) {
                        if (($file != 'dev') && ($file != 'bin') && ($file != 'doc') && ($file != 'img')) {
                            $words = array_merge($words, $this->search_dir($folder . '/' . $file));
                        }
                    } else {
                        // Merging results
                        $words = array_merge($words, $this->getWords(file_get_contents($folder . '/' . $file)));
                    }
                }
            }

            // Close resource
            closedir($dh);
        }

        return $words;
    }

    /**
     * Search for words to be translate
     *
     * @param string $buffer full file text
     * @return string $result words to be translated
     */
    private function getWords($buffer)
    {
        // Processing buffer
        $result = array();
        $index  = '';
        $key    = '';

        $index_finded = 0;

        for ($i = 0; $i < strlen($buffer); $i++) {
            // Find one possible word mark
            if (($buffer{$i} == '^') && (strlen($buffer) > $i+2)) {
                // Check if this is a start macro, end macro (real macro to be translated)
                if ($buffer{$i+1} == '^') {
                    // start to counting or keep saving characters till the end of this word
                    if ($buffer{$i+2} == '[') {
                        $index_finded = 1;
                        $i = $i + 3;
                    }
                }
            }

            // Find one possible end word mark
            if (($buffer{$i} == ']') && (strlen($buffer) > $i+2)) {
                // Check if this is a start macro, end macro (real macro to be translated)
                if ($buffer{$i+1} == '^') {
                    // start to counting or keep saving characters till the end of this word
                    if ($buffer{$i+2} == '^') {
                        $index_finded = 0;
                        $i = $i + 3;
                    }
                }
            }

            // Check the
            if ($index_finded == 0) {
                // Check if there any word to be processed
                if ($index) {
                    // Find the hash based on index
                    $key = md5($index);

                    // Keep the word
                    $result[$key] = $index;

                    // Reset the word
                    $index = '';
                }
            } else {
                // Capturing a new word
                $index .= $buffer{$i};
            }
        }

        return $result;
    }
}
