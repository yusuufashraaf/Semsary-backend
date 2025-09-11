<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyDocument extends Model
{
    protected $fillable = [
        'property_id',
        'document_url',
        'public_id', 
        'document_type',
        'original_filename',
        'size'
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
