<?php

namespace CCUPLUS\CourseImport;

use Illuminate\Support\ServiceProvider;

class CourseImportServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\Course\Import::class,
            ]);
        }
    }
}
