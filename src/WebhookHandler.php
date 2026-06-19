<?php

namespace AsaasSDK;

final class WebhookHandler
{
public function handle(callable $callback): void
{
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
http_response_code(405);
return;
}

$input = file_get_contents('php://input');
$data  = json_decode($input, true);

if (!is_array($data)) {
http_response_code(400);
return;
}

file_put_contents('asaas_webhook.log', $input . PHP_EOL, FILE_APPEND);

$callback($data);

http_response_code(200);
}
}
