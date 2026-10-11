<?php

/**
 * Laravel - A PHP Framework For Web Artisans
 *
 * @package  Laravel
 * @author   Taylor Otwell <taylor@laravel.com>
 */

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
);

// This file allows us to emulate Apache's "mod_rewrite" functionality from the
// built-in PHP web server. This provides a convenient way to test a Laravel
// application without having installed a "real" web server software here.
if ($uri !== '/' && file_exists(__DIR__.'/public'.$uri)) {
    // Only public code and fonts get validators; business documents keep their existing access path.
    $file = realpath(__DIR__.'/public'.$uri);
    $public = realpath(__DIR__.'/public').DIRECTORY_SEPARATOR;
    $type = strtolower(pathinfo($uri, PATHINFO_EXTENSION));
    $types = ['js' => 'application/javascript', 'css' => 'text/css', 'woff' => 'font/woff',
        'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'eot' => 'application/vnd.ms-fontobject'];
    if ($file && is_file($file) && str_starts_with($file, $public)
        && preg_match('#^(css|js|vendor|fonts)/#', str_replace('\\', '/', substr($file, strlen($public))))
        && isset($types[$type])) {
        $modified = filemtime($file);
        $etag = '"'.$modified.'-'.filesize($file).'"';
        header('Content-Type: '.$types[$type]);
        header('Cache-Control: public, max-age=0, must-revalidate');
        header('ETag: '.$etag);
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', $modified).' GMT');
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            return;
        }
        header('Content-Length: '.filesize($file));
        readfile($file);
        return;
    }
    return false;
}

require_once __DIR__.'/public/index.php';
