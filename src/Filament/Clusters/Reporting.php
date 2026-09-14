<?php

declare(strict_types=1);

namespace Reporting\Filament\Clusters;

use BackedEnum;
use Filament\Clusters\Cluster;

final class Reporting extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';
}
