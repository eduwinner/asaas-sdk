<?php

namespace AsaasSDK;

final class WebhookHandler
{
public function __construct(
private ?string $authToken = null
) {}

public function handle(callable $callback): void
{
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
http_response_code(405);
return;
}

if ($this->authToken !== null) {
$header = $_SERVER['HTTP_ASAAS_ACCESS_TOKEN'] ?? '';
if (!hash_equals($this->authToken, $header)) {
http_response_code(401);
return;
}
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
