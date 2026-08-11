<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CommunityMediaController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'file' => 'required|file|max:51200|mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/x-matroska,video/x-msvideo'
        ]);

        $file = $data['file'];
        $path = $file->store('community-media', 'public');
        $mime = $file->getMimeType();
        $type = Str::startsWith($mime, 'video') ? 'video' : 'image';

        $relative = Storage::disk('public')->url($path);
        $absolute = str_starts_with($relative, 'http') ? $relative : url($relative);

        return response()->json([
            'url' => $absolute,
            'media_url' => $absolute,
            'type' => $type
        ], 201);
    }
}
