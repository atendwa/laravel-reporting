<?php

declare(strict_types=1);

namespace Reporting\Filament\Support;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

final class ModifierField
{
    /**
     * @param  array<string, mixed>  $modifier
     */
    public static function make(array $modifier): DatePicker|Select|TextInput
    {
        $name = (string) $modifier['name'];

        $field = match ($modifier['type'] ?? 'text') {
            'date_range', 'date' => DatePicker::make($name),
            'select' => Select::make($name)->options($modifier['options'] ?? []),
            'multiselect' => Select::make($name)->options($modifier['options'] ?? [])->multiple(),
            'number' => TextInput::make($name)->numeric(),
            default => TextInput::make($name),
        };

        return $field->label($modifier['label'] ?? $name)
            ->required($modifier['required'] ?? false)
            ->default($modifier['default'] ?? null);
    }
}
