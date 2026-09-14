<?php

declare(strict_types=1);

namespace Reporting\Commands;

use Illuminate\Console\Command;
use function Laravel\Prompts\info;
use Reporting\Consts\Reporting;
use Reporting\Services\GeneratorDiscovery;

final class ClearGeneratorCacheCommand extends Command
{
    protected $signature = Reporting::NAME . ':cache:clear';

    protected $description = 'Clear the generator discovery cache';

    public function handle(GeneratorDiscovery $generatorDiscovery): int
    {
        $generatorDiscovery->clearCache();

        info('Generator discovery cache has been cleared.');

        return self::SUCCESS;
    }
}
