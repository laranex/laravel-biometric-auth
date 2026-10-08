<?php

declare(strict_types=1);

namespace Laranex\LaravelBiometricAuth\Exceptions;

use Throwable;

/**
 * The public key cannot be loaded by phpseclib (HTTP 422).
 */
class InvalidPublicKeyException extends BiometricException
{
    public function __construct(string $message = 'Invalid Public Key', ?int $code = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    protected function defaultStatusCode(): int
    {
        return 422;
    }
}
