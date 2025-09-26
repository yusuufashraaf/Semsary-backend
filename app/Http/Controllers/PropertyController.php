<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePropertyRequest;
use App\Http\Requests\UpdatePropertyRequest;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery\CountValidator\Exception;

class PropertyController extends Controller
{
    protected $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = auth('api')->user();
        // if ($user->role === 'owner') {
            // $properties = Property::with(['images', 'documents', 'features'])->get();
            $properties = Property::with(['images', 'documents', 'features'])->where('owner_id', $user->id)->get();
            if ($properties->isEmpty()) {
                return response()->json(['message' => 'No properties found for this owner'], 404);
            }
        // }

        return response()->json([
            'message' => 'Properties fetched successfully',
            'data' => $properties
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    // public function store(StorePropertyRequest $request)
    // {
    //     $data = $request->validated();

    //     $property = Property::create($data);


    //     if($request->has('features')){
    //         foreach($request->features as $feature){
    //             $property->features()->create(['name' => $feature]);
    //         }

    //     }
    //     if($request->hasFile('images')){
    //         foreach($request->file('images') as $file){
    //             $uploadedFile = Cloudinary::upload(
    //             $file->path(),
    //             ['folder' => 'properties/images']
    //         )->getSecurePath();
    //             $property->images()->create([
    //                 'image_url' => $uploadedFile,
    //                 'image_type' => $file->getClientMimeType(),
    //                 'order_index' => 0,
    //                 'description' => ''
    //             ]);
    //         }
    //     }



    //       if ($request->hasFile('documents')) {
    //         foreach ($request->file('documents') as $file) {
    //         $uploadedFile = Cloudinary::uploadFile(
    //             $file->getRealPath(),
    //             ['folder' => 'properties/documents']
    //         )->getSecurePath();

    //         $property->documents()->create([
    //             'document_url' => $uploadedFile,
    //             'document_type' => $file->getClientMimeType(),
    //         ]);
    //     }
    // }
    //     return response()->json([
    //         'message'=>'Property created successfully',
    //         'data'=>$property->load(['features', 'documents', 'images'])],
    //          201);
    // }

    public function store(StorePropertyRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['owner_id'] = auth('api')->id();

        $property = Property::create($data);

        // Handle features
        if ($request->has('features')) {
            $property->features()->sync($request->features);
        }

        // Handle image uploads using CloudinaryService
        if ($request->hasFile('images')) {
            $orderIndex = 0;
            foreach ($request->file('images') as $file) {
                $result = $this->cloudinaryService->uploadFile(
                    $file,
                    'properties/images',
                    [
                        'transformation' => [
                            'quality' => 'auto',
                            'fetch_format' => 'auto',
                        ]
                    ]
                );

                if ($result['success']) {
                    $property->images()->create([
                        'image_url' => $result['url'],
                        'public_id' => $result['public_id'], // Store public_id for deletion
                        'image_type' => $result['format'],
                        'order_index' => $orderIndex++,
                        'description' => '',
                        'original_filename' => $result['original_filename'],
                        'size' => $result['size'],
                        'width' => $result['width'],
                        'height' => $result['height']
                    ]);
                } else {
                    // Handle upload failure
                    return response()->json([
                        'message' => 'Image upload failed: ' . $result['error'],
                        'success' => false
                    ], 400);
                }
            }
        }

        // Handle document uploads using CloudinaryService
        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $file) {
                $result = $this->cloudinaryService->uploadFile(
                    $file,
                    'properties/documents',
                    [
                        'resource_type' => 'raw' 
                    ]
                );

                if ($result['success']) {
                    $extension = $result['format'] ?? pathinfo($result['original_filename'], PATHINFO_EXTENSION);

                    $property->documents()->create([
                        'document_url' => $result['url'],
                        'public_id' => $result['public_id'], 
                        'document_type' => $extension,      
                        'original_filename' => $result['original_filename'],
                        'size' => $result['size']
                    ]);
                } else {
                    return response()->json([
                        'message' => 'Document upload failed: ' . $result['error'],
                        'success' => false
                    ], 400);
                }
            }
        }


        return response()->json([
            'message' => 'Property created successfully',
            'data' => $property->load(['features', 'documents', 'images']),
            'success' => true
        ], 201);

    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $property = Property::where('id', $id)
            ->where('owner_id', auth()->id())
            ->with(['images', 'documents', 'features'])
            ->first();
        if (!$property) {
            return response()->json(['message' => 'Property not found'], 404);
        }
        return response()->json($property, 200);
    }

