<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\CSAgentPropertyAssign;

class AssignmentFilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Agent filtering
            'cs_agent_id' => 'nullable|integer|exists:users,id',

            // Status filtering
            'status' => 'nullable|array',
            'status.*' => 'in:' . implode(',', CSAgentPropertyAssign::getStatuses()),

            // Property filtering
            'property_id' => 'nullable|integer|exists:properties,id',
            'property_type' => 'nullable|in:Apartment,Villa,Duplex,Roof,Land',
            'property_state' => 'nullable|in:Valid,Invalid,Pending,Rented,Sold',

            // Admin filtering
            'assigned_by' => 'nullable|integer|exists:users,id',

            // Date range filtering
            'date_from' => 'nullable|date|before_or_equal:today',
            'date_to' => 'nullable|date|after_or_equal:date_from|before_or_equal:today',

            // Priority filtering
            'priority' => 'nullable|in:low,normal,high,urgent',

            // Search
            'search' => 'nullable|string|max:255',

            // Sorting and pagination
            'sort_by' => 'nullable|in:assigned_at,status,property_id,cs_agent_id,created_at,completed_at',
            'sort_order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'status.*.in' => 'Invalid assignment status.',
            'property_type.in' => 'Invalid property type.',
            'property_state.in' => 'Invalid property state.',
            'date_to.after_or_equal' => 'End date must be after or equal to start date.',
            'priority.in' => 'Invalid priority level.',
            'per_page.min' => 'Per page value must be at least 5.',
            'per_page.max' => 'Per page value cannot exceed 100.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert string values to arrays if needed
        if ($this->has('status') && is_string($this->status)) {
            $this->merge([
                'status' => explode(',', $this->status)
            ]);
        }

        // Set default values
        $this->merge([
            'sort_by' => $this->sort_by ?? 'assigned_at',
            'sort_order' => $this->sort_order ?? 'desc',
            'per_page' => $this->per_page ?? 15,
        ]);
    }
}
