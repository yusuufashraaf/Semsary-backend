<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\CSAgentPropertyAssign;

class UpdateAssignmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && ($this->user()->isAdmin() || $this->user()->isAgent());
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $assignment = $this->route('assignment') ?? CSAgentPropertyAssign::find($this->route('id'));

        return [
            'status' => 'required|in:' . implode(',', CSAgentPropertyAssign::getStatuses()),
            'notes' => 'nullable|string|max:1000',
            'priority' => 'nullable|in:low,normal,high,urgent',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Status is required.',
            'status.in' => 'Invalid status. Must be one of: ' . implode(', ', CSAgentPropertyAssign::getStatuses()),
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
            $assignment = $this->route('assignment') ?? CSAgentPropertyAssign::find($this->route('id'));

            if (!$assignment) {
                $validator->errors()->add('assignment', 'Assignment not found.');
                return;
            }

            $requestedStatus = $this->input('status');
            $currentStatus = $assignment->status;

            // Validate status transitions
            $validTransitions = $this->getValidStatusTransitions($currentStatus);

            if (!in_array($requestedStatus, $validTransitions)) {
                $validator->errors()->add('status',
                    "Cannot change status from '{$currentStatus}' to '{$requestedStatus}'. Valid transitions: " .
                    implode(', ', $validTransitions)
                );
            }

            // If user is CS Agent, they can only update their own assignments
            if ($this->user()->isAgent() && !$this->user()->isAdmin()) {
                if ($assignment->cs_agent_id !== $this->user()->id) {
                    $validator->errors()->add('assignment', 'You can only update your own assignments.');
                }
            }

            // Require notes when rejecting
            if ($requestedStatus === 'rejected' && empty($this->input('notes'))) {
                $validator->errors()->add('notes', 'Notes are required when rejecting an assignment.');
            }
        });
    }

    /**
     * Get valid status transitions for current status
     */
    private function getValidStatusTransitions(string $currentStatus): array
    {
        return match($currentStatus) {
            'pending' => ['in_progress', 'rejected'],
            'in_progress' => ['completed', 'rejected'],
            'completed' => [], // No transitions from completed
            'rejected' => ['pending'], // Can be reassigned
            default => []
        };
    }
}
