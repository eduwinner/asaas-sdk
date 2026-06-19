<?php

namespace AsaasSDK;

final class WebhookHandler
{
public function handle(callable $callback): void
{
$input = file_get_contents('php://input');
$data = json_decode($input, true);

file_put_contents('asaas_webhook.log', $input . PHP_EOL, FILE_APPEND);

$callback($data);

http_response_code(200);
}
}
