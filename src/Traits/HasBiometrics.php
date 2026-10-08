<?php

declare(strict_types=1);

namespace Laranex\LaravelBiometricAuth\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laranex\LaravelBiometricAuth\Exceptions\BiometricNotFoundException;
use Laranex\LaravelBiometricAuth\Exceptions\InvalidPublicKeyException;
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
     * @throws InvalidPublicKeyException
     */
    public function createBiometric(string $publicKeyBase64): Biometric
    {
        PublicKey::load($publicKeyBase64);

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
        /** @var Biometric|null $biometric */
        $biometric = $this->biometrics()->where('id', $biometricId)->where('revoked', false)->first();

        if ($biometric === null) {
            throw new BiometricNotFoundException;
        }

        return $biometric->update(['revoked' => true]);
    }
}
