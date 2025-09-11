<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
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
    ];

    protected $casts = [
        'location' => 'array', // Cast JSON to array
    ];

    public function features()
    {
        return $this->belongsToMany(Feature::class, 'property_features');
    }
    public function images()
    {
        return $this->hasMany(PropertyImage::class);
    }

    public function documents()
    {
        return $this->hasMany(PropertyDocument::class);
    }
    function user()
    {
       return $this->belongsTo(User::class);
    }
}
