<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;

class ImageOfId extends Controller
{
    /**
     * Handle the upload of a user's ID image.
     *
     * @param Request $request
     * @param CloudinaryService
     * @return \Illuminate\Http\JsonResponse
     */
       public function uploadIdImage(Request $request, CloudinaryService $cloudinaryService)
    {
        $validator = Validator::make($request->all(), [
            'id_image' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
            'user_id'  => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::find($request->user_id);
        $file = $request->file('id_image');
        $folder = 'user_ids';

        // ✅ If user already has an image, delete old one first
        if ($user->id_image_public_id) {
            $cloudinaryService->deleteFile($user->id_image_public_id);
        }

            $uploadResult = $cloudinaryService->uploadFile($file, $folder);

            if (!$uploadResult['success']) {
                return response()->json([
                    'message' => 'Failed to upload image.',
                    'error' => $uploadResult['error']
                ], 500);
            }

            // Update user: new ID image + state pending
            $user->update([
                'id_image_url' => $uploadResult['url'],
                'id_state'     => 'pending',
            ]);

            return response()->json([
                'message' => 'ID image uploaded successfully.',
                'url' => $uploadResult['url']
            ]);
        }
}
