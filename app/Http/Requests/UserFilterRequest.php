<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user() && auth()->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'role' => ['nullable', Rule::in(['user', 'owner', 'agent', 'admin'])],
            'status' => ['nullable', Rule::in(['active', 'pending', 'suspended', 'blocked'])],
            'search' => ['nullable', 'string', 'max:255'],
            'sort_by' => ['nullable', Rule::in(['created_at', 'first_name', 'email', 'status', 'role'])],
            'sort_order' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'role.in' => 'Invalid role filter.',
            'status.in' => 'Invalid status filter.',
            'sort_by.in' => 'Invalid sort field.',
            'sort_order.in' => 'Invalid sort order.',
            'per_page.max' => 'Cannot display more than 100 items per page.',
        ];
    }
}
