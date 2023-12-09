<?php

namespace Laranex\LaravelBiometricAuth\Exceptions;

use Exception;

class BiometricNotFoundException extends Exception
{
    public function __construct($message = 'Biometric not found', $code = 500, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
