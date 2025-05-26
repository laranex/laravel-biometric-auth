<?php

return [
    'table' => env('BIOMETRIC_AUTH_TABLE', 'biometrics'),

    // You will need to be explicit about the encryption padding and hash algorithm when working with RSA keys.
    // For the rest of the algorithms, the package will automatically detect with the help of phpseclib.
    'rsa' => [
        'encryption_padding' => \phpseclib3\Crypt\RSA::SIGNATURE_PKCS1,
        'hash_algorithm' => 'sha256',
    ],
];
