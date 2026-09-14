<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;
use Reporting\Commands\CleanupReportsCommand;

Schedule::command(CleanupReportsCommand::class)->daily();
