<?php

declare(strict_types=1);

use Pest\ArchPresets\Php;

if (class_exists(Php::class)) {
    arch()->preset()->php();

    arch()->preset()->security();
}

arch('it will not use dd(), ddd(), env(), or exit()')
    ->expect(['dd', 'ddd', 'env', 'exit'])
    ->each->not->toBeUsed();

arch('the package source declares strict types')
    ->expect('Laranex\LaravelBiometricAuth')
    ->toUseStrictTypes();

// Helpers defined only by laravel/framework (Illuminate/Foundation/helpers.php); the
// package requires standalone illuminate/* components, so it must not call them.
arch('it only calls helpers that the illuminate/* components define')
    ->expect('Laranex\LaravelBiometricAuth')
    ->not->toUse([
        '__', 'abort', 'abort_if', 'abort_unless', 'action', 'app', 'app_path', 'asset', 'auth',
        'back', 'base_path', 'bcrypt', 'broadcast', 'broadcast_if', 'broadcast_unless', 'cache',
        'config', 'config_path', 'context', 'cookie', 'csrf_field', 'csrf_token', 'database_path',
        'decrypt', 'defer', 'dispatch', 'dispatch_sync', 'encrypt', 'event', 'fake', 'info',
        'lang_path', 'logger', 'logs', 'method_field', 'mix', 'now', 'old', 'policy',
        'precognitive', 'public_path', 'redirect', 'report', 'report_if', 'report_unless',
        'request', 'rescue', 'resolve', 'resource_path', 'response', 'route', 'secure_asset',
        'secure_url', 'session', 'storage_path', 'to_action', 'to_route', 'today', 'trans',
        'trans_choice', 'uri', 'url', 'validator', 'view',
    ]);
