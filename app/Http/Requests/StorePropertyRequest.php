<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePropertyRequest extends FormRequest
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
            'user_id'      => 'required|exists:users,id',
            'title'         => 'required|string|max:200',
            'description'   => 'nullable|string',
            'type'          => 'required|in:Apartment,Villa,Duplex,Roof,Land',
            'price'         => 'required|numeric|min:0',
            'price_type'    => 'required|in:FullPay,Monthly,Daily',
            'location'      => 'required|array',
            'location.address' => 'required|string|max:255',
            'location.lat'  => 'numeric|between:-90,90',
            'location.lng'  => 'numeric|between:-180,180',
            'size'          => 'required|integer|min:1',
            'property_state'=> 'in:Valid,Invalid,Pending,Rented,Sold',
            // Images
            'images' => 'nullable|array',
            'images.*' => 'file|mimes:jpg,jpeg,png,webp|max:2048',

            // Documents
            'documents' => 'nullable|array',
            'documents.*' => 'file|mimes:pdf,doc,docx,png,jpg,jpeg|max:5120',
    
        ];
    }
}
