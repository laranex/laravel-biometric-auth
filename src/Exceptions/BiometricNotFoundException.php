<?php

declare(strict_types=1);

namespace Laranex\LaravelBiometricAuth\Exceptions;

use Throwable;

/**
 * The biometric does not exist, has been revoked or belongs to another model (HTTP 404).
 */
class BiometricNotFoundException extends BiometricException
{
    public function __construct(string $message = 'Biometric not found', ?int $code = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    protected function defaultStatusCode(): int
    {
        return 404;
    }
}
