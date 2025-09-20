<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\CSAgentPropertyAssign;

class CreateAssignmentRequest extends FormRequest
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
            'property_id' => 'required|integer|exists:properties,id',
            'cs_agent_id' => 'required|integer|exists:users,id',
            'notes' => 'nullable|string|max:1000',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'assigned_at' => 'nullable|date|after_or_equal:now',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'property_id.required' => 'Property is required.',
            'property_id.exists' => 'Selected property does not exist.',
            'cs_agent_id.required' => 'CS Agent is required.',
            'cs_agent_id.exists' => 'Selected CS Agent does not exist.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
            'priority.in' => 'Priority must be one of: low, normal, high, urgent.',
            'assigned_at.after_or_equal' => 'Assignment date cannot be in the past.',
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
                if ($user && (!$user->isAgent() || !$user->isActive())) {
                    $validator->errors()->add('cs_agent_id', 'Selected user is not an active CS Agent.');
                }
            }

            // Check if property can be assigned (doesn't have active assignment)
            if ($this->filled('property_id')) {
                $property = \App\Models\Property::find($this->property_id);
                if ($property && !$property->canBeAssigned()) {
                    $validator->errors()->add('property_id', 'Property already has an active assignment.');
                }
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default assigned_at to now if not provided
        if (!$this->has('assigned_at')) {
            $this->merge([
                'assigned_at' => now()
            ]);
        }

        // Set default priority if not provided
        if (!$this->has('priority')) {
            $this->merge([
                'priority' => 'normal'
            ]);
        }
    }
}
