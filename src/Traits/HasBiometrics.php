<?php

declare(strict_types=1);

namespace Laranex\LaravelBiometricAuth\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Laranex\LaravelBiometricAuth\Exceptions\BiometricNotFoundException;
use Laranex\LaravelBiometricAuth\Exceptions\InvalidPublicKeyException;
use Laranex\LaravelBiometricAuth\Exceptions\TooManyBiometricsException;
use Laranex\LaravelBiometricAuth\Models\Biometric;
use Laranex\LaravelBiometricAuth\Support\PublicKey;

/**
 * @phpstan-require-extends Model
 */
trait HasBiometrics
{
    /**
     * Every biometric ever registered for this model, revoked ones included.
     */
    public function biometrics(): MorphMany
    {
        return $this->morphMany(Biometric::class, 'authenticable');
    }

    /**
     * Register a device public key (base64 encoded PEM/DER) for this model.
     *
     * Accepts EC P-256, EC P-384, Ed25519 and 2048 to 4096-bit RSA keys, up to
     * `biometric-auth.max_per_user` active biometrics per model.
     *
     * @throws InvalidPublicKeyException
     * @throws TooManyBiometricsException
     */
    public function createBiometric(string $publicKeyBase64): Biometric
    {
        PublicKey::load($publicKeyBase64);

        $max = Config::get('biometric-auth.max_per_user', 10);

        if (is_numeric($max) && (int) $max > 0 && $this->biometrics()->where('revoked', false)->count() >= (int) $max) {
            throw new TooManyBiometricsException;
        }

        /** @var Biometric $biometric */
        $biometric = $this->biometrics()->create([
            'public_key' => $publicKeyBase64,
        ]);

        return $biometric;
    }

    /**
     * Revoke one of this model's active biometrics so it can no longer be challenged or verified.
     *
     * @throws BiometricNotFoundException
     */
    public function revokeBiometric(string $biometricId): bool
    {
        if (! Str::isUuid($biometricId)) {
            throw new BiometricNotFoundException;
        }

        /** @var Biometric|null $biometric */
        $biometric = $this->biometrics()->whereKey($biometricId)->where('revoked', false)->first();

        if ($biometric === null) {
            throw new BiometricNotFoundException;
        }

        return $biometric->forceFill(['revoked' => true, 'challenge' => null, 'challenge_issued_at' => null])->save();
    }
}
