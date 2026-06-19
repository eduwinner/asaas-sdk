<?php

require_once __DIR__ . '/vendor/autoload.php';

use AsaasSDK\AsaasClient;
use GuzzleHttp\Client as GuzzleClient;
use Nyholm\Psr7\Factory\Psr17Factory;

// ---------------------------------------------------------------------------
// Config
// ---------------------------------------------------------------------------

$apiKey = getenv('ASAAS_API_KEY');

if (!$apiKey) {
    echo "Erro: variável ASAAS_API_KEY não definida.\n";
    echo "Execute: set ASAAS_API_KEY=sua_chave && php test.php\n";
    exit(1);
}

$factory = new Psr17Factory();

$client = new AsaasClient(
    apiKey: $apiKey,
    httpClient: new GuzzleClient(['http_errors' => false]),
    requestFactory: $factory,
    streamFactory: $factory,
    sandbox: true,
);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function ok(string $label, array $data): void
{
    $id = $data['id'] ?? $data['object'] ?? '—';
    echo "[OK] $label — id: $id\n";
}

function fail(string $label, \Throwable $e): void
{
    $code = $e instanceof \AsaasSDK\Exceptions\HttpException ? " (HTTP {$e->statusCode})" : '';
    echo "[ERRO] $label{$code}: {$e->getMessage()}\n";
}

// ---------------------------------------------------------------------------
// Testes
// ---------------------------------------------------------------------------

echo "=== Asaas SDK — Teste Sandbox ===\n\n";

// 1. Criar cliente
$customerId = null;
try {
    $result = $client->createCustomer([
        'name'    => 'Cliente Teste SDK',
        'cpfCnpj' => '24971563792', // CPF fictício válido
        'email'   => 'teste-sdk@example.com',
    ]);
    $customerId = $result['id'];
    ok('createCustomer', $result);
} catch (\Throwable $e) {
    fail('createCustomer', $e);
}

// 2. Buscar cliente
if ($customerId) {
    try {
        $result = $client->getCustomer($customerId);
        ok('getCustomer', $result);
    } catch (\Throwable $e) {
        fail('getCustomer', $e);
    }
}

// 3. Criar cobrança boleto
$paymentId = null;
if ($customerId) {
    try {
        $result = $client->createPayment([
            'customer'    => $customerId,
            'billingType' => 'BOLETO',
            'value'       => 100.00,
            'dueDate'     => date('Y-m-d', strtotime('+7 days')),
            'description' => 'Cobrança de teste SDK',
        ]);
        $paymentId = $result['id'];
        ok('createPayment (BOLETO)', $result);
    } catch (\Throwable $e) {
        fail('createPayment (BOLETO)', $e);
    }
}

// 4. Buscar cobrança
if ($paymentId) {
    try {
        $result = $client->getPayment($paymentId);
        ok('getPayment', $result);
    } catch (\Throwable $e) {
        fail('getPayment', $e);
    }
}

// 5. Criar Pix QR Code
if ($customerId) {
    try {
        $result = $client->createPayment([
            'customer'    => $customerId,
            'billingType' => 'PIX',
            'value'       => 49.90,
            'dueDate'     => date('Y-m-d', strtotime('+3 days')),
            'description' => 'Pix de teste SDK',
        ]);
        ok('createPayment (PIX)', $result);
    } catch (\Throwable $e) {
        fail('createPayment (PIX)', $e);
    }
}

// 6. Criar assinatura
if ($customerId) {
    try {
        $result = $client->createSubscription([
            'customer'    => $customerId,
            'billingType' => 'BOLETO',
            'value'       => 29.90,
            'nextDueDate' => date('Y-m-d', strtotime('+30 days')),
            'cycle'       => 'MONTHLY',
            'description' => 'Assinatura de teste SDK',
        ]);
        ok('createSubscription', $result);
    } catch (\Throwable $e) {
        fail('createSubscription', $e);
    }
}

// 7. Listar transferências
try {
    $result = $client->listTransfers();
    $total = $result['totalCount'] ?? count($result['data'] ?? []);
    echo "[OK] listTransfers — total: $total\n";
} catch (\Throwable $e) {
    fail('listTransfers', $e);
}

// 8. Cancelar cobrança criada no teste
if ($paymentId) {
    try {
        $result = $client->deletePayment($paymentId);
        ok('deletePayment', $result);
    } catch (\Throwable $e) {
        fail('deletePayment', $e);
    }
}

echo "\n=== Concluído ===\n";
