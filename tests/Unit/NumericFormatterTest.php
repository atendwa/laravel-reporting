<?php

declare(strict_types=1);

use Reporting\Utilities\NumericFormatter;

it('formats integers with thousands separator', function (): void {
    $formatter = new NumericFormatter;

    expect($formatter->format(1000))->toBe('1,000');
    expect($formatter->format(1234567))->toBe('1,234,567');
    expect($formatter->format(0))->toBe('0');
});

it('formats floats with two decimal places', function (): void {
    $formatter = new NumericFormatter;

    expect($formatter->format(1234.5))->toBe('1,234.50');
    expect($formatter->format(99.999))->toBe('100.00');
    expect($formatter->format(0.5))->toBe('0.50');
});

it('passes non-numeric values through unchanged', function (): void {
    $formatter = new NumericFormatter;

    expect($formatter->format('hello'))->toBe('hello');
    expect($formatter->format(''))->toBe('');
    expect($formatter->format('N/A'))->toBe('N/A');
});

it('formats numeric strings correctly', function (): void {
    $formatter = new NumericFormatter;

    expect($formatter->format('1234'))->toBe('1,234');
    expect($formatter->format('1234.56'))->toBe('1,234.56');
});

it('identifies numeric values correctly', function (): void {
    $formatter = new NumericFormatter;

    expect($formatter->isNumeric(42))->toBeTrue();
    expect($formatter->isNumeric(3.14))->toBeTrue();
    expect($formatter->isNumeric('100'))->toBeTrue();
    expect($formatter->isNumeric('12.5'))->toBeTrue();
    expect($formatter->isNumeric('hello'))->toBeFalse();
    expect($formatter->isNumeric(''))->toBeFalse();
    expect($formatter->isNumeric(value: null))->toBeFalse();
});
