<?php

namespace App\Http\Requests\Workflow;

use App\Enums\TriggerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('api')->user()->canEdit();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:200',
            'description' => 'nullable|string|max:1000',
            'definition' => 'required|array',
            'definition.steps' => 'required|array|min:1|max:100',
            'definition.steps.*.id' => 'required|string|max:100',
            'definition.steps.*.name' => 'required|string|max:200',
            'definition.steps.*.type' => 'required|string|in:http,script,delay,condition',
            'definition.steps.*.config' => 'required|array',
            'definition.steps.*.depends_on' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'trigger_type' => ['nullable', Rule::enum(TriggerType::class)],
            'cron_expression' => 'nullable|string|max:100',
            'timeout_seconds' => 'nullable|integer|min:1|max:86400',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'definition.steps.*.id.required' => 'Each step must have an id.',
            'definition.steps.*.type.in' => 'Step type must be one of: http, script, delay, condition.',
        ];
    }
}
