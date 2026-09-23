<?php

declare(strict_types=1);

namespace Reporting\Tests;

use Barryvdh\DomPDF\ServiceProvider as DomPdfServiceProvider;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Reporting\Providers\ReportingServiceProvider;
use Spatie\Permission\PermissionServiceProvider;
use Support\Models\User;
use Support\TestPanelProvider;

class TestCase extends Orchestra
{
    public const PANEL_ID = 'reporting-test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations();

        // spatie/laravel-permission ships its migration as an unpublished .stub, not an
        // auto-discovered migration - run it directly rather than relying on vendor:publish.
        (require __DIR__ . '/../vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub')->up();

        $this->artisan('migrate');

        Factory::guessFactoryNamesUsing(
            fn (string $modelName): string => 'Reporting\\Database\\Factories\\' . class_basename($modelName) . 'Factory'
        );
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            TestPanelProvider::class,
            FilamentServiceProvider::class,
            PermissionServiceProvider::class,
            DomPdfServiceProvider::class,
            ReportingServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('auth.providers.users.model', User::class);
    }
}
