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
    $file = realpath(__DIR__.'/public'.$uri);
    $public = realpath(__DIR__.'/public').DIRECTORY_SEPARATOR;
    if ($file && is_file($file) && str_starts_with($file, $public)) {
        $type = strtolower(pathinfo($uri, PATHINFO_EXTENSION));
        $types = [
            'js' => 'application/javascript', 
            'css' => 'text/css', 
            'woff' => 'font/woff',
            'woff2' => 'font/woff2', 
            'ttf' => 'font/ttf', 
            'eot' => 'application/vnd.ms-fontobject',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'gif' => 'image/gif'
        ];
        if (isset($types[$type])) {
            header('Content-Type: '.$types[$type]);
        }
        $modified = filemtime($file);
        $etag = '"'.$modified.'-'.filesize($file).'"';
        $version = $modified.'-'.filesize($file);
        $versioned = ($_GET['v'] ?? '') === $version;
        header('Cache-Control: public, '.($versioned ? 'max-age=31536000, immutable' : 'max-age=0, must-revalidate'));
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
