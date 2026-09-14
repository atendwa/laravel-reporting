<?php

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Reporting\Consts\Reporting;
use Reporting\Database\Factories\ReportHistoryFactory;
use Reporting\Filament\Resources\ReportHistoryResource;
use function Support\Helpers\testFilamentResource;
use Support\Testing\FilamentResourceTester;

uses(RefreshDatabase::class);

testFilamentResource();

beforeEach(function (): void {
    //    FilamentTestHelpers::actingAsAuthorisedUser();
    //    Filament::setTenant(Team::query()->find(2));

    Filament::setCurrentPanel(Filament::getPanel(Reporting::PANEL));

    $this->tester = new FilamentResourceTester(
        ReportHistoryResource::class,
        ReportHistoryFactory::class,
        'name',
        true,
    );
});
