<?php

declare(strict_types=1);

namespace Laranex\LaravelBiometricAuth\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Base class for the package's exceptions.
 *
 * Each exception carries an HTTP status (also used as the default exception code).
 * Laravel calls render() for "renderable" exceptions: requests that expect JSON get
 * {"message": "..."} with that status, every other request falls back to the
 * application's exception handler.
 */
abstract class BiometricException extends Exception
{
    public function __construct(string $message = '', ?int $code = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $code ?? $this->defaultStatusCode(), $previous);
    }

    /**
     * The HTTP status code: the exception code when it is a 4xx/5xx status, the class default otherwise.
     */
    public function getStatusCode(): int
    {
        $code = $this->getCode();

        return $code >= 400 && $code <= 599 ? $code : $this->defaultStatusCode();
    }

    /**
     * The HTTP status the exception renders with when no custom 4xx/5xx code is given.
     */
    abstract protected function defaultStatusCode(): int;

    /**
     * Render the exception as a JSON error for API requests.
     *
     * Returning false lets the application's exception handler render every other request.
     */
    public function render(Request $request): JsonResponse|false
    {
        if (! $request->expectsJson()) {
            return false;
        }

        return new JsonResponse(['message' => $this->getMessage()], $this->getStatusCode());
    }
}
