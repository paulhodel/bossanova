<?php

namespace bossanova\Layout;

class Layout
{
    public $title = '';
    public $author = '';
    public $keywords = '';
    public $description = '';

    /**
     * Loading the HTML layout including the bossanova needs (base href, and javascript in the end)
     *
     * @param string $templatePath - Path of the template
     * @param array $contents - Array with keys and values, where keys should match id of each html content container.
     * @return string $html
     */
    public function render($templatePath, $contents, $message = null)
    {
        if (! file_exists('public/' . $templatePath)) {
            $html = "^^[Template not found]^^ {$templatePath}";
        } else {
            // Load HTML layoiut
            $html = file_get_contents('public/' . $templatePath);

            // Scheme
            $request_scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? 'https:' : 'http:';

            // Defining baseurl for a correct template images, styling, javascript reference
            $url = defined('BOSSANOVA_BASEURL') ? BOSSANOVA_BASEURL : substr($_SERVER["SCRIPT_NAME"], 0, strrpos($_SERVER["SCRIPT_NAME"], "/"));
            $url = $_SERVER["HTTP_HOST"] . $url;
            $baseurl = explode('/', $templatePath);
            array_pop($baseurl);
            $baseurl = implode('/', $baseurl);

            // Page configuration
            $extra = '';

            if ($this->title) {
                $extra .= "\n<title>{$this->title}</title>";
                $extra .= "\n<meta itemprop='title' property='og:title' name='title' content='{$this->title}'>";
            }
            if ($this->author) {
                $extra .= "\n<meta itemprop='author' property='og:author' name='author' content='{$this->author}'>";
            }
            if ($this->keywords) {
                $extra .= "\n<meta itemprop='keywords' property='og:keywords' name='keywords' content='{$this->keywords}'>";
            }
            if ($this->description) {
                $extra .= "\n<meta itemprop='description' property='og:description' name='description' content='{$this->description}'>";
            }

            // Dynamic Tags
            $html = preg_replace("<head.*>", "head>\n<base href='$request_scheme//$url/$baseurl/'>$extra", $html, 1);

            // Process message
            if (isset($message) && $message) {
                // Force remove html tag to avoid duplication
                $html = str_replace("</html>", "", $html);

                // Inject message to the frontend
                $html .= "<script>\n";
                $html .= "var bossanova_message = {$message}\n";
                $html .= "if (jSuites) { jSuites.notification(bossanova_message); }\n";
                $html .= "</script>\n";
                $html .= "</html>";
            }

            // Looking for the template area to insert the content
            if ($contents) {
                $id = '';
                $tag = 0;
                $test = strtolower($html);

                // Is id found?
                $found = 0;

                // Merging HTML
                $merged = $html[0];

                for ($i = 1; $i < strlen($html); $i ++) {
                    $merged .= $html[$i];

                    // Inside a tag
                    if ($tag > 0) {
                        // Inside an id property?
                        if ($tag > 1) {
                            if ($tag == 2) {
                                // Found [=]
                                if ($test[$i] == chr(61)) {
                                    $tag = 3;
                                } else {
                                    // [space], ["], [']
                                    if ($test[$i] != chr(32) && $test[$i] != chr(34) && $test[$i] != chr(39)) {
                                        $tag = 1;
                                    }
                                }
                            } else {
                                // Separate any valid id character
                                if ((ord($test[$i]) >= 0x30 && ord($test[$i]) <= 0x39) ||
                                    (ord($test[$i]) >= 0x61 && ord($test[$i]) <= 0x7A) ||
                                    (ord($test[$i]) == 95) ||
                                    (ord($test[$i]) == 45)) {
                                    $id .= $test[$i];
                                }

                                // Checking end of the id string
                                if ($id) {
                                    // Check for an string to be closed in the next character [>], [space], ["], [']
                                    if ($test[$i + 1] == chr(62) ||
                                        $test[$i + 1] == chr(32) ||
                                        $test[$i + 1] == chr(34) ||
                                        $test[$i + 1] == chr(39)) {
                                        // Id found mark flag
                                        if (isset($contents[$id])) {
                                            $found = $contents[$id];
                                        }

                                        $id = '';
                                        $tag = 1;
                                    }
                                }
                            }
                        } elseif ($test[$i - 1] == chr(105) && $test[$i] == chr(100)) {
                            // id found start testing
                            $tag = 2;
                        }
                    }

                    // Tag found <
                    if ($test[$i - 1] == chr(60)) {
                        $tag = 1;
                    }

                    // End of a tag >
                    if ($test[$i] == chr(62)) {
                        $id = '';
                        $tag = 0;

                        // Inserted content in the correct position
                        if ($found) {
                            $merged .= $found;
                            $found = '';
                        }
                    }
                }

                $html = $merged;
            }
        }

        return $html;
    }
}
