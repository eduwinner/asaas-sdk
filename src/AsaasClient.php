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
        $body = $data !== null ? json_encode($data) : null;

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

        $status  = $response->getStatusCode();
        $content = json_decode($response->getBody()->getContents(), true) ?? [];

        if ($status >= 400) {
            $msg = $content['errors'][0]['description'] ?? 'Erro desconhecido';
            throw new HttpException($msg, $status);
        }

        return $content;
    }

    private function buildQuery(array $filters): string
    {
        return $filters ? '?' . http_build_query($filters) : '';
    }

    // -------------------------------------------------------------------------
    // Sub-contas (White Label / BaaS)
    // -------------------------------------------------------------------------

    public function createSubAccount(array $data): array
    {
        return $this->call('POST', '/accounts', $data);
    }

    public function getSubAccount(string $id): array
    {
        return $this->call('GET', "/accounts/$id");
    }

    public function listSubAccounts(array $filters = []): array
    {
        return $this->call('GET', '/accounts' . $this->buildQuery($filters));
    }

    // -------------------------------------------------------------------------
    // Clientes
    // -------------------------------------------------------------------------

    public function createCustomer(array $data): array
    {
        return $this->call('POST', '/customers', $data);
    }

    public function getCustomer(string $id): array
    {
        return $this->call('GET', "/customers/$id");
    }

    public function listCustomers(array $filters = []): array
    {
        return $this->call('GET', '/customers' . $this->buildQuery($filters));
    }

    public function updateCustomer(string $id, array $data): array
    {
        return $this->call('POST', "/customers/$id", $data);
    }

    public function deleteCustomer(string $id): array
    {
        return $this->call('DELETE', "/customers/$id");
    }

    // -------------------------------------------------------------------------
    // Cobranças
    // -------------------------------------------------------------------------

    public function createPayment(array $data): array
    {
        return $this->call('POST', '/payments', $data);
    }

    public function getPayment(string $id): array
    {
        return $this->call('GET', "/payments/$id");
    }

    public function listPayments(array $filters = []): array
    {
        return $this->call('GET', '/payments' . $this->buildQuery($filters));
    }

    public function updatePayment(string $id, array $data): array
    {
        return $this->call('POST', "/payments/$id", $data);
    }

    public function deletePayment(string $id): array
    {
        return $this->call('DELETE', "/payments/$id");
    }

    public function refundPayment(string $id, array $data = []): array
    {
        return $this->call('POST', "/payments/$id/refund", $data ?: null);
    }

    public function getPaymentPixQrCode(string $id): array
    {
        return $this->call('GET', "/payments/$id/pixQrCode");
    }

    // -------------------------------------------------------------------------
    // Cartão de crédito
    // -------------------------------------------------------------------------

    /**
     * Cobrança com cartão (sem tokenizar). $data deve conter: customer, value,
     * dueDate, creditCard {number, holderName, expiryMonth, expiryYear, ccv},
     * creditCardHolderInfo {name, email, cpfCnpj, postalCode, addressNumber, phone}.
     */
    public function createCreditCardPayment(array $data): array
    {
        $data['billingType'] = 'CREDIT_CARD';
        return $this->call('POST', '/payments', $data);
    }

    /** Tokeniza cartão e retorna creditCardToken para cobranças futuras. */
    public function tokenizeCreditCard(array $data): array
    {
        return $this->call('POST', '/creditCard/tokenize', $data);
    }

    // -------------------------------------------------------------------------
    // Assinaturas
    // -------------------------------------------------------------------------

    public function createSubscription(array $data): array
    {
        return $this->call('POST', '/subscriptions', $data);
    }

    public function getSubscription(string $id): array
    {
        return $this->call('GET', "/subscriptions/$id");
    }

    public function listSubscriptions(array $filters = []): array
    {
        return $this->call('GET', '/subscriptions' . $this->buildQuery($filters));
    }

    public function updateSubscription(string $id, array $data): array
    {
        return $this->call('POST', "/subscriptions/$id", $data);
    }

    public function deleteSubscription(string $id): array
    {
        return $this->call('DELETE', "/subscriptions/$id");
    }

    public function listSubscriptionPayments(string $id, array $filters = []): array
    {
        return $this->call('GET', "/subscriptions/$id/payments" . $this->buildQuery($filters));
    }

    // -------------------------------------------------------------------------
    // Pix
    // -------------------------------------------------------------------------

    public function createPixQrCode(array $data): array
    {
        return $this->call('POST', '/pix/qrCodes', $data);
    }

    // -------------------------------------------------------------------------
    // Notas fiscais
    // -------------------------------------------------------------------------

    public function createInvoice(array $data): array
    {
        return $this->call('POST', '/invoices', $data);
    }

    // -------------------------------------------------------------------------
    // Transferências
    // -------------------------------------------------------------------------

    public function transfer(array $data): array
    {
        return $this->call('POST', '/transfers', $data);
    }

    public function listTransfers(array $filters = []): array
    {
        return $this->call('GET', '/transfers' . $this->buildQuery($filters));
    }

    // -------------------------------------------------------------------------
    // Contas a pagar (Bills)
    // -------------------------------------------------------------------------

    /**
     * Cria conta a pagar. $data: identificationField (linha digitável do boleto),
     * dueDate, value, description. type: BOLETO | OUTROS.
     */
    public function createBill(array $data): array
    {
        return $this->call('POST', '/bills', $data);
    }

    public function getBill(string $id): array
    {
        return $this->call('GET', "/bills/$id");
    }

    public function listBills(array $filters = []): array
    {
        return $this->call('GET', '/bills' . $this->buildQuery($filters));
    }

    public function payBill(string $id): array
    {
        return $this->call('POST', "/bills/$id/pay");
    }

    public function cancelBill(string $id): array
    {
        return $this->call('DELETE', "/bills/$id");
    }

    // -------------------------------------------------------------------------
    // Financeiro — extrato e saldo (conciliação bancária)
    // -------------------------------------------------------------------------

    /**
     * Saldo disponível na conta Asaas.
     * Retorna: balance, availableForWithdrawal.
     */
    public function getBalance(): array
    {
        return $this->call('GET', '/finance/balance');
    }

    /**
     * Extrato de movimentações financeiras.
     * Filtros úteis: startDate, finishDate, type (CREDIT|DEBIT).
     * Cada item contém: id, date, value, balance, type, description,
     * payment (se vinculado a cobrança) e transferId (se transferência).
     */
    public function listFinancialTransactions(array $filters = []): array
    {
        return $this->call('GET', '/financialTransactions' . $this->buildQuery($filters));
    }
}
