<?php

namespace AsaasSDK\Enums;

enum PixAddressKeyType: string
{
    case Cpf   = 'CPF';
    case Cnpj  = 'CNPJ';
    case Email = 'EMAIL';
    case Phone = 'PHONE';
    case Eva   = 'EVA';
}
