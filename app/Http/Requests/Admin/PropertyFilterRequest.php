<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PropertyFilterRequest extends FormRequest
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
            // Status filtering
            'status' => 'nullable|array',
            'status.*' => 'in:Valid,Invalid,Pending,Rented,Sold',

            // Property type filtering
            'type' => 'nullable|array',
            'type.*' => 'in:Apartment,Villa,Duplex,Roof,Land',

            // Price range filtering
            'price_min' => 'nullable|numeric|min:0',
            'price_max' => 'nullable|numeric|min:0|gte:price_min',

            // Owner filtering
            'owner_id' => 'nullable|integer|exists:users,id',

            // Date range filtering
            'date_from' => 'nullable|date|before_or_equal:today',
            'date_to' => 'nullable|date|after_or_equal:date_from|before_or_equal:today',

            // Property specifications
            'bedrooms' => 'nullable|integer|min:1|max:20',
            'bathrooms' => 'nullable|integer|min:1|max:20',
            'size_min' => 'nullable|integer|min:1',
            'size_max' => 'nullable|integer|min:1|gte:size_min',

            // Location filtering
            'location' => 'nullable|string|max:255',

            // Sorting and pagination
            'sort_by' => 'nullable|in:created_at,price,title,status,owner',
            'sort_order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',

            // Additional filters
            'has_images' => 'nullable|boolean',
            'has_reviews' => 'nullable|boolean',
            'has_bookings' => 'nullable|boolean',
            'featured_only' => 'nullable|boolean',
            'requires_attention' => 'nullable|boolean',
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
            'status.*.in' => 'Invalid property status. Must be one of: Valid, Invalid, Pending, Rented, Sold',
            'type.*.in' => 'Invalid property type. Must be one of: Apartment, Villa, Duplex, Roof, Land',
            'price_max.gte' => 'Maximum price must be greater than or equal to minimum price',
            'date_to.after_or_equal' => 'End date must be after or equal to start date',
            'size_max.gte' => 'Maximum size must be greater than or equal to minimum size',
            'per_page.min' => 'Per page value must be at least 5',
            'per_page.max' => 'Per page value cannot exceed 100',
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
            // Ensure price_min is not greater than price_max when both are provided
            if ($this->filled(['price_min', 'price_max']) && $this->price_min > $this->price_max) {
                $validator->errors()->add('price_min', 'Minimum price cannot be greater than maximum price');
            }

            // Ensure size_min is not greater than size_max when both are provided
            if ($this->filled(['size_min', 'size_max']) && $this->size_min > $this->size_max) {
                $validator->errors()->add('size_min', 'Minimum size cannot be greater than maximum size');
            }

            // Validate date range
            if ($this->filled(['date_from', 'date_to'])) {
                $dateFrom = \Carbon\Carbon::parse($this->date_from);
                $dateTo = \Carbon\Carbon::parse($this->date_to);

                if ($dateFrom->gt($dateTo)) {
                    $validator->errors()->add('date_from', 'Start date cannot be after end date');
                }
            }
        });
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

        if ($this->has('type') && is_string($this->type)) {
            $this->merge([
                'type' => explode(',', $this->type)
            ]);
        }

        // Set default values
        $this->merge([
            'sort_by' => $this->sort_by ?? 'created_at',
            'sort_order' => $this->sort_order ?? 'desc',
            'per_page' => $this->per_page ?? 15,
        ]);
    }
}
