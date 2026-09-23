<?php

use Filament\Facades\Filament;
use Reporting\Database\Factories\ReportFactory;
use Reporting\Filament\Resources\ReportResource;
use Reporting\Tests\TestCase;
use function Support\Helpers\testFilamentResource;
use Support\Testing\FilamentResourceTester;
use Support\Testing\FilamentTestHelpers;

testFilamentResource();

beforeEach(function (): void {
    FilamentTestHelpers::actingAsAuthorisedUser();
    Filament::setCurrentPanel(Filament::getPanel(TestCase::PANEL_ID));

    $this->tester = new FilamentResourceTester(
        ReportResource::class,
        ReportFactory::class,
        'name',
        true,
    );
});
