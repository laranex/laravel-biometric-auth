<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laranex\LaravelBiometricAuth\Exceptions\BiometricChallengeNotFoundException;
use Laranex\LaravelBiometricAuth\Exceptions\BiometricException;
use Laranex\LaravelBiometricAuth\Exceptions\BiometricNotFoundException;
use Laranex\LaravelBiometricAuth\Exceptions\InvalidPublicKeyException;

it('ships a default message, code and HTTP status for every exception', function (string $class, string $message, int $status) {
    $exception = new $class;

    expect($exception)->toBeInstanceOf(BiometricException::class)
        ->and($exception)->toBeInstanceOf(Exception::class)
        ->and($exception->getMessage())->toBe($message)
        ->and($exception->getCode())->toBe($status)
        ->and($exception->getStatusCode())->toBe($status)
        ->and($exception->getPrevious())->toBeNull();
})->with([
    [BiometricNotFoundException::class, 'Biometric not found', 404],
    [BiometricChallengeNotFoundException::class, 'Biometric challenge not found', 422],
    [InvalidPublicKeyException::class, 'Invalid Public Key', 422],
]);

it('accepts a custom message, code and previous exception', function () {
    $previous = new RuntimeException('cause');
    $exception = new InvalidPublicKeyException('Bad key', 400, $previous);

    expect($exception->getMessage())->toBe('Bad key')
        ->and($exception->getCode())->toBe(400)
        ->and($exception->getStatusCode())->toBe(400)
        ->and($exception->getPrevious())->toBe($previous);
});

it('keeps the default HTTP status when the custom code is not an HTTP error status', function () {
    expect((new BiometricNotFoundException('Gone', 7))->getStatusCode())->toBe(404)
        ->and((new BiometricNotFoundException('Gone', 7))->getCode())->toBe(7);
});

it('renders a JSON error for requests that expect JSON', function () {
    $request = Request::create('/biometrics/1/verify', 'POST');
    $request->headers->set('Accept', 'application/json');

    $response = (new BiometricNotFoundException)->render($request);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(404)
        ->and($response->getData(true))->toBe(['message' => 'Biometric not found']);
});

it('leaves other requests to the application exception handler', function () {
    expect((new BiometricNotFoundException)->render(Request::create('/biometrics/1')))->toBeFalse();
});
