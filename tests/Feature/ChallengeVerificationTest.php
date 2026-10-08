<?php

declare(strict_types=1);

use Laranex\LaravelBiometricAuth\Exceptions\BiometricChallengeNotFoundException;
use Laranex\LaravelBiometricAuth\Exceptions\BiometricNotFoundException;
use Laranex\LaravelBiometricAuth\Exceptions\InvalidPublicKeyException;
use Laranex\LaravelBiometricAuth\Facades\LaravelBiometricAuth;
use Laranex\LaravelBiometricAuth\Models\Biometric;

it('issues a random 32 byte challenge and keeps it until it is verified', function () {
    $biometric = createUser()->createBiometric(publicKeyBase64(rsaPrivateKey()));

    $challenged = LaravelBiometricAuth::getBiometric($biometric->id);

    expect($challenged->challenge)->toBeString()->toMatch('/^[0-9a-f]{64}$/')
        ->and(LaravelBiometricAuth::getBiometric($biometric->id)->challenge)->toBe($challenged->challenge)
        ->and($biometric->fresh()?->challenge)->toBe($challenged->challenge);
});

it('issues a different challenge to every biometric', function () {
    $user = createUser();
    $first = $user->createBiometric(publicKeyBase64(rsaPrivateKey()));
    $second = $user->createBiometric(publicKeyBase64(rsaPrivateKey()));

    expect(LaravelBiometricAuth::getBiometric($first->id)->challenge)
        ->not->toBe(LaravelBiometricAuth::getBiometric($second->id)->challenge);
});

it('verifies an RSA PKCS1 signature over the challenge and consumes the challenge', function () {
    $privateKey = rsaPrivateKey();
    $biometric = createUser()->createBiometric(publicKeyBase64($privateKey));
    $challenge = (string) LaravelBiometricAuth::getBiometric($biometric->id)->challenge;

    $signature = signChallenge($privateKey, $challenge);

    expect(LaravelBiometricAuth::verifyBiometric($biometric->id, $signature))->toBeTrue()
        ->and($biometric->fresh()?->challenge)->toBeNull()
        ->and(LaravelBiometricAuth::getBiometric($biometric->id)->challenge)->not->toBe($challenge);
});

it('does not accept a replayed signature once the challenge was consumed', function () {
    $privateKey = rsaPrivateKey();
    $biometric = createUser()->createBiometric(publicKeyBase64($privateKey));
    $signature = signChallenge($privateKey, (string) LaravelBiometricAuth::getBiometric($biometric->id)->challenge);
    LaravelBiometricAuth::verifyBiometric($biometric->id, $signature);

    LaravelBiometricAuth::verifyBiometric($biometric->id, $signature);
})->throws(BiometricChallengeNotFoundException::class, 'Biometric challenge not found');

it('verifies EC P-256 and Ed25519 signatures without any configuration', function (string $curve) {
    $privateKey = ecPrivateKey($curve);
    $biometric = createUser()->createBiometric(publicKeyBase64($privateKey));
    $challenge = (string) LaravelBiometricAuth::getBiometric($biometric->id)->challenge;

    expect(LaravelBiometricAuth::verifyBiometric($biometric->id, signChallenge($privateKey, $challenge)))->toBeTrue();
})->with(['secp256r1', 'Ed25519']);

it('honours the configured RSA padding and hash algorithm', function () {
    config()->set('biometric-auth.rsa.encryption_padding', 'pss');
    config()->set('biometric-auth.rsa.hash_algorithm', 'sha512');

    $privateKey = rsaPrivateKey('pss', 'sha512');
    $biometric = createUser()->createBiometric(publicKeyBase64($privateKey));
    $challenge = (string) LaravelBiometricAuth::getBiometric($biometric->id)->challenge;

    expect(LaravelBiometricAuth::verifyBiometric($biometric->id, signChallenge($privateKey, $challenge)))->toBeTrue();
});

it('rejects an RSA signature made with a different padding than configured', function () {
    $privateKey = rsaPrivateKey('pss');
    $biometric = createUser()->createBiometric(publicKeyBase64($privateKey));
    $challenge = (string) LaravelBiometricAuth::getBiometric($biometric->id)->challenge;

    expect(LaravelBiometricAuth::verifyBiometric($biometric->id, signChallenge($privateKey, $challenge)))->toBeFalse();
});

