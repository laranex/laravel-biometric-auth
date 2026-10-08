<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Biometrics Table
    |--------------------------------------------------------------------------
    |
    | The database table that stores the registered device public keys and
    | their pending challenges. Change it if "biometrics" clashes with a
    | table in your application; the bundled migration follows this value.
    |
    */

    'table' => env('BIOMETRIC_AUTH_TABLE', 'biometrics'),

    /*
    |--------------------------------------------------------------------------
    | Challenges
    |--------------------------------------------------------------------------
    |
    | A failed verification keeps the pending challenge so the device can
    | retry. After "max_attempts" failed verifications of the same challenge
    | it is cleared and the client must request a new one. Attempts are
    | counted in the application's default cache store. Set it to 0 or null
    | to disable the limit.
    |
    */

    'challenge' => [
        'max_attempts' => env('BIOMETRIC_AUTH_CHALLENGE_MAX_ATTEMPTS', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | RSA Signature Settings
    |--------------------------------------------------------------------------
    |
    | RSA signatures need an explicit padding scheme and hash algorithm, so
    | they must match the way your mobile apps sign the challenge. Every other
    | key type (EC, Ed25519, DSA) is detected automatically by phpseclib.
    |
    | Padding: "pkcs1" (RSASSA-PKCS1-v1_5) or "pss" (RSASSA-PSS); phpseclib 3
    | also accepts "relaxed_pkcs1". The name works with phpseclib 3 and 4.
    |
    */

    'rsa' => [
        'encryption_padding' => 'pkcs1',
        'hash_algorithm' => 'sha256',
    ],

];
