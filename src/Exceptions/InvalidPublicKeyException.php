<?php

declare(strict_types=1);

namespace Laranex\LaravelBiometricAuth\Exceptions;

use Exception;
use Throwable;

class InvalidPublicKeyException extends Exception
{
    public function __construct(string $message = 'Invalid Public Key', int $code = 500, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
