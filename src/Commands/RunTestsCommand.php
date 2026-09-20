<?php

declare(strict_types=1);

namespace Reporting\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;
use Reporting\Consts\Reporting;

final class RunTestsCommand extends Command
{
    protected $signature = Reporting::NAME . ':plugin:test {--no-coverage : Run tests without coverage}';

    protected $description = 'Run Reporting plugin tests with coverage and display the score';

    public function handle(): int
    {
        info('Running ' . Reporting::DISPLAY_NAME . ' tests...');

        DB::disconnect();

        $coverageFlag = $this->option('no-coverage') ? '' : '--coverage';
        $command = sprintf(
            'cd %s && APP_ENV=testing ./vendor/bin/pest --group=%s %s --colors=never 2>&1',
            escapeshellarg(base_path()),
            escapeshellarg(Reporting::TEST_GROUP),
            $coverageFlag
        );

        exec($command, $outputLines, $exitCode);
        $output = implode("\n", $outputLines);

        $testSummary = $this->extractTestSummary($output);
        $coverageScore = $this->extractCoverageScore($output);

        if ($testSummary !== null) {
            when(str_contains($testSummary, 'failed'), fn () => error($testSummary));
            when(! str_contains($testSummary, 'failed'), fn () => info($testSummary));
        }

        if (! $this->option('no-coverage')) {
            when($coverageScore !== null, fn () => info('Coverage: ' . $coverageScore));
            when($coverageScore === null, fn () => warning('Could not extract coverage score.'));
        }

        return $exitCode;
    }

    private function extractCoverageScore(string $output): ?string
    {
        if (preg_match('/Total:\s*([\d.]+\s*%)/i', $output, $matches)) {
            return mb_trim($matches[1]);
        }

        return null;
    }

    private function extractTestSummary(string $output): ?string
    {
        if (preg_match('/Tests:\s*(.+?)(?:\r?\n|$)/i', $output, $matches)) {
            return 'Tests: ' . mb_trim($matches[1]);
        }

        return null;
    }
}
