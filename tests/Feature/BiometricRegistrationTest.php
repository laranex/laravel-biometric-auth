<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Laranex\LaravelBiometricAuth\Exceptions\BiometricNotFoundException;
use Laranex\LaravelBiometricAuth\Exceptions\InvalidPublicKeyException;
use Laranex\LaravelBiometricAuth\Models\Biometric;
use Workbench\App\Models\User;

it('registers a device public key for an authenticatable model', function () {
    $user = createUser();
    $publicKey = publicKeyBase64(rsaPrivateKey());

    $biometric = $user->createBiometric($publicKey);
    $stored = $biometric->fresh();

    expect($biometric)->toBeInstanceOf(Biometric::class)
        ->and(Str::isUuid($biometric->id))->toBeTrue()
        ->and($stored)->toBeInstanceOf(Biometric::class)
        ->and($stored?->id)->toBe($biometric->id)
        ->and($stored?->public_key)->toBe($publicKey)
        ->and($stored?->challenge)->toBeNull()
        ->and((bool) $stored?->revoked)->toBeFalse()
        ->and((string) $stored?->authenticable_id)->toBe((string) $user->getKey())
        ->and($stored?->authenticable_type)->toBe($user->getMorphClass())
        ->and($stored?->instance)->toBeInstanceOf(User::class)
        ->and($stored?->instance?->getKey())->toBe($user->getKey())
        ->and($user->biometrics()->count())->toBe(1);
});

it('registers EC and Ed25519 public keys too', function () {
    $user = createUser();

    $p256 = $user->createBiometric(publicKeyBase64(ecPrivateKey('secp256r1')));
    $ed25519 = $user->createBiometric(publicKeyBase64(ecPrivateKey('Ed25519')));

    expect($p256->id)->not->toBe($ed25519->id)
        ->and($user->biometrics()->count())->toBe(2);
});

it('accepts EC P-256, P-384, Ed25519 and 2048 to 4096-bit RSA keys', function (Closure $privateKey) {
    $biometric = createUser()->createBiometric(publicKeyBase64($privateKey()));

    expect($biometric->exists)->toBeTrue();
})->with([
    'EC P-256' => fn (): object => ecPrivateKey('secp256r1'),
    'EC P-384' => fn (): object => ecPrivateKey('secp384r1'),
    'Ed25519' => fn (): object => ecPrivateKey('Ed25519'),
    'RSA 2048' => fn (): object => rsaKeyOfSize(2048),
    'RSA 4096' => fn (): object => rsaKeyOfSize(4096),
]);

it('rejects weak or unsupported key types', function (Closure $privateKey) {
    createUser()->createBiometric(publicKeyBase64($privateKey()));
})->with([
    'RSA 512' => fn (): object => rsaKeyOfSize(512),
    'RSA 1024' => fn (): object => rsaKeyOfSize(1024),
    'RSA 2047' => fn (): object => rsaKeyOfSize(2047),
    'RSA 4104' => fn (): object => rsaKeyOfSize(4104),
    'DSA 1024' => fn (): object => call_user_func([phpseclib('Crypt\DSA'), 'createKey'], 1024, 160),
    'EC secp112r1' => fn (): object => ecPrivateKey('secp112r1'),
    'EC secp256k1' => fn (): object => ecPrivateKey('secp256k1'),
    'EC P-521' => fn (): object => ecPrivateKey('secp521r1'),
    'Ed448' => fn (): object => ecPrivateKey('Ed448'),
])->throws(InvalidPublicKeyException::class, 'Invalid Public Key');

it('rejects a public key that is not strict base64', function () {
    $publicKey = publicKeyBase64(ecPrivateKey());

    createUser()->createBiometric(substr($publicKey, 0, 20).'!'.substr($publicKey, 20));
})->throws(InvalidPublicKeyException::class);

it('rejects an empty public key', function () {
    createUser()->createBiometric('');
})->throws(InvalidPublicKeyException::class);

it('rejects a public key longer than 8 KiB before decoding it', function () {
    createUser()->createBiometric(str_repeat('A', 8193));
})->throws(InvalidPublicKeyException::class);

it('rejects a public key phpseclib cannot load', function () {
    createUser()->createBiometric(base64_encode('not a public key'));
})->throws(InvalidPublicKeyException::class, 'Invalid Public Key');

it('rejects a private key where a public key is expected', function () {
    createUser()->createBiometric(base64_encode(rsaPrivateKey()->toString('PKCS8')));
})->throws(InvalidPublicKeyException::class);

it('never exposes the public key when the biometric is serialized', function () {
    $biometric = createUser()->createBiometric(publicKeyBase64(rsaPrivateKey()))->fresh();

    expect($biometric)->toBeInstanceOf(Biometric::class)
        ->and($biometric?->toArray())->not->toHaveKey('public_key')
        ->toHaveKeys(['id', 'authenticable_id', 'authenticable_type', 'challenge', 'revoked'])
        ->and(json_decode((string) $biometric?->toJson(), true))->not->toHaveKey('public_key');
});

it('revokes a biometric so it is no longer active', function () {
    $user = createUser();
    $biometric = $user->createBiometric(publicKeyBase64(rsaPrivateKey()));

    expect($user->revokeBiometric($biometric->id))->toBeTrue()
        ->and((bool) $biometric->fresh()?->revoked)->toBeTrue()
        ->and(Biometric::query()->active()->count())->toBe(0)
        ->and($user->biometrics()->count())->toBe(1);
});

it('refuses to revoke a biometric twice', function () {
    $user = createUser();
    $biometric = $user->createBiometric(publicKeyBase64(rsaPrivateKey()));
    $user->revokeBiometric($biometric->id);

    $user->revokeBiometric($biometric->id);
})->throws(BiometricNotFoundException::class, 'Biometric not found');

it('refuses to revoke a biometric that belongs to someone else', function () {
    $owner = createUser('owner@example.com');
    $biometric = $owner->createBiometric(publicKeyBase64(rsaPrivateKey()));

    createUser('intruder@example.com')->revokeBiometric($biometric->id);
})->throws(BiometricNotFoundException::class);
