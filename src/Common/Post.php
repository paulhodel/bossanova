<?php

namespace bossanova\Common;

trait Post
{
    /**
     * This function is to return $_POST values
     *
     * @param  array $filter Array with the values you want to get from the $_POST, if is NULL return the whole $_POST.
     * @return array $row    Array with values from $_POST
     */
    public function getPost($filter = null)
    {
        // Return all variables in the post
        if (! isset($filter)) {
            if (isset($_POST)) {
                $row = $_POST;
            }
        } else {
            // Return only what you have defined as important
            if (is_string($filter)) {
                $row = isset($_POST[$filter]) ? $_POST[$filter] : null;
            } else {
                if (is_array($filter)) {
                    foreach ($filter as $k => $v) {
                        if (isset($_POST[$v])) {
                            $row[$v] = $_POST[$v];
                        }
                    }
                }
            }
        }

        return isset($row) ? $row : null;
    }
}
