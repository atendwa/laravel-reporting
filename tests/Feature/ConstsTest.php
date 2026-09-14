<?php

declare(strict_types=1);

use Reporting\Consts\CacheKeys;
use Reporting\Consts\CacheTtl;
use Reporting\Consts\Reporting;
use Reporting\Consts\ReportSource;
use Reporting\Consts\ReportStatus;

it('has correct feature name', function (): void {
    expect(Reporting::NAME)->toBe('reporting');
    expect(Reporting::DISPLAY_NAME)->toBe('Reporting');
    expect(Reporting::TEST_GROUP)->toBe('reporting-plugin');
});

it('generates command names', function (): void {
    expect(Reporting::commandName('test'))->toBe('reporting:test');
    expect(Reporting::commandName('reset'))->toBe('reporting:reset');
});

it('has all report statuses', function (): void {
    expect(ReportStatus::STATUS_PENDING)->toBe('pending');
    expect(ReportStatus::STATUS_PROCESSING)->toBe('processing');
    expect(ReportStatus::STATUS_COMPLETED)->toBe('completed');
    expect(ReportStatus::STATUS_FAILED)->toBe('failed');
    expect(ReportStatus::options())->toHaveCount(4);
});

it('has all report sources', function (): void {
    expect(ReportSource::SOURCE_SCHEDULED)->toBe('scheduled');
    expect(ReportSource::SOURCE_MANUAL)->toBe('manual');
    expect(ReportSource::options())->toHaveCount(2);
});

it('has cache keys', function (): void {
    expect(CacheKeys::GENERATORS)->toBe('reporting.generators');
    expect(CacheKeys::SCHEDULED_REPORTS)->toBe('reporting.scheduled_reports');
});

it('has cache TTL values', function (): void {
    expect(CacheTtl::ONE_MINUTE)->toBe(60);
    expect(CacheTtl::ONE_HOUR)->toBe(3600);
    expect(CacheTtl::ONE_DAY)->toBe(86400);
});
