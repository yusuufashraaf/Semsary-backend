<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feature extends Model
{
    protected $fillable = ['name'];

    public function properties()
    {
        // return $this->belongsToMany(Property::class, 'property_feature', 'feature_id', 'property_id');
        return $this->belongsToMany(Property::class, 'property_features');

    }
}
