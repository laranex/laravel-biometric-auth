<?php

namespace Laranex\LaravelBiometricAuth\Exceptions;

use Exception;

class InvalidPublicKeyException extends Exception
{
    public function __construct($message = 'Invalid Public Key', $code = 500, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
