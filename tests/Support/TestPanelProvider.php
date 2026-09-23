<?php

declare(strict_types=1);

namespace Support;

use Filament\Panel;
use Filament\PanelProvider;
use Reporting\Filament\Plugins\ReportingPlugin;
use Reporting\Tests\TestCase;

class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id(TestCase::PANEL_ID)
            ->path(TestCase::PANEL_ID)
            ->default()
            ->plugin(ReportingPlugin::make());
    }
}
