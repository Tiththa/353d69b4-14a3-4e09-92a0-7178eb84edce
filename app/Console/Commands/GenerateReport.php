<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use function Laravel\Prompts\text;
use function Laravel\Prompts\select;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

class GenerateReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        // load students json
        $json_students = Storage::disk('local')->get('data/students.json');
        $students = json_decode($json_students, true);
        $valid_ids = collect($students)->pluck('id')->toArray();


        // get 2 values: Student ID and Report to generate (Diagnostic, Progress,  Feedback)
        note('Please enter the following');

        $student_id = text(
            label: 'Enter Student ID: ',
            validate: [
                'student_id' => [
                    'required',
                    'max:255',
                    'string',
                    Rule::in($valid_ids)
                ]
            ]
        );

        $report_types_check = ['Diagnostic', 'Progress', 'Feedback'];

        $report_type = select(
            label: 'Report to generate:',
            options: [
                'Diagnostic',
                'Progress',
                'Feedback'
            ],
            validate: [
                'report_type' => [
                    fn(string $value) => in_array($value, $report_types_check) ? null : 'The selected report type is invalid.',
                    'max:255',
                    'string',
                ]
            ]
        );

        $this->generateReport($students, $student_id, $report_type);

    }

    protected function generateReport($students, $student_id, $report_type)
    {

        // filter students array to get the student with the given ID
        $student = collect($students)->firstWhere('id', $student_id);

        if ($report_type == 'Diagnostic') {


            // get the active assessments
            $json_students = Storage::disk('local')->get('data/assessments.json');
            $assessments = collect(json_decode($json_students, true));

            // loop through assessments
            foreach ($assessments as $assessment) {
                $assessment_id = $assessment['id'];

                // get the active assessments
                $json_responses = Storage::disk('local')->get('data/student-responses.json');
                $student_responses = collect(json_decode($json_responses, true))
                    ->where('student.id', $student_id)
                    ->where('assessmentId', $assessment_id)
                    ->where('completed', true)
                    ->sortByDesc('completed');

                // take the most recent completed assessment for diagnostic report

                $recent_response = $student_responses->first();
                $responses_count_total = count($recent_response['responses']) ?? 0;
                $results_count = $recent_response['results']['rawScore'] ?? 0;
                $recent_question_responses = $recent_response['responses'] ?? [];

                // print out the first portion of the report
                $student_name = $student['firstName'] . ' ' . $student['lastName'];

                // format completed at
                $completed_date = $recent_response['completed'];
                $carbon_instance = Carbon::createFromFormat('d/m/Y H:i:s', $completed_date);


                info($student_name . ' recently completed ' . $assessment['name'] . ' assessment on ' . $carbon_instance->format('jS F Y h:i:s A') . '.');
                info('He got ' . $results_count . ' out of ' . $responses_count_total . '. Details by strand given below:');

                // get the breakdown of results by category from questions

                $json_questions = Storage::disk('local')->get('data/questions.json');
                $questions = collect(json_decode($json_questions, true))->keyBy('id');

                $breakdown = collect($recent_question_responses)
                    ->map(function ($response) use ($questions) {
                        $question = $questions->get($response['questionId']);
                        $is_correct = $response['response'] === ($question['config']['key'] ?? null);

                        return [
                            'strand' => $question['strand'] ?? 'Unknown',
                            'isCorrect' => $is_correct,
                        ];
                    })
                    ->groupBy('strand')
                    ->map(function ($items) {
                        $total = $items->count();
                        $correct = $items->where('isCorrect', true)->count();

                        return [
                            'correct' => $correct,
                            'total' => $total,
                            'summary' => "{$correct} out of {$total} correct",
                        ];
                    });


                foreach ($breakdown as $strand => $stats) {
                    info("{$strand}: {$stats['summary']}");
                }


            }
        }

        if ($report_type == 'Progress') {

            // get the active assessments
            $json_students = Storage::disk('local')->get('data/assessments.json');
            $assessments = collect(json_decode($json_students, true));

            $student_name = $student['firstName'] . ' ' . $student['lastName'];

            // loop through assessments
            foreach ($assessments as $assessment) {
                $assessment_id = $assessment['id'];

                // get the active assessments
                $json_responses = Storage::disk('local')->get('data/student-responses.json');
                $student_responses = collect(json_decode($json_responses, true))
                    ->where('student.id', $student_id)
                    ->where('assessmentId', $assessment_id)
                    ->where('completed', true)
                    ->sortByDesc('completed');

                // take all completed assessments for progress report
                $assessment_attempts = $student_responses->count();
                info($student_name . ' has completed ' . $assessment['name'] . ' assessment ' . $assessment_attempts . ' times in total. Date and raw score given below:');

                foreach ($student_responses as $assessment_attempt) {

                    // format completed at
                    $completed_date = $assessment_attempt['completed'];
                    $carbon_instance = Carbon::createFromFormat('d/m/Y H:i:s', $completed_date);

                    $results_count = $assessment_attempt['results']['rawScore'] ?? 0;
                    $total_responses_count = count($assessment_attempt['responses']) ?? 0;

                    info('Date: ' . $carbon_instance->format('jS F Y h:i:s A') . ', Raw Score: ' . $results_count . ' out of ' . $total_responses_count);

                }

                // oldest and newest attempt comparison

                $recent_attempt = $student_responses->first();
                $oldest_attempt = $student_responses->last();

                if ($recent_attempt && $oldest_attempt) {
                    $recent_score = $recent_attempt['results']['rawScore'] ?? 0;
                    $oldest_score = $oldest_attempt['results']['rawScore'] ?? 0;

                    $score_diff = $recent_score - $oldest_score;

                    if ($score_diff > 0) {
                        info($student_name . " got {$score_diff} more correct in the recent completed assessment than the oldest.");
                    } elseif ($score_diff < 0) {
                        info($student_name . " got " . abs($score_diff) . " fewer correct in the recent completed assessment than the oldest.");
                    } else {
                        info($student_name . " got the same number correct in the recent and the oldest assessments.");
                    }
                }

            }

        }

        if ($report_type == 'Feedback') {

            // get the active assessments
            $json_students = Storage::disk('local')->get('data/assessments.json');
            $assessments = collect(json_decode($json_students, true));

            $student_name = $student['firstName'] . ' ' . $student['lastName'];

            // loop through assessments
            foreach ($assessments as $assessment) {
                $assessment_id = $assessment['id'];

                // get the active assessments
                $json_responses = Storage::disk('local')->get('data/student-responses.json');
                $student_responses = collect(json_decode($json_responses, true))
                    ->where('student.id', $student_id)
                    ->where('assessmentId', $assessment_id)
                    ->where('completed', true)
                    ->sortByDesc('completed');


                $recent_attempt = $student_responses->first();

                if ($recent_attempt) {
                    $completed_date = Carbon::createFromFormat('d/m/Y H:i:s', $recent_attempt['completed']);

                    $results_count = $recent_attempt['results']['rawScore'] ?? 0;
                    $total_responses_count = count($recent_attempt['responses']) ?? 0;

                    info("{$student_name} recently completed {$assessment['name']} assessment on " . $completed_date->format('jS F Y h:i A'));
                    info("He got {$results_count} questions right out of {$total_responses_count}. Feedback for wrong answers given below:");

                    $json_questions = Storage::disk('local')->get('data/questions.json');
                    $questions = collect(json_decode($json_questions, true))->keyBy('id');

                    foreach ($recent_attempt['responses'] as $response) {
                        $question = $questions->get($response['questionId']);

                        $correct_answer = $question['config']['key'] ?? null;
                        $is_correct = $response['response'] === $correct_answer;

                        if (!$is_correct) {

                            $correct_option = collect($question['config']['options'])->firstWhere('id', $correct_answer);
                            $given_option = collect($question['config']['options'])->firstWhere('id', $response['response']);

                            info("Question: " . $question['stem']);
                            info("Your answer: {$given_option['id']} with value {$given_option['value']}");
                            info("Right answer: {$correct_option['id']} with value {$correct_option['value']}");
                            if (!empty($question['config']['hint'])) {
                                info("Hint: " . $question['config']['hint']);
                            }
                        }
                    }
                }


            }
        }


    }

}
