<?php

declare(strict_types=1);

namespace Reporting\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use Reporting\Consts\Reporting;

final class MakeGeneratorCommand extends Command
{
    protected $signature = Reporting::NAME . ':make:generator
        {name? : The name of the generator class}
        {--model= : Create a model-backed generator for the given model}
        {--tenant : Make the generator tenant-aware (adds IsTenantAware trait)}';

    protected $description = 'Create a new report generator class';

    public function __construct(private readonly Filesystem $filesystem)
    {
        parent::__construct();
    }

    /**
     * @throws FileNotFoundException
     */
    public function handle(): int
    {
        $name = $this->getGeneratorName();
        $namespace = $this->getNamespace();
        $isHeavy = $this->askIsHeavy();
        $isTenantAware = $this->askIsTenantAware();
        $reportName = $this->getReportName($name);
        $description = $this->getReportDescription($reportName);
        $executionTime = $this->getEstimatedExecutionTime();
        $model = $this->resolveModel();

        $path = $this->getPath($namespace, $name);

        if ($this->filesystem->exists($path)) {
            error('Generator [' . $name . '}] already exists at [' . $path . '].');

            return self::FAILURE;
        }

        $this->filesystem->ensureDirectoryExists(dirname($path));

        $stub = filled($model)
            ? $this->buildModelStub(
                $namespace, $name, $reportName, $description, $isHeavy, $executionTime, $model, $isTenantAware
            )
            : $this->buildPlainStub(
                $namespace, $name, $reportName, $description, $isHeavy, $executionTime, $isTenantAware
            );

        $this->filesystem->put($path, $stub);

        info('Generator [' . $name . '] created successfully at [' . $path . '].');

        if (filled($model)) {
            info('Model [' . $model . '] is wired into the generator.');
        }

        if ($isTenantAware) {
            info("Generator is tenant-aware. Data and options are scoped to the user's teams.");
            info('  - Call $this->applyTenantScope($query, $modifiers) in generate()');
            info('  - Call $this->scopeOptionsToUserTeams($query) for select option queries');
            info('  - Grant cross-tenant access per role in the Roles relation manager');
        }

        info('Next steps:');
        info('  - Implement the generate() method with your query logic');
        info('  - Define modifiers, headers, and default parameters');
        info('  - Register a Report: php artisan ' . Reporting::NAME . ':make:report-from-generator');

        return self::SUCCESS;
    }

    private function getGeneratorName(): string
    {
        $name = $this->argument('name');

        if (is_string($name) && filled($name)) {
            return Str::studly($name);
        }

        $input = text(
            label: 'What should the generator be named?',
            placeholder: 'e.g. MonthlyExpenseReport',
            required: true,
            validate: fn (string $value): ?string => preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $value)
                ? null
                : 'The name must be a valid PHP class name (alphanumeric, starting with a letter).',
        );

