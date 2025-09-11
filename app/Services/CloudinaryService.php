<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Transformation\Resize;
use Exception;

class CloudinaryService
{
    protected $cloudinary;

    public function __construct()
    {
        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => config('services.cloudinary.cloud_name'),
                'api_key' => config('services.cloudinary.api_key'),
                'api_secret' => config('services.cloudinary.api_secret'),
            ]
        ]);
    }

    /**
     * Upload file to Cloudinary
     */
    public function uploadFile($file, $folder = null, $options = [])
    {
        try {
            $uploadOptions = [
                'resource_type' => 'auto', // Automatically detect file type
            ];

            // Add folder if specified
            if ($folder) {
                $uploadOptions['folder'] = $folder;
            }

            // Merge with custom options
            $uploadOptions = array_merge($uploadOptions, $options);

            // Upload the file
            $result = $this->cloudinary->uploadApi()->upload(
                $file->getRealPath(),
                $uploadOptions
            );

            return [
                'success' => true,
                'public_id' => $result['public_id'],
                'url' => $result['secure_url'],
                'original_filename' => $result['original_filename'] ?? $file->getClientOriginalName(),
                'format' => $result['format'],
                'size' => $result['bytes'],
                'width' => $result['width'] ?? null,
                'height' => $result['height'] ?? null,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete file from Cloudinary
     */
    public function deleteFile($publicId, $resourceType = 'image')
    {
        try {
            $result = $this->cloudinary->uploadApi()->destroy($publicId, [
                'resource_type' => $resourceType
            ]);

            return [
                'success' => $result['result'] === 'ok',
                'result' => $result['result']
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get optimized image URL with transformations
     */
    public function getOptimizedUrl($publicId, $transformations = [])
    {
        try {
            $url = $this->cloudinary->image($publicId);
            
            // Apply transformations if provided
            if (!empty($transformations)) {
                foreach ($transformations as $transformation) {
                    $url = $url->addTransformation($transformation);
                }
            }

            return $url->toUrl();
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Upload multiple files
     */
    public function uploadMultipleFiles($files, $folder = null, $options = [])
    {
        $results = [];
        
        foreach ($files as $file) {
            $results[] = $this->uploadFile($file, $folder, $options);
        }
        
        return $results;
    }
}