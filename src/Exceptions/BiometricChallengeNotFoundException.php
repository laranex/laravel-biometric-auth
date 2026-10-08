<?php

declare(strict_types=1);

namespace Laranex\LaravelBiometricAuth\Exceptions;

use Exception;
use Throwable;

class BiometricChallengeNotFoundException extends Exception
{
    public function __construct(string $message = 'Biometric challenge not found', int $code = 500, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
