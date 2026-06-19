# Asaas SDK PHP (com White Label/BaaS)

SDK não-oficial para integração com o [Asaas](https://www.asaas.com) em PHP, incluindo suporte completo ao modelo **White Label / Banking as a Service (BaaS)**.

- Cliente PSR-18 para chamadas HTTP (traga seu próprio cliente)
- Tipagem forte (PHP 8.1+)
- Subcontas (White Label)
- Clientes, cobranças, assinaturas, Pix, notas fiscais
- Webhook handler
- Tratamento de erros padronizado

---

## Instalação

```bash
composer require eduwinner/asaas-sdk
```

Este SDK não inclui um cliente HTTP concreto — você precisa instalar um compatível com PSR-18. Recomendado:

```bash
composer require guzzlehttp/guzzle nyholm/psr7
```

---

## Configuração

```php
use AsaasSDK\AsaasClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;

$httpClient     = new GuzzleClient();
$requestFactory = new HttpFactory();
$streamFactory  = new HttpFactory();

$asaas = new AsaasClient(
    apiKey: '$aact_SUA_CHAVE_AQUI',
    httpClient: $httpClient,
    requestFactory: $requestFactory,
    streamFactory: $streamFactory,
    sandbox: true, // false em produção
);
```

---

## Clientes

```php
// Criar cliente
$cliente = $asaas->createCustomer([
    'name'     => 'João Silva',
    'cpfCnpj'  => '12345678909',
    'email'    => 'joao@exemplo.com',
    'phone'    => '11999990000',
]);

// Buscar cliente
$cliente = $asaas->getCustomer('cus_000012345678');
```

---

## Cobranças

```php
// Criar cobrança (boleto, Pix ou cartão)
$cobranca = $asaas->createPayment([
    'customer'    => 'cus_000012345678',
    'billingType' => 'BOLETO', // PIX | CREDIT_CARD | BOLETO
    'value'       => 150.00,
    'dueDate'     => '2025-12-31',
    'description' => 'Plano mensal',
]);

// Buscar cobrança
$cobranca = $asaas->getPayment('pay_000012345678');

// Cancelar cobrança
$asaas->deletePayment('pay_000012345678');
```

---

## Pix

```php
$qrCode = $asaas->createPixQrCode([
    'addressKey'     => 'sua@chave.pix',
    'description'    => 'Pagamento referente ao pedido #42',
    'value'          => 99.90,
]);
```

---

## Assinaturas

```php
$assinatura = $asaas->createSubscription([
    'customer'    => 'cus_000012345678',
    'billingType' => 'BOLETO',
    'value'       => 49.90,
    'nextDueDate' => '2025-12-01',
    'cycle'       => 'MONTHLY', // WEEKLY | MONTHLY | YEARLY
]);
```

---

## Notas Fiscais

```php
$nota = $asaas->createInvoice([
    'payment'        => 'pay_000012345678',
    'serviceDescription' => 'Desenvolvimento de software',
    'observations'   => 'ISS retido na fonte',
]);
```

---

## White Label / BaaS — Subcontas

Use `createSubAccount` para criar uma subconta e `withApiKey` para operar em nome dela:

```php
// Criar subconta
$subconta = $asaas->createSubAccount([
    'name'       => 'Empresa Parceira LTDA',
    'cpfCnpj'   => '12345678000195',
    'email'      => 'financeiro@parceira.com',
    'mobilePhone'=> '11999990000',
    'address'    => 'Rua Exemplo, 100',
    'province'   => 'Centro',
    'postalCode' => '01310100',
]);

// Operar com a chave da subconta
$clienteSubconta = $asaas->withApiKey($subconta['apiKey']);

$clienteSubconta->createCustomer([...]);
$clienteSubconta->createPayment([...]);
```

---

## Webhooks

Crie um endpoint PHP e use `WebhookHandler` para processar os eventos:

```php
use AsaasSDK\WebhookHandler;

$handler = new WebhookHandler();

$handler->handle(function (array $evento) {
    match ($evento['event']) {
        'PAYMENT_RECEIVED'  => processarPagamento($evento['payment']),
        'PAYMENT_OVERDUE'   => notificarAtraso($evento['payment']),
        default             => null,
    };
});
```

> Os payloads recebidos são gravados em `asaas_webhook.log` no diretório raiz da requisição.

---

## Tratamento de Erros

```php
use AsaasSDK\Exceptions\AsaasException;
use AsaasSDK\Exceptions\HttpException;

try {
    $cobranca = $asaas->createPayment([...]);
} catch (HttpException $e) {
    // Erro da API (4xx / 5xx)
    echo $e->statusCode;  // ex: 400
    echo $e->getMessage(); // descrição retornada pelo Asaas
} catch (AsaasException $e) {
    // Falha de conexão / rede
    echo $e->getMessage();
}
```

---

## Ambientes

| Ambiente   | URL base                              |
|------------|---------------------------------------|
| Sandbox    | `https://sandbox.asaas.com/api/v3`    |
| Produção   | `https://www.asaas.com/api/v3`        |

Passe `sandbox: true` no construtor para usar o ambiente de testes. Crie sua conta sandbox em [sandbox.asaas.com](https://sandbox.asaas.com).

---

## Licença

MIT
