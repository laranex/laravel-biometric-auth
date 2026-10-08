<?php

declare(strict_types=1);

use Laranex\LaravelBiometricAuth\Exceptions\BiometricChallengeNotFoundException;
use Laranex\LaravelBiometricAuth\Exceptions\BiometricNotFoundException;
use Laranex\LaravelBiometricAuth\Exceptions\InvalidPublicKeyException;

it('ships a default message and code for every exception', function (string $class, string $message) {
    $exception = new $class;

    expect($exception)->toBeInstanceOf(Exception::class)
        ->and($exception->getMessage())->toBe($message)
        ->and($exception->getCode())->toBe(500)
        ->and($exception->getPrevious())->toBeNull();
})->with([
    [BiometricNotFoundException::class, 'Biometric not found'],
    [BiometricChallengeNotFoundException::class, 'Biometric challenge not found'],
    [InvalidPublicKeyException::class, 'Invalid Public Key'],
]);

it('accepts a custom message, code and previous exception', function () {
    $previous = new RuntimeException('cause');
    $exception = new InvalidPublicKeyException('Bad key', 422, $previous);

    expect($exception->getMessage())->toBe('Bad key')
        ->and($exception->getCode())->toBe(422)
        ->and($exception->getPrevious())->toBe($previous);
});
