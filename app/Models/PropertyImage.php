<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyImage extends Model
{
    protected $fillable = [
        'property_id',
        'image_url', 
        'public_id',
        'image_type',
        'order_index',
        'description',
        'original_filename',
        'size',
        'width',
        'height'
    ];
    
    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
