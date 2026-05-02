<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTimeEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id'  => ['required', 'integer', 'exists:companies,id'],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'project_id'  => ['nullable', 'integer', 'exists:projects,id'],
            'task_id'     => ['required', 'integer', 'exists:tasks,id'],
            'started_at'  => ['required', 'date'],
            'ended_at'    => ['required', 'date', 'after:started_at'],
        ];
    }
}
