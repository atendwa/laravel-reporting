<?php

declare(strict_types=1);

use Reporting\Exceptions\GeneratorNotFoundException;
use Reporting\Generator\TestGenerator;
use Reporting\Services\GeneratorDiscovery;

it('validates a valid generator class', function (): void {
    $generatorDiscovery = app(GeneratorDiscovery::class);

    expect($generatorDiscovery->isValidGenerator(TestGenerator::class))->toBeTrue();
});

it('rejects a non-existent class', function (): void {
    $generatorDiscovery = app(GeneratorDiscovery::class);

    expect($generatorDiscovery->isValidGenerator('App\\NonExistent\\Class'))->toBeFalse();
});

it('rejects a class that does not implement the interface', function (): void {
    $generatorDiscovery = app(GeneratorDiscovery::class);

    expect($generatorDiscovery->isValidGenerator(stdClass::class))->toBeFalse();
});

it('resolves a valid generator', function (): void {
    $generatorDiscovery = app(GeneratorDiscovery::class);
    $generator = $generatorDiscovery->resolve(TestGenerator::class);

    expect($generator)->toBeInstanceOf(TestGenerator::class);
    expect($generator->getName())->toBe('Test Report');
});

it('throws exception for invalid generator', function (): void {
    $generatorDiscovery = app(GeneratorDiscovery::class);

    $generatorDiscovery->resolve('App\\NonExistent\\Generator');
})->throws(GeneratorNotFoundException::class);

it('clears cache without error', function (): void {
    $generatorDiscovery = app(GeneratorDiscovery::class);

    $generatorDiscovery->clearCache();

    expect(value: true)->toBeTrue();
});
