<?php

use App\Console\Commands\GenerateReport;
use function Laravel\Prompts\text;
use function Laravel\Prompts\select;
use function Laravel\Prompts\info;

test('general validation', function () {
    $this->artisan(GenerateReport::class)
        ->expectsQuestion('Enter Student ID: ', 'student5')
        ->expectsOutputToContain( 'The selected student id is invalid')
        ->assertFailed();
});

test('diagnostic report shows latest score and breakdown', function () {
    $this->artisan(GenerateReport::class)
        ->expectsQuestion('Enter Student ID: ', 'student1')
        ->expectsChoice('Report to generate:', 'Diagnostic', [
            'Diagnostic', 'Progress', 'Feedback'
        ])
        ->expectsOutputToContain('Tony Stark recently completed ')
        ->expectsOutputToContain('He got 15 out of 16. Details by strand given below:')
        ->expectsOutputToContain('Number and Algebra: 5 out of 5 correct')
        ->expectsOutputToContain('Measurement and Geometry: 7 out of 7 correct')
        ->expectsOutputToContain('Statistics and Probability: 3 out of 4 correct')
        ->assertExitCode(0);
});

test('progress report shows attempts and score differences', function () {
    $this->artisan(GenerateReport::class)
        ->expectsQuestion('Enter Student ID: ', 'student1')
        ->expectsChoice('Report to generate:', 'Progress', [
            'Diagnostic', 'Progress', 'Feedback'
        ])
        ->expectsOutputToContain('Tony Stark has completed Numeracy assessment 3 times in total. ')
        ->expectsOutputToContain('Date: 16th December 2021 10:46:00 AM, Raw Score: 15 out of 16')
        ->expectsOutputToContain('Date: 16th December 2020 10:46:00 AM, Raw Score: 10 out of 16')
        ->expectsOutputToContain('Date: 16th December 2019 10:46:00 AM, Raw Score: 6 out of 16')
        ->expectsOutputToContain('Tony Stark got 9 more correct in the recent completed assessment than the oldest')
        ->assertExitCode(0);
});

test('feedback report shows wrong answers and hints', function () {
    $this->artisan(GenerateReport::class)
        ->expectsQuestion('Enter Student ID: ', 'student1')
        ->expectsChoice('Report to generate:', 'Feedback', [
            'Diagnostic', 'Progress', 'Feedback'
        ])
        ->expectsOutputToContain('Tony Stark recently completed Numeracy assessment')
        ->expectsOutputToContain('He got 15 questions right out of 16. Feedback for wrong answers given below:')
        ->expectsOutputToContain("Question: What is the 'median' of the following group of numbers 5, 21, 7, 18, 9?")
        ->expectsOutputToContain('Your answer: option1 with value 7')
        ->expectsOutputToContain('Right answer: option2 with value 9')
        ->assertExitCode(0);
});
