<?php

declare(strict_types=1);

namespace Reporting\Services;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use Reporting\Consts\CacheKeys;
use Reporting\Consts\CacheTtl;
use Reporting\Contracts\GeneratorInterface;
use Reporting\Exceptions\GeneratorNotFoundException;

final class GeneratorDiscovery
{
    /**
     * Discover all generator classes implementing GeneratorInterface.
     *
     * @return array<int, class-string<GeneratorInterface>>
     */
    public function discover(): array
    {
        return Cache::remember(
            CacheKeys::GENERATORS,
            CacheTtl::ONE_HOUR,
            fn (): array => $this->scanGenerators()
        );
    }

    /**
     * Get generator options for Filament select dropdowns.
     *
     * @return array<string, string>
     */
    public function getGeneratorOptions(): array
    {
        $options = [];

        collect($this->discover())->each(function (string $class) use (&$options): void {
            /** @var GeneratorInterface $instance */
            $instance = app($class);

            $options[$class] = $instance->getName();
        });

        return $options;
    }

    /**
     * Validate that a generator class exists and implements the interface.
     */
    public function isValidGenerator(string $class): bool
    {
        if (! class_exists($class)) {
            return false;
        }

        $reflectionClass = new ReflectionClass($class);

        return $reflectionClass->implementsInterface(GeneratorInterface::class)
            && ! $reflectionClass->isAbstract();
    }

    /**
     * Resolve a generator instance from class name.
     */
    public function resolve(string $class): GeneratorInterface
    {
        if (! $this->isValidGenerator($class)) {
            throw GeneratorNotFoundException::forClass($class);
        }

        return app($class);
    }

    /**
     * Clear the cached generator discovery results.
     */
    public function clearCache(): void
    {
        Cache::forget(CacheKeys::GENERATORS);
    }

    /**
     * Scan configured paths for generator implementations.
     *
     * @return array<int, class-string<GeneratorInterface>>
     *
     * @throws FileNotFoundException
     */
    private function scanGenerators(): array
    {
        $generators = [];

        /** @var array<int, string> $paths */
        $paths = config('reporting.generator_paths', []);

        collect($paths)
            ->filter(fn (string $path): bool => File::isDirectory($path))
            ->each(function (string $path) use (&$generators): void {
                $this->scanDirectory($path, $generators);
            });

        return $generators;
    }

    /**
     * Scan a directory for generator class files.
     *
     * @param  array<int, class-string<GeneratorInterface>>  $generators
     *
     * @throws FileNotFoundException
     */
    private function scanDirectory(string $path, array &$generators): void
    {
        collect(File::files($path))
            ->filter(fn ($file): bool => $file->getExtension() === 'php')
            ->each(function ($file) use (&$generators): void {
                $class = $this->resolveClassName($file->getPathname());

                if ($class !== null && $this->isValidGenerator($class)) {
                    $generators[] = $class;
                }
            });
    }

    /**
     * Resolve the fully qualified class name from a file path.
     *
     * @throws FileNotFoundException
     */
    private function resolveClassName(string $filePath): ?string
    {
        $contents = File::get($filePath);

        if (! preg_match('/namespace\s+(.+?);/', $contents, $nsMatch)) {
            return null;
        }

        if (! preg_match('/^(?:(?:abstract|final)\s+)*class\s+(\w+)/m', $contents, $classMatch)) {
            return null;
        }

        return $nsMatch[1] . '\\' . $classMatch[1];
    }
}
