<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
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

it('honors the configured RSA padding and hash algorithm', function () {
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

it('clears the challenge after the configured number of failed attempts', function () {
    config()->set('biometric-auth.challenge.max_attempts', 3);

    $privateKey = rsaPrivateKey();
    $biometric = createUser()->createBiometric(publicKeyBase64($privateKey));
    $challenge = (string) LaravelBiometricAuth::getBiometric($biometric->id)->challenge;
    $forged = signChallenge(ecPrivateKey(), $challenge);

    expect(LaravelBiometricAuth::verifyBiometric($biometric->id, $forged))->toBeFalse()
        ->and(LaravelBiometricAuth::verifyBiometric($biometric->id, $forged))->toBeFalse()
        ->and($biometric->fresh()?->challenge)->toBe($challenge)
        ->and(LaravelBiometricAuth::verifyBiometric($biometric->id, $forged))->toBeFalse()
        ->and($biometric->fresh()?->challenge)->toBeNull()
        ->and(fn () => LaravelBiometricAuth::verifyBiometric($biometric->id, signChallenge($privateKey, $challenge)))
        ->toThrow(BiometricChallengeNotFoundException::class);

    $fresh = (string) LaravelBiometricAuth::getBiometric($biometric->id)->challenge;

    expect($fresh)->not->toBe($challenge)
        ->and(LaravelBiometricAuth::verifyBiometric($biometric->id, signChallenge($privateKey, $fresh)))->toBeTrue();
});

it('limits failed attempts to five by default and resets the count with a new challenge', function () {
    $privateKey = rsaPrivateKey();
    $biometric = createUser()->createBiometric(publicKeyBase64($privateKey));
    $challenge = (string) LaravelBiometricAuth::getBiometric($biometric->id)->challenge;

    foreach (range(1, 4) as $attempt) {
        expect(LaravelBiometricAuth::verifyBiometric($biometric->id, base64_encode('garbage')))->toBeFalse();
    }

    expect($biometric->fresh()?->challenge)->toBe($challenge)
        ->and(LaravelBiometricAuth::verifyBiometric($biometric->id, signChallenge($privateKey, $challenge)))->toBeTrue();

    $next = (string) LaravelBiometricAuth::getBiometric($biometric->id)->challenge;

    foreach (range(1, 4) as $attempt) {
        LaravelBiometricAuth::verifyBiometric($biometric->id, base64_encode('garbage'));
    }

    expect($biometric->fresh()?->challenge)->toBe($next);

    LaravelBiometricAuth::verifyBiometric($biometric->id, base64_encode('garbage'));

    expect($biometric->fresh()?->challenge)->toBeNull();
});

it('does not limit failed attempts when max_attempts is disabled', function (mixed $disabled) {
    config()->set('biometric-auth.challenge.max_attempts', $disabled);

    $biometric = createUser()->createBiometric(publicKeyBase64(rsaPrivateKey()));
    $challenge = (string) LaravelBiometricAuth::getBiometric($biometric->id)->challenge;

    foreach (range(1, 10) as $attempt) {
        LaravelBiometricAuth::verifyBiometric($biometric->id, base64_encode('garbage'));
    }

    expect($biometric->fresh()?->challenge)->toBe($challenge);
})->with([0, null]);

it('renders the exceptions as JSON errors with their HTTP status in API requests', function () {
    Route::get('/biometrics/{id}/challenge', fn (string $id) => ['challenge' => LaravelBiometricAuth::getBiometric($id)->challenge]);
    Route::post('/biometrics/{id}/verify', fn (string $id) => ['verified' => LaravelBiometricAuth::verifyBiometric($id, 'c2lnbmF0dXJl')]);

    $biometric = createUser()->createBiometric(publicKeyBase64(rsaPrivateKey()));

    $this->getJson('/biometrics/00000000-0000-0000-0000-000000000000/challenge')
        ->assertStatus(404)
        ->assertJson(['message' => 'Biometric not found']);

    $this->postJson("/biometrics/{$biometric->id}/verify")
        ->assertStatus(422)
        ->assertJson(['message' => 'Biometric challenge not found']);

    $this->getJson("/biometrics/{$biometric->id}/challenge")->assertOk();
    $this->postJson("/biometrics/{$biometric->id}/verify")->assertOk()->assertExactJson(['verified' => false]);
});

it('issues a fresh challenge once the pending one has expired', function () {
    $biometric = createUser()->createBiometric(publicKeyBase64(rsaPrivateKey()));
    $challenge = LaravelBiometricAuth::getBiometric($biometric->id)->challenge;

    $this->travel(299)->seconds();

    expect(LaravelBiometricAuth::getBiometric($biometric->id)->challenge)->toBe($challenge);

    $this->travel(2)->seconds();

    $fresh = LaravelBiometricAuth::getBiometric($biometric->id)->challenge;

    expect($fresh)->toMatch('/^[0-9a-f]{64}$/')->not->toBe($challenge)
        ->and($biometric->fresh()?->challenge)->toBe($fresh);
});

it('refuses to verify an expired challenge and clears it', function () {
    config()->set('biometric-auth.challenge.ttl', 60);

    $privateKey = rsaPrivateKey();
    $biometric = createUser()->createBiometric(publicKeyBase64($privateKey));
    $signature = signChallenge($privateKey, (string) LaravelBiometricAuth::getBiometric($biometric->id)->challenge);

    $this->travel(61)->seconds();

    expect(fn () => LaravelBiometricAuth::verifyBiometric($biometric->id, $signature))
        ->toThrow(BiometricChallengeNotFoundException::class)
        ->and($biometric->fresh()?->challenge)->toBeNull();
});

it('never expires challenges when the ttl is disabled', function (mixed $disabled) {
    config()->set('biometric-auth.challenge.ttl', $disabled);

    $privateKey = rsaPrivateKey();
    $biometric = createUser()->createBiometric(publicKeyBase64($privateKey));
    $challenge = (string) LaravelBiometricAuth::getBiometric($biometric->id)->challenge;

    $this->travel(30)->days();

    expect(LaravelBiometricAuth::getBiometric($biometric->id)->challenge)->toBe($challenge)
        ->and(LaravelBiometricAuth::verifyBiometric($biometric->id, signChallenge($privateKey, $challenge)))->toBeTrue();
})->with([0, null]);

it('treats an id that is not a UUID as an unknown biometric', function (string $method) {
    expect(fn () => match ($method) {
        'getBiometric' => LaravelBiometricAuth::getBiometric('1 OR 1=1'),
        'verifyBiometric' => LaravelBiometricAuth::verifyBiometric('not-a-uuid', base64_encode('signature')),
        'revokeBiometric' => createUser()->revokeBiometric('42'),
    })->toThrow(BiometricNotFoundException::class);
})->with(['getBiometric', 'verifyBiometric', 'revokeBiometric']);

it('rejects a signature that is not valid base64 as a failed attempt', function () {
    config()->set('biometric-auth.challenge.max_attempts', 2);

    $biometric = createUser()->createBiometric(publicKeyBase64(rsaPrivateKey()));
    LaravelBiometricAuth::getBiometric($biometric->id);

    expect(LaravelBiometricAuth::verifyBiometric($biometric->id, 'not base64!'))->toBeFalse()
        ->and(LaravelBiometricAuth::verifyBiometric($biometric->id, ''))->toBeFalse()
        ->and($biometric->fresh()?->challenge)->toBeNull();
});

it('verifies a signature only once when two requests race for the same challenge', function () {
    $privateKey = rsaPrivateKey();
    $biometric = createUser()->createBiometric(publicKeyBase64($privateKey));
    $signature = signChallenge($privateKey, (string) LaravelBiometricAuth::getBiometric($biometric->id)->challenge);

    // Another request consumes the challenge right after this one has loaded the biometric.
    Biometric::retrieved(function (Biometric $loaded): void {
        Biometric::query()->whereKey($loaded->id)->update(['challenge' => null]);
    });

    expect(LaravelBiometricAuth::verifyBiometric($biometric->id, $signature))->toBeFalse();
});

it('returns the challenge a concurrent request issued instead of overwriting it', function () {
    $biometric = createUser()->createBiometric(publicKeyBase64(rsaPrivateKey()));
    $concurrent = str_repeat('a', 64);

    // Another request issues a challenge right after this one has loaded the biometric.
    Biometric::retrieved(function (Biometric $loaded) use ($concurrent): void {
        if ($loaded->challenge === null) {
            Biometric::query()->whereKey($loaded->id)->update(['challenge' => $concurrent]);
        }
    });

    expect(LaravelBiometricAuth::getBiometric($biometric->id)->challenge)->toBe($concurrent);
});
