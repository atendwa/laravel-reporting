<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\File;

afterEach(function (): void {
    $testFiles = [
        'MonthlyExpense.php',
        'UserActivity.php',
        'OrderSummary.php',
        'Duplicate.php',
        'SyntaxCheck.php',
        'TenantSales.php',
    ];

    foreach ($testFiles as $testFile) {
        $path = app_path('Reports/Generators/' . $testFile);

        if (File::exists($path)) {
            File::delete($path);
        }
    }
});

it('creates a plain generator via prompts', function (): void {
    $this->artisan('reporting:make:generator')
        ->expectsQuestion('What should the generator be named?', 'MonthlyExpense')
        ->expectsQuestion('Is this a heavy/resource-intensive report?', false)
        ->expectsQuestion('Should this generator be tenant-aware?', false)
        ->expectsQuestion('What is the display name of this report?', 'Monthly Expense')
        ->expectsQuestion('Describe what this report generates:', 'Generates the Monthly Expense report.')
        ->expectsQuestion('Estimated execution time?', '60')
        ->expectsQuestion('Should this generator be backed by an Eloquent model?', false)
        ->assertSuccessful();

    $path = app_path('Reports/Generators/MonthlyExpense.php');

    expect($path)->toBeFile();

    $contents = File::get($path);

    expect($contents)
        ->toContain('namespace App\\Reports\\Generators;')
        ->toContain('class MonthlyExpense implements GeneratorInterface')
        ->toContain("return 'Monthly Expense';")
        ->toContain('return false;')
        ->toContain('return 60;')
        ->not->toContain('IsTenantAware')
        ->not->toContain('TenantAwareInterface');
});

it('creates a model-backed generator via --model option', function (): void {
    $this->artisan('reporting:make:generator', [
        'name' => 'UserActivity',
        '--model' => 'User',
    ])
        ->expectsQuestion('Is this a heavy/resource-intensive report?', true)
        ->expectsQuestion('Should this generator be tenant-aware?', false)
        ->expectsQuestion('What is the display name of this report?', 'User Activity')
        ->expectsQuestion('Describe what this report generates:', 'Generates the User Activity report.')
        ->expectsQuestion('Estimated execution time?', '300')
        ->assertSuccessful();

    $path = app_path('Reports/Generators/UserActivity.php');

    expect($path)->toBeFile();

    $contents = File::get($path);

    expect($contents)
        ->toContain('namespace App\\Reports\\Generators;')
        ->toContain('class UserActivity implements GeneratorInterface')
        ->toContain('use App\\Models\\User;')
        ->toContain('User::query()')
        ->toContain('return true;')
        ->toContain('return 300;');
});

it('creates a model-backed generator via prompt', function (): void {
    $this->artisan('reporting:make:generator', ['name' => 'OrderSummary'])
        ->expectsQuestion('Is this a heavy/resource-intensive report?', false)
        ->expectsQuestion('Should this generator be tenant-aware?', false)
        ->expectsQuestion('What is the display name of this report?', 'Order Summary')
        ->expectsQuestion('Describe what this report generates:', 'Generates the Order Summary report.')
        ->expectsQuestion('Estimated execution time?', '60')
        ->expectsQuestion('Should this generator be backed by an Eloquent model?', true)
        ->expectsQuestion('Which model should be used?', 'App\\Models\\Order')
        ->assertSuccessful();

    $path = app_path('Reports/Generators/OrderSummary.php');

    expect($path)->toBeFile();

    $contents = File::get($path);

    expect($contents)
        ->toContain('use App\\Models\\Order;')
        ->toContain('Order::query()');
});

it('fails when generator already exists', function (): void {
    $path = app_path('Reports/Generators/Duplicate.php');

    File::ensureDirectoryExists(dirname($path));
    File::put($path, '<?php // existing');

    $this->artisan('reporting:make:generator', ['name' => 'Duplicate'])
        ->expectsQuestion('Is this a heavy/resource-intensive report?', false)
        ->expectsQuestion('Should this generator be tenant-aware?', false)
        ->expectsQuestion('What is the display name of this report?', 'Duplicate')
        ->expectsQuestion('Describe what this report generates:', 'Test.')
        ->expectsQuestion('Estimated execution time?', '30')
        ->expectsQuestion('Should this generator be backed by an Eloquent model?', false)
        ->assertFailed();
});

it('generates valid php syntax', function (): void {
    $this->artisan('reporting:make:generator', [
        'name' => 'SyntaxCheck',
        '--model' => User::class,
    ])
        ->expectsQuestion('Is this a heavy/resource-intensive report?', false)
        ->expectsQuestion('Should this generator be tenant-aware?', false)
        ->expectsQuestion('What is the display name of this report?', 'Syntax Check')
        ->expectsQuestion('Describe what this report generates:', 'Test.')
        ->expectsQuestion('Estimated execution time?', '30')
        ->assertSuccessful();

    $path = app_path('Reports/Generators/SyntaxCheck.php');
    $result = exec('php -l ' . escapeshellarg($path) . ' 2>&1', $output, $exitCode);

    expect($exitCode)->toBe(0);
});

it('creates a tenant-aware generator via --tenant flag', function (): void {
    $this->artisan('reporting:make:generator', [
        'name' => 'TenantSales',
        '--tenant' => true,
    ])
        ->expectsQuestion('Is this a heavy/resource-intensive report?', false)
        ->expectsQuestion('What is the display name of this report?', 'Tenant Sales')
        ->expectsQuestion('Describe what this report generates:', 'Generates the Tenant Sales report.')
        ->expectsQuestion('Estimated execution time?', '60')
        ->expectsQuestion('Should this generator be backed by an Eloquent model?', false)
        ->assertSuccessful();

    $path = app_path('Reports/Generators/TenantSales.php');

    expect($path)->toBeFile();

    $contents = File::get($path);

    expect($contents)
        ->toContain('use Reporting\\Contracts\\TenantAwareInterface;')
        ->toContain('use Reporting\\Generator\\Concerns\\IsTenantAware;')
        ->toContain('implements GeneratorInterface, TenantAwareInterface')
        ->toContain('use IsTenantAware;')
        ->toContain('$this->buildTenantModifier()')
        ->toContain("'tenant_ids' => null");
});

it('creates a tenant-aware generator via prompt', function (): void {
    $this->artisan('reporting:make:generator', ['name' => 'TenantSales'])
        ->expectsQuestion('Is this a heavy/resource-intensive report?', false)
        ->expectsQuestion('Should this generator be tenant-aware?', true)
        ->expectsQuestion('What is the display name of this report?', 'Tenant Sales')
        ->expectsQuestion('Describe what this report generates:', 'Generates the Tenant Sales report.')
        ->expectsQuestion('Estimated execution time?', '60')
        ->expectsQuestion('Should this generator be backed by an Eloquent model?', false)
        ->assertSuccessful();

    $path = app_path('Reports/Generators/TenantSales.php');

    expect($path)->toBeFile();

    $contents = File::get($path);

    expect($contents)
        ->toContain('use Reporting\\Generator\\Concerns\\IsTenantAware;')
        ->toContain('use IsTenantAware;')
        ->toContain('$this->buildTenantModifier()');
});
