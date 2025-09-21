<?php

namespace App\Http\Requests\CsAgent;

use App\Models\CSAgentPropertyAssign;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVerificationStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isCsAgent();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'status' => 'required|in:in_progress,completed,rejected',
            'notes' => 'nullable|string|max:2000'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Verification status is required.',
            'status.in' => 'Status must be one of: in_progress, completed, rejected.',
            'notes.max' => 'Notes cannot exceed 2000 characters.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $property = $this->route('property');
            $user = $this->user();

            // Check if user has an active assignment for this property
            if ($property && $user) {
                $assignment = CSAgentPropertyAssign::where('property_id', $property->id)
                    ->where('cs_agent_id', $user->id)
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->first();

                if (!$assignment) {
                    $validator->errors()->add('property', 'No active assignment found for this property.');
                }

                // Validate status transitions
                if ($assignment) {
                    $currentStatus = $assignment->status;
                    $newStatus = $this->input('status');

                    // Define allowed transitions
                    $allowedTransitions = [
                        'pending' => ['in_progress', 'rejected'],
                        'in_progress' => ['completed', 'rejected'],
                    ];

                    if (!isset($allowedTransitions[$currentStatus]) ||
                        !in_array($newStatus, $allowedTransitions[$currentStatus])) {
                        $validator->errors()->add('status', "Cannot change status from '{$currentStatus}' to '{$newStatus}'.");
                    }
                }
            }
        });
    }
}
