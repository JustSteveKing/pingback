<?php

declare(strict_types=1);

/*
 * A webhook endpoint for the contract suite, run with PHP's built-in server: every request it
 * receives is written, exactly as it arrived, to a file in WEBHOOK_INBOX.
 */

$inbox = (string) getenv('WEBHOOK_INBOX');

file_put_contents(sprintf('%s/%s-%s.json', $inbox, hrtime(true), bin2hex(random_bytes(4))), json_encode([
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'],
    'headers' => getallheaders(),
    'body' => file_get_contents('php://input'),
    'received_at' => gmdate('Y-m-d H:i:s').' UTC',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

http_response_code(204);
