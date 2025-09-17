<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePropertyRequest extends FormRequest
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
        'user_id' => 'sometimes|exists:users,id',
        'title' => 'sometimes|string|max:255',
        'description' => 'sometimes|string|min:10',
        'bedrooms'    => 'sometimes|integer|min:1',
        'bathrooms'   => 'sometimes|integer|min:1',
        'type' => 'sometimes|string',
        'price' => 'sometimes|numeric',
        'price_type' => 'sometimes|string',
        'location' => 'sometimes|array',
        'location.address' => 'sometimes|string',
        'location.lat' => 'sometimes|numeric',
        'location.lng' => 'sometimes|numeric',
        'size' => 'sometimes|numeric',
        'property_state' => 'sometimes|string',
        'features' => 'sometimes|array',
        'images.*' => 'sometimes|file|mimes:jpeg,png,jpg',
        'documents.*' => 'sometimes|file|mimes:pdf,doc,docx',
    ];
}

}
