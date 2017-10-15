<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Storage;

class ImageController
{
    /**
     * Serve public notification images
     * Route: GET /api/images/notifications/{adminId}/{date}/{filename}
     * Public - no auth required
     */
    public function serveNotificationImage($adminId, $date, $filename)
    {
        $filename = basename($filename);
        $path = "notifications/{$adminId}/{$date}/{$filename}";

        // Check if file exists
        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'Image not found'], 404);
        }

        try {
            // Get file
            $file = Storage::disk('public')->get($path);
            $mimeType = Storage::disk('public')->mimeType($path);

            // Return as inline view (display in browser)
            return response($file)
                ->header('Content-Type', $mimeType)
                ->header('Cache-Control', 'public, max-age=86400')
                ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error reading file', 'error' => $e->getMessage()], 500);
        }
    }
}
