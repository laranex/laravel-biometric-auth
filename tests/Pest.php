<?php

declare(strict_types=1);

use Laranex\LaravelBiometricAuth\Support\PublicKey;
use Laranex\LaravelBiometricAuth\Tests\TestCase;
use Workbench\App\Models\User;

uses(TestCase::class)->in(__DIR__);

/**
 * Persist a workbench user that uses the HasBiometrics trait.
 */
function createUser(string $email = 'test@example.com'): User
{
    return User::query()->create([
        'name' => 'Test User',
        'email' => $email,
        'password' => 'secret',
    ]);
}

/**
 * A phpseclib class name in whichever major (3 or 4) is installed.
 */
function phpseclib(string $class): string
{
    return PublicKey::phpseclibNamespace().'\\'.$class;
}

/**
 * Generate an RSA key pair the way a mobile device would, signing with PKCS1 v1.5 / SHA-256 by default.
 */
function rsaPrivateKey(string $padding = 'pkcs1', string $hash = 'sha256'): object
{
    static $keys = [];

    $namespace = PublicKey::phpseclibNamespace();
    $keys[$namespace] ??= call_user_func([phpseclib('Crypt\RSA'), 'createKey'], 2048);

    return $keys[$namespace]->withPadding(PublicKey::rsaSignaturePadding($padding))->withHash($hash);
}

/**
 * Generate an EC key pair on the given curve (P-256 by default, or Ed25519).
 */
function ecPrivateKey(string $curve = 'secp256r1'): object
{
    return call_user_func([phpseclib('Crypt\EC'), 'createKey'], $curve);
}

/**
 * The base64 encoded public key a device sends when it registers.
 */
function publicKeyBase64(object $privateKey): string
{
    return base64_encode($privateKey->getPublicKey()->toString('PKCS8'));
}

/**
 * The base64 encoded signature a device sends back for a challenge.
 */
function signChallenge(object $privateKey, string $challenge): string
{
    return base64_encode($privateKey->sign($challenge));
}
