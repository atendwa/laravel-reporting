<?php

use Reporting\Filament\Resources\ReportHistoryResource;
use Reporting\Filament\Resources\ReportingScheduleResource;
use Reporting\Filament\Resources\ReportResource;

it('keeps its resources out of a tenanted panel\'s scope', function (string $resource): void {
    expect($resource::isScopedToTenant())->toBeFalse();
})->with([ReportResource::class, ReportHistoryResource::class, ReportingScheduleResource::class]);
