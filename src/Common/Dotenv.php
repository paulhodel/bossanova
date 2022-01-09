<?php

namespace bossanova\Common;

class Dotenv
{
    static public function get($path)
    {
        if (! is_readable($path)) {
            throw new \RuntimeException(sprintf('%s file is not readable', $path));
        }

        if (! file_exists($path)) {
            throw new \InvalidArgumentException(sprintf('%s does not exist', $path));
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if (! array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                if (substr($value, 0, 1) == "'" || substr($value, 0, 1) == '"') {
                    $value = substr($value, 1);
                }
                if (substr($value, -1, 1) == "'" || substr($value, -1, 1) == '"') {
                    $value = substr($value, 0,-1);
                }
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
                
                define($name, $value);
            }
        }
    }
}
