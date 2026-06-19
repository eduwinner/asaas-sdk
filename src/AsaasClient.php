<?php

namespace AsaasSDK;

use AsaasSDK\Exceptions\AsaasException;
use AsaasSDK\Exceptions\HttpException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

class AsaasClient
{
public function __construct(
private string $apiKey,
private ClientInterface $httpClient,
private RequestFactoryInterface $requestFactory,
private StreamFactoryInterface $streamFactory,
private bool $sandbox = false
) {}

private function baseUrl(): string
{
return $this->sandbox
? 'https://sandbox.asaas.com/api/v3'
: 'https://www.asaas.com/api/v3';
}

public function withApiKey(string $apiKey): self
{
return new self(
apiKey: $apiKey,
httpClient: $this->httpClient,
requestFactory: $this->requestFactory,
streamFactory: $this->streamFactory,
sandbox: $this->sandbox
);
}

private function call(string $method, string $endpoint, ?array $data = null): array
{
$body = $data ? json_encode($data) : null;

$request = $this->requestFactory
->createRequest($method, $this->baseUrl() . $endpoint)
->withHeader('Content-Type', 'application/json')
->withHeader('access_token', $this->apiKey);

if ($body !== null) {
$stream = $this->streamFactory->createStream($body);
$request = $request->withBody($stream);
}

try {
$response = $this->httpClient->sendRequest($request);
} catch (\Throwable $e) {
throw new AsaasException("Erro de conexão: {$e->getMessage()}");
}

$status = $response->getStatusCode();
$content = json_decode($response->getBody()->getContents(), true);

if ($status >= 400) {
$msg = $content['errors'][0]['description'] ?? 'Erro desconhecido';
throw new HttpException($msg, $status);
}

return $content;
}

public function createSubAccount(array $data): array
{
return $this->call('POST', '/accounts', $data);
}

public function createCustomer(array $data): array
{
return $this->call('POST', '/customers', $data);
}

public function getCustomer(string $id): array
{
return $this->call('GET', "/customers/$id");
}

public function createPayment(array $data): array
{
return $this->call('POST', '/payments', $data);
}

public function getPayment(string $id): array
{
return $this->call('GET', "/payments/$id");
}

public function deletePayment(string $id): array
{
return $this->call('DELETE', "/payments/$id");
}

public function createSubscription(array $data): array
{
return $this->call('POST', '/subscriptions', $data);
}

public function createPixQrCode(array $data): array
{
return $this->call('POST', '/pix/qrCodes', $data);
}

public function createInvoice(array $data): array
{
return $this->call('POST', '/invoices', $data);
}
}
