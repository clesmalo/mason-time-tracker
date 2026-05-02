<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $companies = Company::factory(3)->create();
        $employees = Employee::factory(10)->create();

        // Attach each employee to 1-2 companies
        $employees->each(function (Employee $employee) use ($companies) {
            $employee->companies()->attach(
                $companies->random(rand(1, 2))->pluck('id')
            );
        });

        // Create 2-3 projects per company and assign employees to them
        $companies->each(function (Company $company) use ($employees) {
            $projects = Project::factory(rand(2, 3))->create(['company_id' => $company->id]);

            // Employees that belong to this company
            $companyEmployeeIds = $company->employees()->pluck('employees.id');

            $projects->each(function (Project $project) use ($companyEmployeeIds) {
                // Assign a random subset of the company's employees to each project
                $project->employees()->attach(
                    $companyEmployeeIds->random(max(1, rand(1, $companyEmployeeIds->count())))
                );

                // 2-4 tasks linked to this project
                Task::factory(rand(2, 4))->create([
                    'company_id' => $project->company_id,
                    'project_id' => $project->id,
                ]);
            });

            // 1-2 company-level tasks (no project)
            Task::factory(rand(1, 2))->create(['company_id' => $company->id]);
        });

        // Create time entries
        $companies->each(function (Company $company) {
            $employees = $company->employees;
            $tasks     = $company->tasks()->with('project')->get();

            // 15-25 entries per company
            $count = rand(15, 25);
            for ($i = 0; $i < $count; $i++) {
                $employee = $employees->random();
                $task     = $tasks->random();

                // Respect the optional project link on the task
                $projectId = $task->project_id;

                TimeEntry::factory()->create([
                    'company_id'  => $company->id,
                    'employee_id' => $employee->id,
                    'project_id'  => $projectId,
                    'task_id'     => $task->id,
                ]);
            }
        });
    }
}