        return Str::studly($input);
    }

    private function getNamespace(): string
    {
        /** @var array<int, string> $namespaces */
        $namespaces = config('reporting.generator_namespaces', ['App\\Reports\\Generators']);

        if (count($namespaces) === 1) {
            return $namespaces[0];
        }

        return select('Which namespace should the generator be created in?', $namespaces);
    }

    private function askIsHeavy(): bool
    {
        return confirm(
            label: 'Is this a heavy/resource-intensive report?',
            default: false,
            hint: 'Heavy reports use locking to prevent concurrent execution.',
        );
    }

    private function askIsTenantAware(): bool
    {
        if ($this->option('tenant')) {
            return true;
        }

        return confirm(
            label: 'Should this generator be tenant-aware?',
            default: false,
            hint: 'Tenant-aware generators scope data and options to the user\'s accessible teams '
                . '(team_id). Enables a "Teams" multiselect in the report form.',
        );
    }

    private function getReportName(string $className): string
    {
        $default = (string) Str::of($className)
            ->replaceMatches('/Generator$/', '')
            ->replaceMatches('/Report$/', '')
            ->headline();

        return text(
            label: 'What is the display name of this report?',
            default: $default,
            required: true,
        );
    }

    private function getReportDescription(string $reportName): string
    {
        return text(
            label: 'Describe what this report generates:',
            default: 'Generates the ' . $reportName . ' report.',
            required: true,
        );
    }

    private function getEstimatedExecutionTime(): int
    {
        $choice = select(
            label: 'Estimated execution time?',
            options: [
                '30' => 'Fast (< 30 seconds)',
                '60' => 'Moderate (~ 1 minute)',
                '300' => 'Slow (~ 5 minutes)',
                '600' => 'Very slow (~ 10 minutes)',
            ],
            default: '60',
        );

        return (int) $choice;
    }

    private function resolveModel(): ?string
    {
        $model = $this->option('model');

        if (is_string($model) && filled($model)) {
            return $this->qualifyModel($model);
        }

        $shouldCreate = confirm('Should this generator be backed by an Eloquent model?', default: false);

        if (! $shouldCreate) {
            return null;
        }

        $input = text(
            label: 'Which model should be used?',
            placeholder: 'e.g. User or App\\Models\\User',
            required: true,
        );

        return $this->qualifyModel($input);
    }

    private function qualifyModel(string $model): string
    {
        if (Str::contains($model, '\\')) {
            return $model;
        }

        return 'App\\Models\\' . Str::studly($model);
    }

    private function getPath(string $namespace, string $name): string
    {
        /** @var array<int, string> $namespaces */
        $namespaces = config('reporting.generator_namespaces', ['App\\Reports\\Generators']);

        /** @var array<int, string> $paths */
        $paths = config('reporting.generator_paths', [app_path('Reports/Generators')]);

        $index = array_search($namespace, $namespaces, strict: true);

        $basePath = $index !== false && isset($paths[$index])
            ? $paths[$index]
            : $paths[0];

        return $basePath . '/' . $name . '.php';
    }

    /**
     * @throws FileNotFoundException
     */
    private function buildPlainStub(
        string $namespace,
        string $class,
        string $name,
        string $description,
        bool $isHeavy,
        int $executionTime,
        bool $isTenantAware,
    ): string {
        $stub = $this->filesystem->get($this->stubPath('generator.stub'));

        [$tenantImports, $tenantInterface, $tenantTrait, $tenantModifier, $tenantDefaultParam] =
            $this->resolveTenantPlaceholders($isTenantAware);

        return str_replace(
            [
                '{{ namespace }}',
                '{{ class }}',
                '{{ name }}',
                '{{ description }}',
                '{{ is_heavy }}',
                '{{ execution_time }}',
                '{{ model_import }}',
                '{{ model_query }}',
                '{{ modifiers }}',
                '{{ headers }}',
                '{{ default_parameters }}',
                '{{ tenant_imports }}',
                '{{ tenant_interface }}',
                '{{ tenant_trait }}',
                '{{ tenant_modifier }}',
                '{{ tenant_default_parameter }}',
            ],
            [
                $namespace,
                $class,
                $name,
                $description,
                $isHeavy ? 'true' : 'false',
                (string) $executionTime,
                '',
                'return collect([]);',
                '',
                '',
                '',
                $tenantImports,
                $tenantInterface,
                $tenantTrait,
                $tenantModifier,
                $tenantDefaultParam,
            ],
            $stub,
        );
    }

    /**
     * @throws FileNotFoundException
     */
    private function buildModelStub(
        string $namespace,
        string $class,
        string $name,
        string $description,
        bool $isHeavy,
        int $executionTime,
        string $model,
        bool $isTenantAware,
    ): string {
        $stub = $this->filesystem->get($this->stubPath('generator.model.stub'));
        $modelShort = class_basename($model);

        [$tenantImports, $tenantInterface, $tenantTrait, $tenantModifier, $tenantDefaultParam] =
            $this->resolveTenantPlaceholders($isTenantAware);

        $tenantApplyScope = $isTenantAware
            ? '$this->applyTenantScope($query, $modifiers);'
            : '';

        return str_replace(
            [
                '{{ namespace }}',
                '{{ class }}',
                '{{ name }}',
                '{{ description }}',
                '{{ is_heavy }}',
                '{{ execution_time }}',
                '{{ model_fqcn }}',
                '{{ model }}',
                '{{ model_columns }}',
                '{{ headers }}',
                '{{ tenant_imports }}',
                '{{ tenant_interface }}',
                '{{ tenant_trait }}',
                '{{ tenant_modifier }}',
                '{{ tenant_default_parameter }}',
                '{{ tenant_apply_scope }}',
            ],
            [
                $namespace,
                $class,
                $name,
                $description,
                $isHeavy ? 'true' : 'false',
                (string) $executionTime,
                $model,
                $modelShort,
                "'id' => \$record->id,",
                "'ID'",
                $tenantImports,
                $tenantInterface,
                $tenantTrait,
                $tenantModifier,
                $tenantDefaultParam,
                $tenantApplyScope,
            ],
            $stub,
        );
    }

    /**
     * Build the tenant-related stub replacements.
     *
     * @return array{0: string, 1: string, 2: string, 3: string, 4: string}
     */
    private function resolveTenantPlaceholders(bool $isTenantAware): array
    {
        if (! $isTenantAware) {
            return ['', '', '', '', ''];
        }

        $imports = implode("\n", [
            'use Reporting\Contracts\TenantAwareInterface;',
            'use Reporting\Generator\Concerns\IsTenantAware;',
        ]);

        $interface = ', TenantAwareInterface';
        $trait = 'use IsTenantAware;';
        $modifier = '$this->buildTenantModifier(),';
        $defaultParam = "'tenant_ids' => null,\n            ";

        return [$imports, $interface, $trait, $modifier, $defaultParam];
    }

    private function stubPath(string $stub): string
    {
        return __DIR__ . '/../../stubs/' . $stub;
    }
}
