<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PropertyStatusRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => 'required|in:Valid,Invalid,Pending,Rented,Sold',
            'reason' => 'nullable|string|max:500|min:5',
            'notify_owner' => 'nullable|boolean',
            'internal_notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Property status is required',
            'status.in' => 'Invalid property status. Must be one of: Valid, Invalid, Pending, Rented, Sold',
            'reason.min' => 'Reason must be at least 5 characters long',
            'reason.max' => 'Reason cannot exceed 500 characters',
            'internal_notes.max' => 'Internal notes cannot exceed 1000 characters',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Require reason when rejecting a property
            if ($this->status === 'Invalid' && !$this->filled('reason')) {
                $validator->errors()->add('reason', 'A reason is required when rejecting a property');
            }

            // Recommend reason for other status changes
            if (in_array($this->status, ['Rented', 'Sold']) && !$this->filled('reason')) {
                // This is just a business logic check - we don't add an error
                // but we could log this for admin training purposes
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values
        $this->merge([
            'notify_owner' => $this->notify_owner ?? true,
        ]);

        // Clean up reason text
        if ($this->filled('reason')) {
            $this->merge([
                'reason' => trim($this->reason)
            ]);
        }

        // Clean up internal notes
        if ($this->filled('internal_notes')) {
            $this->merge([
                'internal_notes' => trim($this->internal_notes)
            ]);
        }
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'status' => 'property status',
            'reason' => 'status change reason',
            'notify_owner' => 'owner notification preference',
            'internal_notes' => 'internal admin notes',
        ];
    }
}
