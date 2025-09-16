<?php

namespace App\Http\Controllers;

use App\Models\Feature;
use Illuminate\Http\Request;

class FeatureController extends Controller
{
    public function index()
    {
        $features = Feature::all();
        return response()->json([
            'data' => $features,
            'message' => 'Feature index']);
    }
}
