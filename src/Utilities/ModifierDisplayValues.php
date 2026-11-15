<?php

declare(strict_types=1);

namespace Reporting\Utilities;

use Reporting\Contracts\GeneratorInterface;

final class ModifierDisplayValues
{
    /**
     * @param  array<string, mixed>  $modifiers
     *
     * @return array<string, string>
     */
    public function resolve(GeneratorInterface $generator, array $modifiers): array
    {
        $definitions = collect($generator->getModifiers())->keyBy('name');

        return collect($modifiers)->mapWithKeys(function (mixed $value, string|int $key) use ($definitions): array {
            $definition = $definitions->get($key);
            $label = $definition['label'] ?? ucfirst(str_replace('_', ' ', (string) $key));

            return [$label => $this->display($value, $definition['options'] ?? [])];
        })->all();
    }

    /**
     * @param  array<array-key, mixed>  $options
     */
    private function display(mixed $value, array $options): string
    {
        return collect(is_array($value) ? $value : [$value])
            ->map(fn (mixed $item): string => (string) ($options[$item] ?? $item))
            ->implode(', ');
    }
}
