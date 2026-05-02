<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employees = Employee::orderBy('name')
            ->when($request->company_id, fn ($q, $id) => $q->whereHas('companies', fn ($q) => $q->where('companies.id', $id)))
            ->get();

        return response()->json($employees);
    }
}
