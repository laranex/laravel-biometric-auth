<?php

// config for Laranex/LaravelBiometricAuth
return [
    'table' => env('BIOMETRIC_AUTH_TABLE', 'biometrics'),

    'signature_algorithm' => env('BIOMETRIC_AUTH_ALGORITHM', OPENSSL_ALGO_SHA256),
];
