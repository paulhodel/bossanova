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
 * Translate Library
 */
namespace bossanova\Translate;

class Dictionary
{
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
        $result = [];
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

                    // Keep the word
                    $result[$key] = $index;

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

        return $result;
    }
}
