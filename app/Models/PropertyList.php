<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyList extends Model
{
    use HasFactory;

    protected $table = 'properties';
    protected $fillable = [
        'owner_id',
        'title',
        'description',
        'type',
        'price',
        'price_type',
        'location',
        'size',
        'property_state',
        'bedrooms',
        'bathrooms',
    ];

    protected $casts = [
        'location' => 'array',
        'bedrooms' => 'integer',
        'bathrooms' => 'integer',
    ];

    // Relations
    public function features()
    {
        return $this->belongsToMany(Feature::class, 'property_features', 'property_id', 'feature_id');
    }

    public function images()
    {
        return $this->hasMany(PropertyImage::class, 'property_id');
    }
}