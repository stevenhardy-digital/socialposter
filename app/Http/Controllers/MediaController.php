<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class MediaController extends Controller
{
    /**
     * Get all media for the authenticated user
     */
    public function index(): JsonResponse
    {
        $media = Media::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'media' => $media
        ]);
    }

    /**
     * Upload and process images with platform-specific crops
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'images' => 'required|array|max:5',
            'images.*' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240', // 10MB
            'crops' => 'required|array',
            'crops.*' => 'required|json'
        ]);

        $uploadedMedia = [];

        foreach ($request->file('images') as $index => $file) {
            $cropData = json_decode($request->input("crops.{$index}"), true);
            
            // Generate unique filename
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            
            // Store original image
            $originalPath = $file->storeAs('media/originals', $filename, 'public');
            
            // Create media record
            $media = Media::create([
                'user_id' => auth()->id(),
                'filename' => $file->getClientOriginalName(),
                'original_path' => $originalPath,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'width' => null, // Will be set after processing
                'height' => null, // Will be set after processing
            ]);

            // If no specific crops provided, generate default crops for all platforms
            if (empty($cropData['crops'])) {
                $cropData['crops'] = $this->generateDefaultCrops($media, $file, $cropData['platforms']);
            }

            // Process image and create crops
            $this->processImageCrops($media, $file, $cropData);
            
            // Generate thumbnail
            $this->generateThumbnail($media, $file);
            
            $uploadedMedia[] = $media->fresh();
        }

        return response()->json([
            'message' => 'Images uploaded and processed successfully',
            'media' => $uploadedMedia
        ]);
    }

    /**
     * Delete media and all associated files
     */
    public function destroy(Media $media): JsonResponse
    {
        // Check ownership
        if ($media->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Delete all files
        if ($media->original_path) {
            Storage::disk('public')->delete($media->original_path);
        }
        
        if ($media->thumbnail_path) {
            Storage::disk('public')->delete($media->thumbnail_path);
        }

        // Delete platform-specific crops
        $crops = json_decode($media->platform_crops, true) ?? [];
        foreach ($crops as $crop) {
            if (isset($crop['path'])) {
                Storage::disk('public')->delete($crop['path']);
            }
        }

        $media->delete();

        return response()->json([
            'message' => 'Media deleted successfully'
        ]);
    }

    /**
     * Process image crops for different platforms
     */
    private function processImageCrops(Media $media, $file, array $cropData): void
    {
        $manager = new ImageManager(new Driver());
        
        // Handle both file uploads and existing file paths
        if (is_string($file)) {
            // File path - read from storage
            $image = $manager->read(Storage::disk('public')->path($file));
        } else {
            // File upload
            $image = $manager->read($file);
        }
        $originalWidth = $image->width();
        $originalHeight = $image->height();

        // Update media dimensions
        $media->update([
            'width' => $originalWidth,
            'height' => $originalHeight
        ]);

        $platformCrops = [];
        $platforms = $cropData['platforms'] ?? [];
        $crops = $cropData['crops'] ?? [];

        foreach ($platforms as $platform) {
            if (!isset($crops[$platform])) continue;

            $crop = $crops[$platform];
            
            // Create cropped image
            $croppedImage = $manager->read($file);
            $croppedImage->crop(
                (int) $crop['width'],
                (int) $crop['height'],
                (int) $crop['x'],
                (int) $crop['y']
            );

            // Resize to platform-optimal dimensions
            $dimensions = $this->getPlatformDimensions($platform);
            if ($dimensions) {
                $croppedImage->resize($dimensions['width'], $dimensions['height']);
            }

            // Save cropped image
            $cropFilename = pathinfo($media->original_path, PATHINFO_FILENAME) . "_{$platform}." . pathinfo($media->original_path, PATHINFO_EXTENSION);
            $cropPath = "media/crops/{$cropFilename}";
            
            Storage::disk('public')->put($cropPath, $croppedImage->toJpeg());

            $platformCrops[$platform] = [
                'path' => $cropPath,
                'url' => Storage::disk('public')->url($cropPath),
                'width' => $croppedImage->width(),
                'height' => $croppedImage->height(),
                'crop_data' => $crop
            ];
        }

        $media->update([
            'platform_crops' => json_encode($platformCrops)
        ]);
    }

    /**
     * Generate thumbnail for media
     */
    private function generateThumbnail(Media $media, $file): void
    {
        $manager = new ImageManager(new Driver());
        $image = $manager->read($file);
        $image->cover(200, 200); // Square thumbnail

        $thumbnailFilename = pathinfo($media->original_path, PATHINFO_FILENAME) . '_thumb.' . pathinfo($media->original_path, PATHINFO_EXTENSION);
        $thumbnailPath = "media/thumbnails/{$thumbnailFilename}";
        
        Storage::disk('public')->put($thumbnailPath, $image->toJpeg());

        $media->update([
            'thumbnail_path' => $thumbnailPath,
            'thumbnail_url' => Storage::disk('public')->url($thumbnailPath)
        ]);
    }

    /**
     * Generate default crops for platforms
     */
    private function generateDefaultCrops(Media $media, $file, array $platforms): array
    {
        $manager = new ImageManager(new Driver());
        $image = $manager->read($file);
        $imageWidth = $image->width();
        $imageHeight = $image->height();
        
        $crops = [];
        
        foreach ($platforms as $platform) {
            $ratio = $this->getPlatformRatio($platform);
            if (!$ratio) continue;
            
            // Calculate crop dimensions for center crop
            $sourceRatio = $imageWidth / $imageHeight;
            
            if ($sourceRatio > $ratio) {
                // Image is wider than target ratio - crop width
                $cropHeight = $imageHeight;
                $cropWidth = $cropHeight * $ratio;
                $cropX = ($imageWidth - $cropWidth) / 2;
                $cropY = 0;
            } else {
                // Image is taller than target ratio - crop height
                $cropWidth = $imageWidth;
                $cropHeight = $cropWidth / $ratio;
                $cropX = 0;
                $cropY = ($imageHeight - $cropHeight) / 2;
            }
            
            $crops[$platform] = [
                'x' => (int) $cropX,
                'y' => (int) $cropY,
                'width' => (int) $cropWidth,
                'height' => (int) $cropHeight
            ];
        }
        
        return $crops;
    }

    /**
     * Get platform aspect ratio
     */
    private function getPlatformRatio(string $platform): ?float
    {
        $ratios = [
            'instagram' => 1.0,
            'facebook' => 1.91,
            'linkedin' => 1.91,
            'twitter' => 1.91,
            'instagram-story' => 0.5625,
            'facebook-story' => 0.5625,
        ];

        return $ratios[$platform] ?? null;
    }

    /**
     * Get optimal dimensions for each platform
     */
    private function getPlatformDimensions(string $platform): ?array
    {
        $dimensions = [
            'instagram' => ['width' => 1080, 'height' => 1080], // Square
            'facebook' => ['width' => 1200, 'height' => 628],   // Landscape
            'linkedin' => ['width' => 1200, 'height' => 628],   // Landscape
            'twitter' => ['width' => 1200, 'height' => 628],    // Landscape
            'instagram-story' => ['width' => 1080, 'height' => 1920], // Vertical
            'facebook-story' => ['width' => 1080, 'height' => 1920],  // Vertical
        ];

        return $dimensions[$platform] ?? null;
    }

    /**
     * Update crops for existing media
     */
    public function updateCrops(Request $request, Media $media): JsonResponse
    {
        // Check ownership
        if ($media->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'platforms' => 'required|array',
            'crops' => 'required|array'
        ]);

        $cropData = [
            'platforms' => $request->platforms,
            'crops' => $request->crops
        ];

        // Re-process crops with new settings
        $this->processImageCrops($media, $media->original_path, $cropData);

        return response()->json([
            'message' => 'Crops updated successfully',
            'media' => $media->fresh()
        ]);
    }

    /**
     * Get media by ID with platform crops
     */
    public function show(Media $media): JsonResponse
    {
        // Check ownership
        if ($media->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'media' => $media
        ]);
    }
}