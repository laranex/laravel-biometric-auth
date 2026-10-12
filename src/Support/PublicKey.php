<?php

declare(strict_types=1);

namespace Laranex\LaravelBiometricAuth\Support;

use Exception;
use InvalidArgumentException;
use Laranex\LaravelBiometricAuth\Exceptions\InvalidPublicKeyException;
use LogicException;

/**
 * A device public key loaded with whichever phpseclib major is installed.
 *
 * phpseclib 4 moved every class from the "phpseclib3" to the "phpseclib4" namespace
 * and renumbered the RSA signature constants, but kept the loading, signing and
 * verification API. This adapter resolves the namespace at runtime so the package
 * supports both majors without referencing either one statically.
 *
 * @internal
 */
final class PublicKey
{
    private function __construct(
        private readonly mixed $key,
    ) {}

    /**
     * Load a base64 encoded public key (any format phpseclib understands).
     *
     * Only EC P-256, EC P-384, Ed25519 and 2048 to 4096-bit RSA keys are accepted;
     * DSA, other curves and weaker or larger RSA keys are rejected. RSA keys are
     * configured with the given hash and padding; the padding is a name ("pkcs1",
     * "pss", ...) or a raw phpseclib RSA::SIGNATURE_* value.
     *
     * @throws InvalidPublicKeyException
     */
    public static function load(string $publicKeyBase64, string $hash = 'sha256', int|string $padding = 'pkcs1'): self
    {
        $namespace = self::phpseclibNamespace();

        // A base64 encoded 4096-bit RSA key is under 1 KiB; anything over 8 KiB is refused before decoding.
        if (strlen($publicKeyBase64) > 8192) {
            throw new InvalidPublicKeyException;
        }

        $decoded = base64_decode($publicKeyBase64, true);

        if ($decoded === false || $decoded === '') {
            throw new InvalidPublicKeyException;
        }

        $loadPublicKey = [$namespace.'\Crypt\PublicKeyLoader', 'loadPublicKey'];

        if (! is_callable($loadPublicKey)) {
            throw new LogicException('phpseclib/phpseclib 3 or 4 must be installed.');
        }

        try {
            $key = $loadPublicKey($decoded);
        } catch (Exception $exception) {
            if (is_a($exception, $namespace.'\Exception\NoKeyLoadedException')) {
                throw new InvalidPublicKeyException(previous: $exception);
            }

            throw $exception;
        }

        if (! self::isAllowedKey($key, $namespace)) {
            throw new InvalidPublicKeyException;
        }

        if (is_a($key, $namespace.'\Crypt\RSA\PublicKey') && method_exists($key, 'withHash')) {
            $key = $key->withHash($hash)->withPadding(self::rsaSignaturePadding($padding));
        }

        return new self($key);
    }

    /**
     * Whether the loaded key is an allowed type and size: EC P-256, P-384, Ed25519 or 2048 to 4096-bit RSA.
     */
    private static function isAllowedKey(mixed $key, string $namespace): bool
    {
        if (! is_object($key)) {
            return false;
        }

        if (is_a($key, $namespace.'\Crypt\RSA\PublicKey') && method_exists($key, 'getLength')) {
            $bits = $key->getLength();

            return is_int($bits) && $bits >= 2048 && $bits <= 4096;
        }

        if (is_a($key, $namespace.'\Crypt\EC\PublicKey') && method_exists($key, 'getCurve')) {
            // phpseclib's names for P-256, P-384 and Ed25519.
            return in_array($key->getCurve(), ['secp256r1', 'secp384r1', 'Ed25519'], true);
        }

        return false;
    }

    /**
     * Verify a raw (already base64 decoded) signature of the message.
     */
    public function verify(string $message, string $signature): bool
    {
        return $this->key->verify($message, $signature) === true;
    }

    /**
     * Resolve an RSA signature padding name to the installed phpseclib's constant.
     *
     * @throws InvalidArgumentException
     */
    public static function rsaSignaturePadding(int|string $padding): int
    {
        if (is_int($padding)) {
            return $padding;
        }

        $constant = self::phpseclibNamespace().'\Crypt\RSA::SIGNATURE_'.strtoupper($padding);

        if (! defined($constant)) {
            throw new InvalidArgumentException("Unsupported RSA signature padding [{$padding}].");
        }

        $value = constant($constant);

        return is_int($value) ? $value : throw new InvalidArgumentException("Unsupported RSA signature padding [{$padding}].");
    }

    /**
     * The root namespace of the installed phpseclib major ("phpseclib4" or "phpseclib3").
     */
    public static function phpseclibNamespace(): string
    {
        return class_exists('phpseclib4\Crypt\PublicKeyLoader') ? 'phpseclib4' : 'phpseclib3';
    }
}
