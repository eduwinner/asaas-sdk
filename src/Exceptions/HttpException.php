<?php

namespace AsaasSDK\Exceptions;

use Exception;

class HttpException extends Exception
{
public function __construct(
string $message,
public int $statusCode
) {
parent::__construct($message);
}
}
