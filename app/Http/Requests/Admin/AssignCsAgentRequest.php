<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AssignCsAgentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'cs_agent_id' => 'required|integer|exists:users,id',
            'notes' => 'nullable|string|max:1000',
            'priority' => 'nullable|in:low,normal,high,urgent'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'cs_agent_id.required' => 'CS Agent is required.',
            'cs_agent_id.exists' => 'Selected CS Agent does not exist.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
            'priority.in' => 'Priority must be one of: low, normal, high, urgent.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check if CS Agent is actually an agent with active status
            if ($this->filled('cs_agent_id')) {
                $user = \App\Models\User::find($this->cs_agent_id);
                if ($user && (!$user->isCsAgent() || !$user->isActive())) {
                    $validator->errors()->add('cs_agent_id', 'Selected user is not an active CS Agent.');
                }
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default priority if not provided
        if (!$this->has('priority')) {
            $this->merge([
                'priority' => 'normal'
            ]);
        }
    }
}

