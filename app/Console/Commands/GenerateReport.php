<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use function Laravel\Prompts\text;
use function Laravel\Prompts\select;
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

        $report_type = select(
            label: 'Select Report Type:',
            options: [
                'Diagnostic',
                'Progress',
                'Feedback'
            ],
            validate: ['reportType' => 'required|in:Diagnostic,Progress,Feedback','max:255|string']
        );



    }
}
