<?php

use App\Console\Commands\GenerateReport;
use function Laravel\Prompts\text;
use function Laravel\Prompts\select;
use function Laravel\Prompts\info;

test('generates a summary report', function () {
    $this->artisan(GenerateReport::class)
        ->expectsQuestion('Enter Student ID: ', 'student1')      // Provide the student ID
        ->expectsChoice('Report to generate:', 'Diagnostic', ['Diagnostic', 'Progress', 'Feedback']) // Provide report type
        ->expectsOutputToContain('Tony Stark recently completed ')         // Match part of your info output
        ->assertExitCode(0);
});
