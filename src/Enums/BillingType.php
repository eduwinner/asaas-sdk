<?php

namespace AsaasSDK\Enums;

enum BillingType: string
{
    case Boleto     = 'BOLETO';
    case CreditCard = 'CREDIT_CARD';
    case DebitCard  = 'DEBIT_CARD';
    case Pix        = 'PIX';
    case Undefined  = 'UNDEFINED';
}
