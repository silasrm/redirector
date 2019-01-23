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

function getMeta($content, $meta)
{
    preg_match_all( '#<\s*meta\s*(name|property|content)\s*=\s*("|\')(.*)("|\')\s*(name|property|content)\s*=\s*("|\')(.*)("|\')(\s+)?/?>#i', $content, $matches, PREG_SET_ORDER );
    $description = array_filter($matches, function($el) use ($meta) {
        return !empty(array_filter($el, function($el2) use ($meta) {
            return $el2 == $meta;
        }));
    });
    if (!empty($description)) {
        $description = array_filter(array_shift($description), function($el) use ($meta) {
            $el = trim($el);
            return !empty($el) && strpos($el, '<meta') === false && $el !== $meta && $el !== 'content' && $el !== 'name' && $el !== 'property' && $el !== '"' && $el !== "'";
        });

        $description = array_shift($description);
    } else {
        $description = null;
    }

    return $description;
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

    $description = getMeta($content, 'description');
    if (empty($description)) {
        $description = getMeta($content, 'og:description');
    }

    if (empty($description)) {
        $description = getMeta($content, 'twitter:description');
    }

    $image = getMeta($content, 'og:image');
    if (empty($image)) {
        $image = getMeta($content, 'twitter:image');
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