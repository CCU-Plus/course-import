<?php

namespace CCUPLUS\CourseImport\Commands\Course;

use Illuminate\Console\Command;

class Import extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'course:import {semester} {--force} {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '匯入指定學期課程資料至資料庫';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        //
    }
}
