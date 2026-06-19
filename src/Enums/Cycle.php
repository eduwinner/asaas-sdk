<?php

namespace AsaasSDK\Enums;

enum Cycle: string
{
    case Weekly       = 'WEEKLY';
    case Biweekly     = 'BIWEEKLY';
    case Monthly      = 'MONTHLY';
    case Bimonthly    = 'BIMONTHLY';
    case Quarterly    = 'QUARTERLY';
    case Semiannually = 'SEMIANNUALLY';
    case Yearly       = 'YEARLY';
}
