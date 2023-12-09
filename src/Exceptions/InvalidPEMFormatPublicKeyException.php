<?php

namespace Laranex\LaravelBiometricAuth\Exceptions;

use Exception;

class InvalidPEMFormatPublicKeyException extends Exception
{
    public function __construct($message = 'Invalid PEM format in given Public Key', $code = 500, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
