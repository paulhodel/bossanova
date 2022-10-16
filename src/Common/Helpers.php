<?php

namespace bossanova\Common;

trait Helpers
{
    public function jTemplate($template, $data)
    {
        $html = '';

        if (isset($data[0]) && count($data[0])) {
            foreach ($data as $k => $v) {
                $txt = $template;

                foreach ($v as $k1 => $v1) {
                    $txt = str_replace("{{". $k1 . "}}", $v1, $txt);
                }

                $html .= $txt;
            }
        } else {
            $html = $template;

            foreach ($data as $k => $v) {
                $html = str_replace("{{". $k . "}}", $v, $html);
            }
        }

        return $html;
    }
}
