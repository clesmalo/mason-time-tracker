<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tasks = Task::orderBy('name')
            ->when($request->company_id, fn ($q, $id) => $q->where('company_id', $id))
            ->when(
                $request->has('project_id'),
                fn ($q) => $q->where('project_id', $request->project_id)
            )
            ->get();

        return response()->json($tasks);
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (!empty($data['project_id'])) {
            $project = \App\Models\Project::find($data['project_id']);
            if ((int) $project->company_id !== (int) $data['company_id']) {
                return response()->json(['message' => 'Project does not belong to the selected company.'], 422);
            }
        }

        $task = Task::create($data);

        return response()->json($task, 201);
    }
}
