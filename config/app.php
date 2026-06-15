<?php
// config/app.php — application-wide base path configuration
//
// BASE_URL is the URL path prefix under which this app is served.
// - If the app lives at the web server's document root (e.g. a domain
//   root like https://editorialscholar.com/), set this to ''.
// - If it lives in a subfolder (e.g. http://localhost/Editorial-Scholar/),
//   set this to '/Editorial-Scholar'.
//
// All absolute links in the app (nav, redirects, asset paths) should be
// built as BASE_URL . '/something' so the site works from either location
// by changing this one value.

if (!defined('BASE_URL')) {
    define('BASE_URL', '/Editorial-Scholar');
}