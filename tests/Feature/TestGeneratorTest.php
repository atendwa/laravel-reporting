<?php

declare(strict_types=1);

use Reporting\Contracts\GeneratorInterface;
use Reporting\Generator\TestGenerator;

it('implements GeneratorInterface', function (): void {
    $generator = new TestGenerator;

    expect($generator)->toBeInstanceOf(GeneratorInterface::class);
});

it('returns report name', function (): void {
    $generator = new TestGenerator;

    expect($generator->getName())->toBe('Test Report');
});

it('returns description', function (): void {
    $generator = new TestGenerator;

    expect($generator->getDescription())
        ->toContain('test report');
});

it('returns headers', function (): void {
    $generator = new TestGenerator;

    expect($generator->getHeaders())
        ->toBeArray()
        ->toHaveCount(5)
        ->toContain('ID', 'Name', 'Category');
});

it('generates data without modifiers', function (): void {
    $generator = new TestGenerator;
    $data = $generator->generate([]);

    expect($data)
        ->toHaveCount(3)
        ->each->toHaveKeys(['id', 'name', 'category', 'amount', 'date']);
});

it('filters data by category modifier', function (): void {
    $generator = new TestGenerator;
    $data = $generator->generate(['category' => 'Alpha']);

    expect($data)->toHaveCount(2);

    $data->each(function (array $row): void {
        expect($row['category'])->toBe('Alpha');
    });
});

it('returns modifiers definition', function (): void {
    $generator = new TestGenerator;
    $modifiers = $generator->getModifiers();

    expect($modifiers)
        ->toBeArray()
        ->toHaveCount(3);

    expect($modifiers[0]['name'])->toBe('date_from');
    expect($modifiers[0]['type'])->toBe('date');
    expect($modifiers[2]['name'])->toBe('category');
    expect($modifiers[2]['type'])->toBe('select');
    expect($modifiers[2]['options'])->toHaveCount(3);
});

it('validates modifiers always returns true', function (): void {
    $generator = new TestGenerator;

    expect($generator->validateModifiers([]))->toBeTrue();
    expect($generator->validateModifiers(['any' => 'thing']))->toBeTrue();
});

it('returns default parameters', function (): void {
    $generator = new TestGenerator;

    expect($generator->getDefaultParameters())
        ->toBeArray()
        ->toHaveKeys(['category', 'date_from', 'date_to']);
});

it('is not heavy', function (): void {
    $generator = new TestGenerator;

    expect($generator->isHeavy())->toBeFalse();
});

it('estimates execution time', function (): void {
    $generator = new TestGenerator;

    expect($generator->estimateExecutionTime())->toBe(60);
});
