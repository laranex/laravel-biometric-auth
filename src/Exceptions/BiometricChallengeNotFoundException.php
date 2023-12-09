<?php

namespace Laranex\LaravelBiometricAuth\Exceptions;

use Exception;

class BiometricChallengeNotFoundException extends Exception
{
    public function __construct($message = 'Biometric challenge not found', $code = 500, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
