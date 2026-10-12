<?php

declare(strict_types=1);

namespace Laranex\LaravelBiometricAuth\Exceptions;

use Throwable;

/**
 * The model already has the maximum number of active biometrics (HTTP 422).
 */
class TooManyBiometricsException extends BiometricException
{
    public function __construct(string $message = 'Too many biometrics registered', ?int $code = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    protected function defaultStatusCode(): int
    {
        return 422;
    }
}
