<?php

/*
 * Router of the local test server for PublicHttpTest (real cURL handler, no Http::fake()):
 * php -S 127.0.0.1:<port> tests/fixtures/public-http-server.php
 */

switch (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) {
    case '/page':
        echo 'Host: '.$_SERVER['HTTP_HOST'].', Accept-Encoding: '.($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '');
        break;

    case '/redirect':
        header('Location: /page', true, 302);
        break;

    case '/internal':
        header('Location: http://10.0.0.5/admin', true, 302);
        break;

    case '/large':
        header('Content-Length: 5000');
        echo str_repeat('a', 5000);
        break;

    case '/stream':
        // No Content-Length: 20 chunks over 2 seconds, so only an abort during the transfer is fast.
        for ($i = 0; $i < 20; $i++) {
            echo str_repeat('a', 1000);
            flush();
            usleep(100000);
        }
        break;

    case '/slow':
        sleep(3);
        echo 'late';
        break;

    default:
        http_response_code(404);
}
