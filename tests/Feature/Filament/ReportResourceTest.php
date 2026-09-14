<?php

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Reporting\Consts\Reporting;
use Reporting\Database\Factories\ReportFactory;
use Reporting\Filament\Resources\ReportResource;
use function Support\Helpers\testFilamentResource;
use Support\Testing\FilamentResourceTester;
use Support\Testing\FilamentTestHelpers;

uses(RefreshDatabase::class);

testFilamentResource();

beforeEach(function (): void {
    FilamentTestHelpers::actingAsAuthorisedUser();
    Filament::setCurrentPanel(Filament::getPanel(Reporting::PANEL));

    //    Filament::setTenant(Team::query()->find(2));

    $this->tester = new FilamentResourceTester(
        ReportResource::class,
        ReportFactory::class,
        'name',
        true,
    );
});
