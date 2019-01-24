<?php

if (!isset($_GET['url']) || !filter_var($_GET['url'], FILTER_VALIDATE_URL) || strpos($_GET['url'], 'http') === false) {
    return false;
}

/* gets the data from a URL */
function getData($url) {
    $ch = curl_init();
    $timeout = 5;
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

function getMetas($content)
{
    preg_match_all( '#<\s*meta\s*(name|property|content)\s*=\s*("|\')(.*)("|\')\s*(name|property|content)\s*=\s*("|\')(.*)("|\')(\s+)?\/?>#i', $content, $matches, PREG_SET_ORDER );

    if (!empty($matches)) {
        $result = array_map(function($arr) {
            return array_values(array_filter($arr, function($el) {
                $el = trim($el);
                return !empty($el) && strpos($el, '<meta') === false
                    && $el !== $meta && $el !== 'content' && $el !== 'name'
                    && $el !== 'property' && $el !== '"' && $el !== "'";
            }));
        }, $matches);
    } else {
        $result = null;
    }

    return $result;
}

function getMeta($metas, $meta)
{
    $result = array_filter($metas, function($arr) use($meta) {
        return $arr[0] == $meta;
    });

    if (!empty($result)) {
        $result = array_shift($result);
        preg_match('/\.([jpg|jpeg|png|gif]){3,}/i', $result[1], $matches);
        if (!empty($matches)) {
            if ((($posicao = strpos($result[1], '.jpg')) !== false)
                || (($posicao = strpos($result[1], '.png')) !== false)
                || (($posicao = strpos($result[1], '.gif')) !== false)) {
                $tamanho = 4;
            } else if (($posicao = strpos($result[1], '.jpeg')) !== false) {
                $tamanho = 5;
            }

            return substr($result[1], 0, ($posicao + $tamanho));
        }

        return $result[1];
    }

    return null;
}

$url = filter_var($_GET['url'], FILTER_VALIDATE_URL);

$cache = 'cache/' . sha1($url) . '.html';
if (!file_exists($cache)) {
    $content = getData($url);
    preg_match('/\<title\>(.*)\<\/title\>/i', $content, $matches);
    $title = array_filter($matches, function($el) {
        return !empty(trim($el)) && strpos($el, '<title>') === false;
    });
    $title = array_shift($title);

    $metas = getMetas($content);

    $description = getMeta($metas, 'description');
    if (empty($description)) {
        $description = getMeta($metas, 'og:description');
    }

    if (empty($description)) {
        $description = getMeta($metas, 'twitter:description');
    }

    $image = getMeta($metas, 'og:image');
    if (empty($image)) {
        $image = getMeta($metas, 'twitter:image');
    }

    if (empty($image)) {
        $image = 'images/marca.png';
    }

    $tmpl = file_get_contents('tmpl.html');
    $tmpl = str_replace('{{title}}', ($title ? $title : ''), $tmpl);
    $tmpl = str_replace('{{description}}', ($description ? $description : ''), $tmpl);
    $tmpl = str_replace('{{image}}', ($image ? $image : ''), $tmpl);
    $tmpl = str_replace('{{url}}', $url, $tmpl);

    file_put_contents($cache, $tmpl);
} else {
    $tmpl = file_get_contents($cache);
}

echo $tmpl;