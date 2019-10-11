<?php

namespace CCUPLUS\CourseImport\Commands\Course;

use CCUPLUS\CourseImport\Importer;
use CCUPLUS\EloquentORM\Course;
use CCUPLUS\EloquentORM\Department;
use CCUPLUS\EloquentORM\Dimension;
use CCUPLUS\EloquentORM\Professor;
use CCUPLUS\EloquentORM\Semester;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Import extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'course:import {semester : 學期} {--force} {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '匯入指定學期課程資料至資料庫';

    /**
     * Course data importer.
     *
     * @var Importer
     */
    protected $importer;

    /**
     * Import constructor.
     *
     * @param Importer $importer
     */
    public function __construct(Importer $importer)
    {
        parent::__construct();

        $this->importer = $importer;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if (is_null($semester = $this->semester())) {
            return;
        }

        $data = $this->importer->get($semester->value);

        $departments = $this->departments($data);

        $dimensions = Dimension::all();

        $professors = $this->professors($data);

        foreach ($data as $datum) {
            foreach ($datum['courses'] as $course) {
                /** @var Course $model */

                $model = Course::query()->firstOrCreate(['code' => $course['code']], [
                    'name' => $course['name']['cht'],
                    'department_id' => $departments[$datum['code']],
                    'dimension_id' => optional($dimensions->firstWhere('name', '=', $course['dimension'] ?? null))->getKey(),
                ]);

                if ($model->semesters->where('name', '=', $semester->name)->isEmpty()) {
                    $model->semesters()->save($semester);
                }

                foreach ($course['professor'] as $professor) {
                    $exists = $model->professors()
                        ->wherePivot('professor_id', '=', $professors[$professor])
                        ->wherePivot('semester_id', '=', $semester->getKey())
                        ->exists();

                    if ($exists) {
                        $model->professors()
                            ->wherePivot('semester_id', '=', $semester->getKey())
                            ->updateExistingPivot($professors[$professor], [
                                'class' => $course['class'],
                                'credit' => $course['credit'],
                            ]);
                    } else {
                        $model->professors()
                            ->attach($professors[$professor], [
                                'semester_id' => $semester->getKey(),
                                'class' => $course['class'],
                                'credit' => $course['credit'],
                            ]);
                    }
                }
            }
        }
    }

    /**
     * 取得學期 eloquent model.
     *
     * @return Semester|null
     */
    protected function semester(): ?Semester
    {
        $name = sprintf(
            '%s%s',
            substr($this->argument('semester'), 0, 3),
            Str::endsWith($this->argument('semester'), '1') ? '上' : '下'
        );

        /** @var Semester $semester */

        $semester = Semester::query()->firstOrCreate(['name' => $name]);

        if ($semester->wasRecentlyCreated || $this->option('force')) {
            return $semester;
        }

        $this->error(sprintf('%s 學期課程資料已匯入，如仍欲執行，請加上 --force', $semester->name));

        return null;
    }

    /**
     * 確保系所存在，並取得所有系所資料.
     *
     * @param array $departments
     *
     * @return array
     */
    protected function departments(array $departments): array
    {
        $colleges = [
            '1' => '文學院',
            '2' => '理學院',
            '3' => '社會科學學院',
            '4' => '工學院',
            '5' => '管理學院',
            '6' => '法學院',
            '7' => '教育學院',
        ];

        $exists = Department::all();

        foreach ($departments as ['name' => $name, 'code' => $code]) {
            // 根據 code 第一碼判斷所屬學院
            $college = $colleges[$code[0]] ?? '其他';

            $byCode = $exists->firstWhere('code', '=', $code);

            $byName = $exists->where('college', '=', $college)->firstWhere('name', '=', $name);

            if (is_null($byCode) && is_null($byName)) { // 如果皆為 null，代表尚無此系所資料
                Department::query()->create(compact('college', 'name', 'code'));
            } else if (!is_null($byCode) && is_null($byName) ) { // 如果有 code 但名稱不存在，代表系所名稱變更
                $byCode->update(compact('college', 'name'));
            } else if (is_null($byCode) && !is_null($byName)) { // 如果名稱存在但 code 不存在，代表系所代碼變更
                $byName->update(compact('code'));
            }
        }

        return Department::all()->pluck('id', 'code')->toArray();
    }

    /**
     * 確保教授存在，並取得所有教授資料.
     *
     * @param array $departments
     *
     * @return array
     */
    protected function professors(array $departments): array
    {
        collect($departments)
            ->pluck('courses')
            ->collapse()
            ->pluck('professor')
            ->flatten()
            ->unique()
            ->values()
            ->map(function (string $name) {
                $map = [
                    '李?玲' => '李䊵玲', // BIG-5 無「䊵」此字
                ];

                return $map[$name] ?? $name;
            })
            ->diff(Professor::all()->pluck('name')->toArray())
            ->chunk(50)
            ->each(function (Collection $names) {
                Professor::query()->insert(array_map(function (string $name) {
                    return ['name' => $name];
                }, $names->values()->toArray()));
            });

        return Professor::all()->pluck('id', 'name')->toArray();
    }
}