it('accepts the installed phpseclib RSA constant as the configured padding', function () {
    config()->set('biometric-auth.rsa.encryption_padding', constant(phpseclib('Crypt\RSA').'::SIGNATURE_PSS'));

    $privateKey = rsaPrivateKey('pss');
    $biometric = createUser()->createBiometric(publicKeyBase64($privateKey));
    $challenge = (string) LaravelBiometricAuth::getBiometric($biometric->id)->challenge;

    expect(LaravelBiometricAuth::verifyBiometric($biometric->id, signChallenge($privateKey, $challenge)))->toBeTrue();
});

it('rejects an unknown RSA padding name', function () {
    config()->set('biometric-auth.rsa.encryption_padding', 'oaep');

    $privateKey = rsaPrivateKey();
    $biometric = createUser()->createBiometric(publicKeyBase64($privateKey));
    $challenge = (string) LaravelBiometricAuth::getBiometric($biometric->id)->challenge;

    LaravelBiometricAuth::verifyBiometric($biometric->id, signChallenge($privateKey, $challenge));
})->throws(InvalidArgumentException::class, 'Unsupported RSA signature padding [oaep].');

it('rejects a signature from another key and keeps the challenge for a retry', function () {
    $privateKey = rsaPrivateKey();
    $biometric = createUser()->createBiometric(publicKeyBase64($privateKey));
    $challenge = (string) LaravelBiometricAuth::getBiometric($biometric->id)->challenge;

    $forged = signChallenge(ecPrivateKey(), $challenge);

    expect(LaravelBiometricAuth::verifyBiometric($biometric->id, $forged))->toBeFalse()
        ->and($biometric->fresh()?->challenge)->toBe($challenge)
        ->and(LaravelBiometricAuth::verifyBiometric($biometric->id, signChallenge($privateKey, $challenge)))->toBeTrue();
});

it('rejects a signature over a different message', function () {
    $privateKey = rsaPrivateKey();
    $biometric = createUser()->createBiometric(publicKeyBase64($privateKey));
    LaravelBiometricAuth::getBiometric($biometric->id);

    expect(LaravelBiometricAuth::verifyBiometric($biometric->id, signChallenge($privateKey, 'another challenge')))->toBeFalse()
        ->and(LaravelBiometricAuth::verifyBiometric($biometric->id, base64_encode('garbage')))->toBeFalse();
});

it('refuses to verify before a challenge was issued', function () {
    $privateKey = rsaPrivateKey();
    $biometric = createUser()->createBiometric(publicKeyBase64($privateKey));

    LaravelBiometricAuth::verifyBiometric($biometric->id, signChallenge($privateKey, 'anything'));
})->throws(BiometricChallengeNotFoundException::class);

it('throws when the biometric does not exist', function () {
    LaravelBiometricAuth::getBiometric('00000000-0000-0000-0000-000000000000');
})->throws(BiometricNotFoundException::class, 'Biometric not found');

it('treats a revoked biometric as not found', function () {
    $user = createUser();
    $privateKey = rsaPrivateKey();
    $biometric = $user->createBiometric(publicKeyBase64($privateKey));
    $challenge = (string) LaravelBiometricAuth::getBiometric($biometric->id)->challenge;
    $user->revokeBiometric($biometric->id);

    expect(fn () => LaravelBiometricAuth::getBiometric($biometric->id))->toThrow(BiometricNotFoundException::class)
        ->and(fn () => LaravelBiometricAuth::verifyBiometric($biometric->id, signChallenge($privateKey, $challenge)))->toThrow(BiometricNotFoundException::class);
});

it('reports a stored public key that can no longer be loaded', function () {
    $biometric = Biometric::query()->create([
        'authenticable_id' => '1',
        'authenticable_type' => 'App\Models\User',
        'public_key' => base64_encode('corrupted'),
        'challenge' => 'abc',
    ]);

    LaravelBiometricAuth::verifyBiometric($biometric->id, base64_encode('signature'));
})->throws(InvalidPublicKeyException::class);
