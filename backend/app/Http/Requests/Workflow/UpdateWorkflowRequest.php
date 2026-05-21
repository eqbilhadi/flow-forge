<?php

namespace App\Http\Requests\Workflow;

use App\Enums\TriggerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('api')->user()->canEdit();
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:200',
            'description' => 'nullable|string|max:1000',
            'definition' => 'sometimes|array',
            'definition.steps' => 'required_with:definition|array|min:1|max:100',
            'definition.steps.*.id' => 'required_with:definition|string|max:100',
            'definition.steps.*.name' => 'required_with:definition|string|max:200',
            'definition.steps.*.type' => 'required_with:definition|string|in:http,script,delay,condition',
            'definition.steps.*.config' => 'required_with:definition|array',
            'is_active' => 'nullable|boolean',
            'trigger_type' => ['nullable', Rule::enum(TriggerType::class)],
            'cron_expression' => 'nullable|string|max:100',
            'timeout_seconds' => 'nullable|integer|min:1|max:86400',
            'tags' => 'nullable|array',
            'change_notes' => 'nullable|string|max:500',
        ];
    }
}
