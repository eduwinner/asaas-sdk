<?php

namespace AsaasSDK\Tests;

use AsaasSDK\Enums\BillingType;
use AsaasSDK\Enums\Cycle;
use AsaasSDK\Enums\PixAddressKeyType;
use PHPUnit\Framework\TestCase;

class EnumsTest extends TestCase
{
    public function testBillingTypeValues(): void
    {
        $this->assertSame('BOLETO', BillingType::Boleto->value);
        $this->assertSame('CREDIT_CARD', BillingType::CreditCard->value);
        $this->assertSame('PIX', BillingType::Pix->value);
    }

    public function testCycleValues(): void
    {
        $this->assertSame('MONTHLY', Cycle::Monthly->value);
        $this->assertSame('YEARLY', Cycle::Yearly->value);
        $this->assertSame('WEEKLY', Cycle::Weekly->value);
    }

    public function testPixAddressKeyTypeValues(): void
    {
        $this->assertSame('CPF', PixAddressKeyType::Cpf->value);
        $this->assertSame('EMAIL', PixAddressKeyType::Email->value);
        $this->assertSame('EVA', PixAddressKeyType::Eva->value);
    }

    public function testBillingTypeFromValue(): void
    {
        $type = BillingType::from('PIX');
        $this->assertSame(BillingType::Pix, $type);
    }

    public function testCycleFromValue(): void
    {
        $cycle = Cycle::from('QUARTERLY');
        $this->assertSame(Cycle::Quarterly, $cycle);
    }
}
