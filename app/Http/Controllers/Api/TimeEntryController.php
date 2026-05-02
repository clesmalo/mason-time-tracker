<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTimeEntryRequest;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class TimeEntryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TimeEntry::select(
            'time_entries.*',
            'companies.name  as company_name',
            'employees.name  as employee_name',
            'projects.name   as project_name',
            'tasks.name      as task_name'
        )
        ->leftJoin('companies', 'time_entries.company_id',  '=', 'companies.id')
        ->leftJoin('employees', 'time_entries.employee_id', '=', 'employees.id')
        ->leftJoin('projects',  'time_entries.project_id',  '=', 'projects.id')
        ->leftJoin('tasks',     'time_entries.task_id',     '=', 'tasks.id');

        // Custom filters applied before recordsTotal
        $query
            ->when($request->filled('company_id'),  fn ($q) => $q->where('time_entries.company_id',  $request->company_id))
            ->when($request->filled('employee_id'), fn ($q) => $q->where('time_entries.employee_id', $request->employee_id))
            ->when($request->filled('project_id'),  fn ($q) => $q->where('time_entries.project_id',  $request->project_id))
            ->when($request->filled('task_id'),    fn ($q) => $q->where('time_entries.task_id', $request->task_id))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('time_entries.started_at', '>=', $request->date_from))
            ->when($request->filled('date_to'),   fn ($q) => $q->whereDate('time_entries.started_at', '<=', $request->date_to));

        $recordsTotal = $query->count();

        // Global DataTables search across key text columns
        $search = $request->input('search.value');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('companies.name',  'like', "%{$search}%")
                  ->orWhere('employees.name', 'like', "%{$search}%")
                  ->orWhere('projects.name',  'like', "%{$search}%")
                  ->orWhere('tasks.name',     'like', "%{$search}%");
            });
        }

        $recordsFiltered = $query->count();

        // Column sort mapping
        $columnMap = [
            0 => 'companies.name',
            1 => 'time_entries.started_at',
            2 => 'employees.name',
            3 => 'projects.name',
            4 => 'tasks.name',
            5 => 'time_entries.started_at',
        ];

        $colIndex  = (int) $request->input('order.0.column', 1);
        $dir       = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $orderCol  = $columnMap[$colIndex] ?? 'time_entries.started_at';

        $entries = $query
            ->orderBy($orderCol, $dir)
            ->offset((int) $request->input('start', 0))
            ->limit((int) $request->input('length', 15))
            ->get();

        $data = $entries->map(function (TimeEntry $entry) {
            $start   = Carbon::parse($entry->started_at);
            $end     = Carbon::parse($entry->ended_at);
            $mins    = (int) $start->diffInMinutes($end);
            $h       = intdiv($mins, 60);
            $m       = $mins % 60;
            $duration = $h > 0 ? ($m > 0 ? "{$h}h {$m}m" : "{$h}h") : "{$m}m";

            return [
                'company'    => $entry->company_name  ?? '—',
                'date'       => $start->format('M j, Y'),
                'employee'   => $entry->employee_name ?? '—',
                'project'    => $entry->project_name  ?? '—',
                'task'       => $entry->task_name     ?? '—',
                'started_at' => $start->format('g:i A'),
                'ended_at'   => $end->format('g:i A'),
                'duration'   => $duration,
            ];
        });

        return response()->json([
            'draw'            => (int) $request->input('draw', 1),
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $query = TimeEntry::query()
            ->when($request->filled('company_id'),  fn ($q) => $q->where('company_id',  $request->company_id))
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->filled('project_id'),  fn ($q) => $q->where('project_id',  $request->project_id))
            ->when($request->filled('task_id'),     fn ($q) => $q->where('task_id',     $request->task_id))
            ->when($request->filled('date_from'),   fn ($q) => $q->whereDate('started_at', '>=', $request->date_from))
            ->when($request->filled('date_to'),     fn ($q) => $q->whereDate('started_at', '<=', $request->date_to));

        $entries = $query->get(['started_at', 'ended_at', 'employee_id', 'project_id']);

        $totalMinutes = $entries->sum(
            fn ($e) => (int) Carbon::parse($e->started_at)->diffInMinutes(Carbon::parse($e->ended_at))
        );

        $h = intdiv($totalMinutes, 60);
        $m = $totalMinutes % 60;
        $totalHours = $totalMinutes === 0
            ? '0h'
            : ($h > 0 ? ($m > 0 ? "{$h}h {$m}m" : "{$h}h") : "{$m}m");

        return response()->json([
            'total_hours'     => $totalHours,
            'total_records'   => $entries->count(),
            'total_projects'  => $request->filled('project_id')
                ? null
                : $entries->whereNotNull('project_id')->pluck('project_id')->unique()->count(),
            'total_employees' => $request->filled('employee_id')
                ? null
                : $entries->pluck('employee_id')->unique()->count(),
        ]);
    }

    public function store(StoreTimeEntryRequest $request): JsonResponse
    {
        $data = $request->validated();

        $projectId = $data['project_id'] ?? null;

        // 1. Employee must belong to the company
        $employee = Employee::find($data['employee_id']);
        if (!$employee->companies()->where('companies.id', $data['company_id'])->exists()) {
            throw ValidationException::withMessages([
                'employee_id' => 'The selected employee does not belong to the selected company.',
            ]);
        }

        // 2. Project must belong to the company
        if ($projectId !== null) {
            $project = Project::find($projectId);
            if ((int) $project->company_id !== (int) $data['company_id']) {
                throw ValidationException::withMessages([
                    'project_id' => 'The selected project does not belong to the selected company.',
                ]);
            }
        }

        // 3. Task must belong to the company
        $task = Task::find($data['task_id']);
        if ((int) $task->company_id !== (int) $data['company_id']) {
            throw ValidationException::withMessages([
                'task_id' => 'The selected task does not belong to the selected company.',
            ]);
        }

        // 4. Task's project must match the entry's project
        if ($task->project_id !== $projectId) {
            throw ValidationException::withMessages([
                'task_id' => 'The selected task does not belong to the selected project.',
            ]);
        }

        // 5. One project per employee per calendar date
        $date = Carbon::parse($data['started_at'])->toDateString();

        $conflict = TimeEntry::where('employee_id', $data['employee_id'])
            ->whereDate('started_at', $date)
            ->when(
                $projectId !== null,
                fn ($q) => $q->where(fn ($q2) => $q2->where('project_id', '!=', $projectId)->orWhereNull('project_id')),
                fn ($q) => $q->whereNotNull('project_id')
            )
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'project_id' => 'This employee already has time entries assigned to a different project on this date.',
            ]);
        }

        $entry = TimeEntry::create($data);

        return response()->json($entry->load(['company', 'employee', 'project', 'task']), 201);
    }
}
