<?php

declare(strict_types=1);

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Reporting\Filament\Support\ModifierField;

it('builds the field for each modifier type', function (string $type, string $class, bool $multiple): void {
    $field = ModifierField::make(['name' => 'region', 'label' => 'Region', 'type' => $type, 'required' => true, 'default' => 'north', 'options' => ['north' => 'North']]);

    expect($field)->toBeInstanceOf($class)
        ->and($field->getName())->toBe('region')
        ->and($field->getLabel())->toBe('Region')
        ->and($field->isRequired())->toBeTrue()
        ->and($field->getDefaultState())->toBe('north');

    if ($field instanceof Select) {
        expect($field->isMultiple())->toBe($multiple)->and($field->getOptions())->toBe(['north' => 'North']);
    }
})->with([
    ['date', DatePicker::class, false],
    ['date_range', DatePicker::class, false],
    ['select', Select::class, false],
    ['multiselect', Select::class, true],
    ['number', TextInput::class, false],
    ['text', TextInput::class, false],
]);

it('falls back to a text field named after the modifier', function (): void {
    $field = ModifierField::make(['name' => 'reference']);

    expect($field)->toBeInstanceOf(TextInput::class)
        ->and($field->getLabel())->toBe('reference')
        ->and($field->isRequired())->toBeFalse();
});
