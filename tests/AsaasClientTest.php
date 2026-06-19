<?php

namespace AsaasSDK\Tests;

use AsaasSDK\AsaasClient;
use AsaasSDK\Exceptions\HttpException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;

class AsaasClientTest extends TestCase
{
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    private function makeClient(int $status, array $body): AsaasClient
    {
        $json     = json_encode($body);
        $stream   = $this->factory->createStream($json);
        $response = new Response($status, ['Content-Type' => 'application/json'], $stream);

        $http = $this->createMock(ClientInterface::class);
        $http->method('sendRequest')->willReturn($response);

        return new AsaasClient('test-key', $http, $this->factory, $this->factory, true);
    }

    public function testCreateCustomerReturnsId(): void
    {
        $client = $this->makeClient(200, ['id' => 'cus_123', 'name' => 'Teste']);
        $result = $client->createCustomer(['name' => 'Teste', 'cpfCnpj' => '24971563792']);

        $this->assertSame('cus_123', $result['id']);
    }

    public function testGetCustomerReturnsData(): void
    {
        $client = $this->makeClient(200, ['id' => 'cus_123', 'name' => 'Teste']);
        $result = $client->getCustomer('cus_123');

        $this->assertSame('cus_123', $result['id']);
    }

    public function testListCustomersReturnsList(): void
    {
        $client = $this->makeClient(200, ['data' => [['id' => 'cus_1']], 'totalCount' => 1]);
        $result = $client->listCustomers(['limit' => 10]);

        $this->assertCount(1, $result['data']);
    }

    public function testUpdateCustomerReturnsUpdated(): void
    {
        $client = $this->makeClient(200, ['id' => 'cus_123', 'email' => 'novo@example.com']);
        $result = $client->updateCustomer('cus_123', ['email' => 'novo@example.com']);

        $this->assertSame('novo@example.com', $result['email']);
    }

    public function testDeleteCustomerReturnsDeleted(): void
    {
        $client = $this->makeClient(200, ['deleted' => true, 'id' => 'cus_123']);
        $result = $client->deleteCustomer('cus_123');

        $this->assertTrue($result['deleted']);
    }

    public function testCreatePaymentReturnsId(): void
    {
        $client = $this->makeClient(200, ['id' => 'pay_abc', 'status' => 'PENDING']);
        $result = $client->createPayment([
            'customer'    => 'cus_123',
            'billingType' => 'BOLETO',
            'value'       => 100.00,
            'dueDate'     => '2026-07-01',
        ]);

        $this->assertSame('pay_abc', $result['id']);
    }

    public function testListPaymentsReturnsList(): void
    {
        $client = $this->makeClient(200, ['data' => [['id' => 'pay_1'], ['id' => 'pay_2']], 'totalCount' => 2]);
        $result = $client->listPayments(['customer' => 'cus_123']);

        $this->assertSame(2, $result['totalCount']);
    }

    public function testRefundPaymentCallsRefundEndpoint(): void
    {
        $client = $this->makeClient(200, ['id' => 'pay_abc', 'status' => 'REFUNDED']);
        $result = $client->refundPayment('pay_abc');

        $this->assertSame('REFUNDED', $result['status']);
    }

    public function testCreateSubscriptionReturnsId(): void
    {
        $client = $this->makeClient(200, ['id' => 'sub_xyz', 'status' => 'ACTIVE']);
        $result = $client->createSubscription([
            'customer'    => 'cus_123',
            'billingType' => 'BOLETO',
            'value'       => 29.90,
            'nextDueDate' => '2026-07-01',
            'cycle'       => 'MONTHLY',
        ]);

        $this->assertSame('sub_xyz', $result['id']);
    }

    public function testGetSubscriptionReturnsData(): void
    {
        $client = $this->makeClient(200, ['id' => 'sub_xyz', 'cycle' => 'MONTHLY']);
        $result = $client->getSubscription('sub_xyz');

        $this->assertSame('MONTHLY', $result['cycle']);
    }

    public function testDeleteSubscriptionReturnsDeleted(): void
    {
        $client = $this->makeClient(200, ['deleted' => true]);
        $result = $client->deleteSubscription('sub_xyz');

        $this->assertTrue($result['deleted']);
    }

    public function testCreateBillReturnsId(): void
    {
        $client = $this->makeClient(200, ['id' => 'bill_001', 'status' => 'PENDING']);
        $result = $client->createBill([
            'identificationField' => '34191.79001 01043.510047 91020.150008 8 00000010000',
            'dueDate'             => '2026-07-10',
            'value'               => 150.00,
        ]);

        $this->assertSame('bill_001', $result['id']);
    }

    public function testListBillsReturnsList(): void
    {
        $client = $this->makeClient(200, ['data' => [['id' => 'bill_1']], 'totalCount' => 1]);
        $result = $client->listBills();

        $this->assertCount(1, $result['data']);
    }

    public function testHttpExceptionThrownOn400(): void
    {
        $this->expectException(HttpException::class);

        $client = $this->makeClient(400, ['errors' => [['description' => 'Cliente não encontrado']]]);
        $client->getCustomer('id-invalido');
    }

    public function testHttpExceptionContainsStatusCode(): void
    {
        try {
            $client = $this->makeClient(404, ['errors' => [['description' => 'Não encontrado']]]);
            $client->getPayment('pay_nao_existe');
            $this->fail('Deveria ter lançado HttpException');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->statusCode);
            $this->assertSame('Não encontrado', $e->getMessage());
        }
    }

    public function testWithApiKeyCreatesNewInstance(): void
    {
        $client    = $this->makeClient(200, ['id' => 'cus_1']);
        $subClient = $client->withApiKey('outra-chave');

        $this->assertNotSame($client, $subClient);
        $this->assertInstanceOf(AsaasClient::class, $subClient);
    }

    public function testCreateCreditCardPaymentForceBillingType(): void
    {
        $captured = null;

        $http = $this->createMock(ClientInterface::class);
        $http->method('sendRequest')->willReturnCallback(function ($request) use (&$captured) {
            $captured = json_decode($request->getBody()->getContents(), true);
            $body   = json_encode(['id' => 'pay_cc', 'billingType' => 'CREDIT_CARD']);
            $stream = (new Psr17Factory())->createStream($body);
            return new Response(200, [], $stream);
        });

        $client = new AsaasClient('key', $http, $this->factory, $this->factory, true);
        $client->createCreditCardPayment(['customer' => 'cus_1', 'value' => 50.00, 'dueDate' => '2026-07-01']);

        $this->assertSame('CREDIT_CARD', $captured['billingType']);
    }
}
