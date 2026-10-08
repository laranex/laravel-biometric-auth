<?php

declare(strict_types=1);

namespace Laranex\LaravelBiometricAuth\Exceptions;

use Throwable;

/**
 * There is no pending challenge to verify: none was issued, it was consumed by a successful
 * verification, or it was cleared after too many failed attempts. The client should request
 * a new one (HTTP 422).
 */
class BiometricChallengeNotFoundException extends BiometricException
{
    public function __construct(string $message = 'Biometric challenge not found', ?int $code = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    protected function defaultStatusCode(): int
    {
        return 422;
    }
}
