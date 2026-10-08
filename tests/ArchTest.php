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
