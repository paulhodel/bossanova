<?php

namespace bossanova\Error;

use bossanova\Render\Render;

class Error
{
    // Singleton needs
    private function __construct()
    {

    }

    // Cannot be clonned
    private function __clone()
    {

    }

    /**
     * Error Handling
     */
    public static function handler($description, $e)
    {
        if (Render::isAjax()) {
            $description = strip_tags($description);
            $e = strip_tags($e);
            $data = [];
            $data['message'] = $description;
            echo json_encode($data);
        } else {
            echo "<h1>Bossanova Framework</h1>";
            echo "<p>{$description}</p>";
        }

        exit();
    }
}
