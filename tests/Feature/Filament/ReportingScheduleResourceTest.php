<?php

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Reporting\Consts\Reporting;
use Reporting\Database\Factories\ReportingScheduleFactory;
use Reporting\Filament\Resources\ReportingScheduleResource;
use function Support\Helpers\testFilamentResource;
use Support\Testing\FilamentResourceTester;
use Support\Testing\FilamentTestHelpers;

uses(RefreshDatabase::class);

testFilamentResource();

beforeEach(function (): void {
    FilamentTestHelpers::actingAsAuthorisedUser();
    //    Filament::setTenant(Team::query()->find(2));

    Filament::setCurrentPanel(Filament::getPanel(Reporting::PANEL));

    $this->tester = new FilamentResourceTester(
        resourceClass: ReportingScheduleResource::class,
        factoryClass: ReportingScheduleFactory::class,
        searchAttribute: 'name',
    );
});
