<?php

namespace App\Http\Controllers\CsAgent;

use App\Http\Controllers\Controller;
use App\Models\CSAgentPropertyAssign;
use App\Models\Property;
use App\Models\PropertyDocument;
use App\Services\CloudinaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PropertyDocumentController extends Controller
{
    protected CloudinaryService $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }

    /**
     * Upload verification documents for a property
     * POST /api/cs-agent/properties/{property}/documents
     */
    public function store(Request $request, Property $property): JsonResponse
    {
        $request->validate([
            'files' => 'required|array|min:1|max:10',
            'files.*' => 'required|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240', // 10MB max
            'document_type' => 'nullable|string|in:verification_photo,site_visit_report,owner_document,other',
            'notes' => 'nullable|string|max:1000'
        ]);

        try {
            DB::beginTransaction();

            $user = auth()->user();

            // Check if user has an active assignment for this property
            $assignment = CSAgentPropertyAssign::where('property_id', $property->id)
                ->where('cs_agent_id', $user->id)
                ->whereIn('status', ['pending', 'in_progress'])
                ->first();

            if (!$assignment) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active assignment found for this property'
                ], 404);
            }

            $uploadedFiles = [];
            $documentType = $request->input('document_type', 'verification_photo');

            foreach ($request->file('files') as $file) {
                $result = $this->cloudinaryService->uploadFile(
                    $file,
                    'properties/verification-documents',
                    [
                        'transformation' => [
                            'quality' => 'auto',
                            'fetch_format' => 'auto',
                        ]
                    ]
                );

                if ($result['success']) {
                    $document = PropertyDocument::create([
                        'property_id' => $property->id,
                        'document_url' => $result['url'],
                        'public_id' => $result['public_id'],
                        'document_type' => $documentType,
                        'original_filename' => $result['original_filename'],
                        'size' => $result['size']
                    ]);

                    $uploadedFiles[] = [
                        'id' => $document->id,
                        'document_url' => $document->document_url,
                        'document_type' => $document->document_type,
                        'original_filename' => $document->original_filename,
                        'size' => $document->size,
                        'uploaded_at' => $document->created_at->toISOString()
                    ];
                } else {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'File upload failed: ' . $result['error']
                    ], 400);
                }
            }

            // Update assignment notes if provided
            if ($request->filled('notes')) {
                $currentNotes = $assignment->notes ?? '';
                $newNote = "\n[" . now()->format('Y-m-d H:i:s') . "] Document upload: " . $request->input('notes');
                $assignment->update(['notes' => $currentNotes . $newNote]);
            }

            // Log the action
            if (class_exists('App\Models\AuditLog')) {
                \App\Models\AuditLog::log(
                    $user->id,
                    'PropertyDocument',
                    'upload_verification_documents',
                    [
                        'assignment_id' => $assignment->id,
                        'property_id' => $property->id,
                        'document_count' => count($uploadedFiles),
                        'document_type' => $documentType
                    ]
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($uploadedFiles) . ' verification documents uploaded successfully',
                'data' => $uploadedFiles
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload verification documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