    public function showAnyone(int $id): JsonResponse|PropertyResource
    {
        $property = Property::with(['images', 'features', 'owner', 'reviews'])
            ->find($id);

        if (!$property) {
            return response()->json([
                'error' => [
                    'code' => 'PROPERTY_NOT_FOUND',
                    'message' => 'Property not found',
                ]
            ], 404);
        }

        return new PropertyResource($property);
    }


    /**
     * Update the specified resource in storage.
     */
    // public function update(Request $request, string $id)
    // {
    //     $property = Property::find($id);
    //     if (!$property) {
    //         return response()->json(['message' => 'Property not found'], 404);
    //     }
    //     $property->update($request->all());
    //     return response()->json($property, 200);
    // }


    public function update(UpdatePropertyRequest $request, Property $property): JsonResponse
    {
        $data = $request->validated();

        $user = auth('api')->user();

        if ($property->owner_id !== $user->id) {
            return response()->json([
                'message' => 'You are not allowed to edit this property'
            ], 403);
        }

        if (in_array($property->property_state, ['Sold', 'Rented'])) {
            return response()->json([
                'message' => 'This property cannot be edited because it is already ' . $property->property_state,
                'success' => false
            ], 403);
        }

        DB::beginTransaction();
        try {
            // Update main fields
            $property->update($data);

            // Sync features if provided
            if ($request->has('features')) {
                $property->features()->sync($request->features);
            }

            //Delete specific images if requested
            if ($request->filled('delete_images')) {
                foreach ($request->delete_images as $imgId) {
                    $img = $property->images()->find($imgId);
                    if ($img) {
                        if (!empty($img->public_id)) {
                            $this->cloudinaryService->deleteFile($img->public_id);
                        }
                        $img->delete();
                    }
                }
            }

            // Replace specific images
            if ($request->has('replace_images')) {
                foreach ($request->replace_images as $i => $entry) {
                    $imgId = $entry['id'] ?? null;
                    if (!$imgId)
                        continue;

                    $imageModel = $property->images()->find($imgId);
                    if (!$imageModel)
                        continue;

                    $file = $request->file("replace_images.$i.file");
                    if (!$file)
                        continue;

                    if (!empty($imageModel->public_id)) {
                        $this->cloudinaryService->deleteFile($imageModel->public_id);
                    }

                    $res = $this->cloudinaryService->uploadFile(
                        $file,
                        'properties/images',
                        ['transformation' => ['quality' => 'auto', 'fetch_format' => 'auto']]
                    );

                    if ($res['success']) {
                        $imageModel->update([
                            'image_url' => $res['url'],
                            'public_id' => $res['public_id'],
                            'image_type' => $res['format'],
                            'original_filename' => $res['original_filename'] ?? null,
                            'size' => $res['size'] ?? null,
                            'width' => $res['width'] ?? null,
                            'height' => $res['height'] ?? null,
                        ]);
                    } else {
                        throw new \Exception('Cloud upload failed: ' . ($res['error'] ?? 'unknown'));
                    }
                }
            }

            //Add new images
            if ($request->hasFile('images')) {
                $orderIndex = $property->images()->count();
                foreach ($request->file('images') as $file) {
                    $res = $this->cloudinaryService->uploadFile(
                        $file,
                        'properties/images',
                        ['transformation' => ['quality' => 'auto', 'fetch_format' => 'auto']]
                    );

                    if ($res['success']) {
                        $property->images()->create([
                            'image_url' => $res['url'],
                            'public_id' => $res['public_id'],
                            'image_type' => $res['format'],
                            'order_index' => $orderIndex++,
                            'description' => '',
                            'original_filename' => $res['original_filename'] ?? null,
                            'size' => $res['size'] ?? null,
                            'width' => $res['width'] ?? null,
                            'height' => $res['height'] ?? null,
                        ]);
                    }
                }
            }

            // Handle documents
            if ($request->filled('delete_documents')) {
                foreach ($request->delete_documents as $docId) {
                    $doc = $property->documents()->find($docId);
                    if ($doc) {
                        if (!empty($doc->public_id))
                            $this->cloudinaryService->deleteFile($doc->public_id);
                        $doc->delete();
                    }
                }
            }

            if ($request->has('replace_documents')) {
                foreach ($request->replace_documents as $i => $entry) {
                    $docId = $entry['id'] ?? null;
                    if (!$docId)
                        continue;

                    $docModel = $property->documents()->find($docId);
                    if (!$docModel)
                        continue;

                    $file = $request->file("replace_documents.$i.file");
                    if (!$file)
                        continue;

                    if (!empty($docModel->public_id))
                        $this->cloudinaryService->deleteFile($docModel->public_id);

                    $res = $this->cloudinaryService->uploadFile($file, 'properties/documents');
                    if ($res['success']) {
                        $docModel->update([
                            'document_url' => $res['url'],
                            'public_id' => $res['public_id'],
                            'document_type' => $res['format'],
                            'original_filename' => $res['original_filename'] ?? null,
                            'size' => $res['size'] ?? null,
                        ]);
                    } else {
                        throw new \Exception('Doc upload failed: ' . ($res['error'] ?? 'unknown'));
                    }
                }
            }

            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $res = $this->cloudinaryService->uploadFile($file, 'properties/documents');
                    if ($res['success']) {
                        $property->documents()->create([
                            'document_url' => $res['url'],
                            'public_id' => $res['public_id'],
                            'document_type' => $res['format'],
                            'original_filename' => $res['original_filename'] ?? null,
                            'size' => $res['size'] ?? null,
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Property updated successfully',
                'data' => $property->load(['features', 'images', 'documents']),
                'success' => true
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Update failed: ' . $e->getMessage(),
                'success' => false
            ], 500);
        }
    }
    // feature listing
    public function latestThree()
    {
        $properties = Property::orderBy('created_at', 'desc')
            ->take(3)
            ->get();

        return response()->json($properties);
    }

    public function destroy(Property $property): JsonResponse
    {
        $user = auth('api')->user();
        if ( $property->owner_id !== $user->id || $property->property_state === 'Rented') {
            return response()->json([
                'message' => 'You are not allowed to delete this property'
            ], 403);
        }
        // Delete images from Cloudinary + DB
        foreach ($property->images as $image) {
            if ($image->public_id) {
                $this->cloudinaryService->deleteFile($image->public_id);
            }
            $image->delete();
        }

        // Delete documents from Cloudinary + DB
        foreach ($property->documents as $document) {
            if ($document->public_id) {
                $this->cloudinaryService->deleteFile($document->public_id);
            }
            $document->delete();
        }

        // Detach features (pivot table)
        $property->features()->detach();

        // Delete property itself
        $property->delete();

        return response()->json([
            'message' => 'Property deleted successfully',
            'success' => true
        ], 200);
    }

    public function typesWithImage(): JsonResponse
    {
        $types = Property::with([
            'images' => function ($query) {
                $query->select('id', 'property_id', 'image_url')->limit(1); // only 1 image
            }
        ])
            ->select('id', 'type')
            ->groupBy('type', 'id')
            ->get()
            ->groupBy('type')
            ->map(function ($items) {
                $property = $items->first();
                return [
                    'id' => $property->id,
                    'type' => $property->type,
                    'image' => $property->images->first()->image_url ?? null
                ];
            })
            ->values();

        return response()->json([
            'message' => 'Property types with images fetched successfully',
            'data' => $types,
            'success' => true
        ], 200);
    }
    public function basicListing(): JsonResponse
    {
        $properties = Property::with([
            'images' => function ($q) {
                $q->select('id', 'property_id', 'image_url')->orderBy('order_index')->limit(1);
            }
        ])
            ->select('id','owner_id', 'title', 'bedrooms', 'bathrooms', 'size', 'price')
            ->latest()
            ->take(3)
            ->get()
            ->map(function ($property) {
                return [
                    'id' => $property->id,
                    'owner_id'=> $property->owner_id,
                    'title' => $property->title,
                    'bedrooms' => $property->bedrooms,
                    'bathrooms' => $property->bathrooms,
                    'sqft' => $property->size,
                    'price' => $property->price,
                    'image' => $property->images->first()->image_url ?? null,
                ];
            });

        return response()->json([
            'message' => 'Featured properties fetched successfully',
            'data' => $properties,
            'success' => true
        ]);
    }

// AI Property Description
 public function generateDescription(Request $request)
{
    $data = $request->validate([
        'title' => 'required|string',
        'bedrooms' => 'required|integer',
        'bathrooms' => 'required|integer', 
        'size' => 'required|numeric',
        'location' => 'required|string',
    ]);

    try {
        $apikey = config('services.openrouter.key');
         if (!$apikey) {
            return response()->json([
                'success' => false,
                'description' => 'OpenRouter API key is not configured.'
            ], 500);
        }
        $response = Http::timeout(30)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $apikey,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => request()->getHost(),
                'X-Title' => 'Property Description Generator'
            ])
            ->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a professional real estate description writer. Write engaging, detailed property descriptions.'
                    ],
                    [
                        'role' => 'user',
                        'content' => "Write a compelling property description for: {$data['title']}, {$data['bedrooms']} bedrooms, {$data['bathrooms']} bathrooms, {$data['size']} sqm, located in {$data['location']}."
                    ]
                ],
                'max_tokens' => 250,
                'temperature' => 0.7
            ]);

        \Log::info('OpenRouter Response Status:', ['status' => $response->status()]);
        \Log::info('OpenRouter Response Body:', ['body' => $response->json()]);

        if ($response->successful()) {
            $result = $response->json();
            
            if (isset($result['choices'][0]['message']['content'])) {
                return response()->json([
                    'success' => true,
                    'description' => trim($result['choices'][0]['message']['content'])
                ]);
            }
            

            return response()->json([
                'success' => false,
                'description' => 'No content in AI response',
                'debug' => $result
            ]);
        }


        return response()->json([
            'success' => false,
            'description' => 'API request failed',
            'status' => $response->status(),
            'debug' => $response->json()
        ]);

    } catch (\Exception $e) {
        \Log::error('OpenRouter API error:', ['error' => $e->getMessage()]);
        
        return response()->json([
            'success' => false,
            'description' => 'Connection error: ' . $e->getMessage()
        ], 500);
    }
}
// Get all properties
public function getProperties()
{
    $properties = Property::with(['owner', 'images', 'documents'])->get();

    $properties->transform(function ($property) {
        return [
            'id' => $property->id,
            'title' => $property->title,
            'description' => $property->description,
            'price' => $property->formatted_price, // accessor
            'location' => $property->location_string, // accessor
            'owner' => $property->owner,
            'is_in_wishlist' => $property->is_in_wishlist,
            'images' => $property->images->map(fn($img) => [
                'id' => $img->id,
                'url' => $img->image_url,
                'type' => $img->image_type,
                'order' => $img->order_index,
            ]),
            'documents' => $property->documents->map(fn($doc) => [
                'id' => $doc->id,
                'url' => $doc->document_url,
                'type' => $doc->document_type,
                'original_filename' => $doc->original_filename,
            ]),
        ];
    });

    return response()->json([
        'success' => true,
        'message' => 'Properties fetched successfully',
        'data' => $properties
    ], 200);
}


// Change property status
public function changeStatus(Request $request, $id)
{
    $request->validate([
        'status' => 'required|in:Valid,Invalid,Pending,Rented,Sold, Rejected',
    ]);

    $property = Property::findOrFail($id);
    $property->property_state = $request->property;
    $property->update();

    return response()->json([
        'success' => true,
        'message' => 'Property status updated!',
        'data' => $property
    ], 200);
}

}