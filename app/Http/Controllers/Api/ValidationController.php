<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User; // Make sure to import your User model

class ValidationController extends Controller
{

    public function checkEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $isTaken = User::where('email', $request->query('email'))->exists();

        return response()->json([
            'isAvailable' => !$isTaken
        ]);
    }

    public function checkPhone(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10', // Example validation
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $isTaken = User::where('phone_number', $request->query('phone'))->exists();

        return response()->json([
            'isAvailable' => !$isTaken
        ]);
    }
}
